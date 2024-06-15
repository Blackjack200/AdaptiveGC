<?php

declare(strict_types=1);

namespace Blackjack200\AdaptiveGC;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {
	private ?int $notifierId = null;
	private ?TaskHandler $taskHandler = null;

	protected function onEnable() : void {
		$logger = $this->getLogger();
		if (!is_int(gc_status()['roots'] ?? null)) {
			$logger->info('Gc root count is unavailable in php ' . PHP_VERSION . '.');
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		if (!AdaptiveGcHandler::checkPocketMineAvailability()) {
			$logger->info('AdaptiveGC is not compatible with your PocketMine-MP.');
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		AdaptiveGcHandler::$logger = $this->getLogger();
		$this->reloadConfig();
		$notifierEntry = Server::getInstance()->getTickSleeper()->addNotifier(static fn() => AdaptiveGcHandler::run());
		$this->notifierId = $notifierEntry->getNotifierId();
		$notifier = $notifierEntry->createNotifier();
		$this->taskHandler = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => $notifier->wakeupSleeper()), 1);
	}

	protected function onDisable() : void {
		if ($this->notifierId !== null) {
			$this->getServer()->getTickSleeper()->removeNotifier($this->notifierId);
			$this->notifierId = null;
		}
		if ($this->taskHandler !== null) {
			$this->taskHandler->cancel();
			$this->taskHandler = null;
		}
	}

	public function reloadConfig() : void {
		parent::reloadConfig();
		$config = $this->getConfig();
		AdaptiveGcHandler::$thresholdPct = (float) $config->get('trigger-percentage', AdaptiveGcHandler::$thresholdPct);
		AdaptiveGcHandler::$avoidTimeExceed = (bool) $config->get('avoid-time-exceed', AdaptiveGcHandler::$avoidTimeExceed);
		AdaptiveGcHandler::$triggerNoPlayer = (bool) $config->get('trigger-no-player', AdaptiveGcHandler::$triggerNoPlayer);
		AdaptiveGcHandler::$forceRootCount = (int) $config->get('force-root-count', AdaptiveGcHandler::$forceRootCount);
		AdaptiveGcHandler::$gcSkipThresholdRatio = (float) $config->get('gc-skip-threshold-ratio', AdaptiveGcHandler::$gcSkipThresholdRatio);

		GcTimePredictor::$smoothingFactor = (float) $config->get('smoothing-factor', GcTimePredictor::$smoothingFactor);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		switch ($label) {
			case 'gc_status':
				$status = gc_status();
				$sender->sendMessage("Gc runs: " . (((string) $status['runs']) ?? 'unavailable') . '.');
				$sender->sendMessage("Possibly roots: " . (string) (($status['roots']) ?? 'unavailable') . '.');
				$sender->sendMessage("Actual Gc: " . number_format(1000 * GcTimePredictor::getLastActualGcTime(), 3) . "ms.");
				$sender->sendMessage("Predicted Gc: " . number_format(1000 * GcTimePredictor::getPredictedGcTime(), 3) . "ms.");
				return true;
			case 'adaptive_gc_reload':
				$this->reloadConfig();
				$sender->sendMessage(TextFormat::GREEN . 'Gc config reloaded.');
				return true;
		}
		return false;
	}
}
