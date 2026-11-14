<?php

declare(strict_types=1);

namespace App\ValueObject;

final class InsuranceSum
{
    private const array ALLOWED_SUMS = [
        30000 => 0.6,
        50000 => 0.8,
    ];

    private int $amount;
    private float $coefficient;

    public function __construct(int $amount)
    {
        if (!isset(self::ALLOWED_SUMS[$amount])) {
            throw new \InvalidArgumentException(sprintf('Страховая сумма %d не поддерживается. Допустимые значения: %s', $amount, implode(', ', array_keys(self::ALLOWED_SUMS))));
        }

        $this->amount = $amount;
        $this->coefficient = self::ALLOWED_SUMS[$amount];
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCoefficient(): float
    {
        return $this->coefficient;
    }
}
