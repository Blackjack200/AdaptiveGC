<?php

namespace Blackjack200\AdaptiveGC;

final class RootCountIncrementStabler {
	private function __construct() { }


	private const MAX_SAMPLE_COUNT = 500;
	public static float $smoothingFactor = 0.5; // Smoothing factor for EMA
	private static float $lastEma = 0;
	private static array $samples = [];

	public static function submitIncrement(float $increment) : void {
		self::$samples[] = $increment;

		if (count(self::$samples) > self::MAX_SAMPLE_COUNT) {
			array_shift(self::$samples);
		}

		self::$lastEma = self::calculateEma(self::$samples, self::$lastEma);
	}

	public static function getStableIncrement() : float {
		return self::$lastEma;
	}

	private static function calculateEma(array $values, float $previousEma) : float {
		$alpha = self::$smoothingFactor;
		if (count($values) === 1) {
			return $values[0];
		}

		$currentValue = end($values);
		return $alpha * $currentValue + (1 - $alpha) * $previousEma;
	}
}