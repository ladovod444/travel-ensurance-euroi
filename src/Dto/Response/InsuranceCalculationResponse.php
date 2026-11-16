<?php

declare(strict_types=1);

namespace App\Dto\Response;

/**
 * DTO для ответа с результатом расчета.
 * Статический фабричный метод округляет значения,
 * чтобы гарантировать корректный формат данных при сериализации в JSON.
 */
final readonly class InsuranceCalculationResponse
{
    public function __construct(
        public float $totalCostInCurrency,
        public float $totalCostInRubles,
        public int $tripDays,
        public float $dailyCoefficient,
        public float $currencyRate,
        public int $insuranceSum,
    ) {
    }

    /**
     * Фабричный метод, который гарантирует правильное округление
     * согласно требованиям ТЗ перед созданием объекта.
     */
    public static function fromCalculation(
        float $totalCostInCurrency,
        float $totalCostInRubles,
        int $tripDays,
        float $dailyCoefficient,
        float $currencyRate,
        int $insuranceSum,
    ): self {
        return new self(
            totalCostInCurrency: round($totalCostInCurrency, 2, PHP_ROUND_HALF_UP),
            totalCostInRubles: round($totalCostInRubles, 2, PHP_ROUND_HALF_UP),
            tripDays: $tripDays,
            dailyCoefficient: round($dailyCoefficient, 2, PHP_ROUND_HALF_UP),
            currencyRate: round($currencyRate, 4, PHP_ROUND_HALF_UP), // Согласно ТЗ: округление до 4 знаков
            insuranceSum: $insuranceSum
        );
    }
}
