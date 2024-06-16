<?php

namespace Blackjack200\AdaptiveGC;

use Closure;
use Logger;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

final class AdaptiveGcHandler {
	public static Logger $logger;
	private static Closure $getNextTick;
	private static Closure $formatMs;

	public static bool $enabled = true;
	private static TimingsHandler $gc;

	public static bool $triggerNoPlayer = true;
	public static bool $avoidTimeExceed = true;
	public static float $gcSkipThresholdRatio = 0.01;
	public static float $thresholdPct = 90;
	public static float $forceRootCount = 500000;

	private static int $lastRootCount;

	private function __construct() { }

	public static function getThreshold() : int {
		return gc_status()['threshold'] ?? throw new RuntimeException('unsupported root count');
	}

	public static function getRootCount() : int {
		return gc_status()['roots'] ?? throw new RuntimeException('unsupported root count');
	}

	private static function getRootCountIncreased() : int {
		return self::getRootCount() - self::$lastRootCount;
	}

	public static function checkPocketMineAvailability() : bool {
		$memoryManager = Server::getInstance()->getMemoryManager();
		try {
			$propertyNextTick = new ReflectionProperty(Server::getInstance(), 'nextTick');
			$propertyPeriod = new ReflectionProperty($memoryManager, 'garbageCollectionPeriod');
		} catch (ReflectionException $e) {
			self::$logger->error('Error occurred when checking PocketMine-MP availability.');
			self::$logger->logException($e);
			return false;
		}
		$typ = $propertyNextTick->getType();
		if ($typ === null) {
			return false;
		}
		if ($typ->getName() !== 'float') {
			return false;
		}
		$typ = $propertyPeriod->getType();
		if ($typ === null) {
			return false;
		}
		if ($typ->getName() !== 'int') {
			return false;
		}
		$propertyPeriod->setAccessible(true);
		$propertyPeriod->setValue($memoryManager, 0);
		/** @phpstan-ignore-next-line */
		self::$getNextTick = fn() => $this->nextTick;
		self::$gc = new TimingsHandler('AdaptiveGC', Timings::$garbageCollector);
		self::$formatMs = static fn(float $s) : string => number_format((1000 * $s), 3) . 'ms';
		self::$lastRootCount = self::getRootCount();
		return true;
	}

	public static function run() : void {
		if (!self::$enabled) {
			return;
		}
		if (gc_enabled()) {
			self::$logger->debug('Detected auto GC is enabled, disabling auto GC.');
			gc_disable();
		}

		$ser = Server::getInstance();
		$now = microtime(true);

		$tickRemaining = (self::$getNextTick->call($ser) - $now);
		$tickRemainingPct = number_format($tickRemaining / Server::TARGET_SECONDS_PER_TICK, 3);

		if (self::getRootCount() >= self::$forceRootCount) {
			goto gc;
		}

		$increased = self::getRootCountIncreased();
		RootCountIncrementStabler::submitIncrement($increased);
		$predicted = RootCountIncrementStabler::getStableIncrement();
		$ratio = ($increased - $predicted) / $predicted;
		if ($predicted !== 0.0 && $ratio < self::$gcSkipThresholdRatio) {
			//skip gc
			return;
		}

		if (self::$triggerNoPlayer && count($ser->getOnlinePlayers()) === 0) {
			goto gc;
		}

		if ($tickRemainingPct > self::$thresholdPct) {
			return;
		}

		$predictedMaxGcTime = GcTimePredictor::getPredictedMaxGcTime();
		if (self::$avoidTimeExceed && $predictedMaxGcTime !== 0.0 && $predictedMaxGcTime >= $tickRemaining) {
			self::$logger->debug('Predicted gc time is ' . (self::$formatMs)($predictedMaxGcTime) . ', but current tick just have ' . (self::$formatMs)($tickRemaining) . ' left, skipping gc.');
			return;
		}

		gc:
		$gcStart = microtime(true);

		self::$gc->startTiming();
		$ser->getMemoryManager()->triggerGarbageCollector();
		self::$gc->stopTiming();

		$gcTime = microtime(true) - $gcStart;
		self::$lastRootCount = self::getRootCount();

		GcTimePredictor::submitGcTime($gcTime);

		$exceed = $gcTime - $tickRemaining;
		if ($exceed > 0) {
			self::$logger->debug('actual gc time ' . (self::$formatMs)($gcTime) . ' exceeded tick remaining ' . (self::$formatMs)($tickRemaining) . ', exceeded ' . (self::$formatMs)($exceed) . '.');
		}
	}
}