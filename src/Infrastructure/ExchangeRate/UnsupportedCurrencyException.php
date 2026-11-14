<?php

declare(strict_types=1);

namespace App\Infrastructure\ExchangeRate;

/**
 * Выбрасываем это исключени, когда запрошенная валюта не поддерживается провайдером.
 */
class UnsupportedCurrencyException extends ExchangeRateException
{
    public static function forCurrency(string $currencyCode): self
    {
        return new self(sprintf('Валюта "%s" не поддерживается провайдером', $currencyCode));
    }
}
