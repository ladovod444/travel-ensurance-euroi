<?php

declare(strict_types=1);

namespace App\Infrastructure\ExchangeRate;

interface ExchangeRateProviderInterface
{
    /**
     * Возвращает курс указанной валюты к рублю на заданную дату.
     *
     * @param string                  $currencyCode ISO 4217 код валюты (например, EUR, USD)
     * @param \DateTimeInterface|null $date         Дата, на которую нужен курс. null = текущий день
     *
     * @return float Курс валюты (сколько рублей за 1 единицу валюты)
     *
     * @throws ExchangeRateException        Если не удалось получить курс
     * @throws UnsupportedCurrencyException Если валюта не поддерживается провайдером
     */
    public function getRate(string $currencyCode, ?\DateTimeInterface $date = null): float;
}
