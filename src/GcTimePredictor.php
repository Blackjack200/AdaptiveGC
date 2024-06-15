<?php

namespace Blackjack200\AdaptiveGC;

final class GcTimePredictor {
	private function __construct() { }


	private const MAX_SAMPLE_COUNT = 500;
	private const MULTIPLY_FACTOR = 1.05;
	public static float $smoothingFactor = 0.5; // Smoothing factor for EMA
	private static float $lastEma = 0;
	private static array $gcTimeSamples = [];

	public static function submitGcTime(float $timeUsed) : void {
		self::$gcTimeSamples[] = $timeUsed;

		if (count(self::$gcTimeSamples) > self::MAX_SAMPLE_COUNT) {
			array_shift(self::$gcTimeSamples);
		}

		self::$lastEma = self::calculateEma(self::$gcTimeSamples, self::$lastEma);
	}

	public static function getPredictedGcTime() : float {
		return self::$lastEma;
	}

	public static function getPredictedMaxGcTime() : float {
		return self::$lastEma * self::MULTIPLY_FACTOR;
	}

	public static function getLastActualGcTime() : float {
		return self::$gcTimeSamples[count(self::$gcTimeSamples) - 1] ?? NAN;
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