<?php

declare(strict_types=1);

namespace App\Infrastructure\ExchangeRate;

/**
 * Базовое исключение для ошибок получения курса валют.
 */
class ExchangeRateException extends \RuntimeException
{
}
