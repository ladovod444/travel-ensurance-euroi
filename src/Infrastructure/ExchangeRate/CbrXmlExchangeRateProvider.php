<?php

declare(strict_types=1);

namespace App\Infrastructure\ExchangeRate;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Получаем данные о курсах валют от ЦБ РФ по XML-API.
 */
final class CbrXmlExchangeRateProvider implements ExchangeRateProviderInterface
{
    private const string BASE_URL = 'https://www.cbr.ru/scripts/XML_daily.asp';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getRate(string $currencyCode, ?\DateTimeInterface $date = null): float
    {
        $url = self::BASE_URL;
        if (null !== $date) {
            $url .= '?date_req='.$date->format('d/m/Y');
        }
        // Логгируем входящие параметры
        $this->logger->info('Запрос курса валюты', [
            'currency' => $currencyCode,
            'date'     => $date?->format('d/m/Y') ?? 'today',
        ]);

        try {
            // Добавляем заголовки, чтобы ЦБ не блокировал запрос как бота
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/xml, text/xml, */*',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                throw new ExchangeRateException(sprintf('Сервер ЦБ РФ вернул некорректный HTTP-статус: %d', $statusCode));
            }

            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new ExchangeRateException('Не удалось получить данные по валютам от ЦБ РФ: '.$e->getMessage(), 0, $e);
        }

        return $this->parseXml($content, $currencyCode);
    }

    private function parseXml(string $xmlContent, string $currencyCode): float
    {
        libxml_clear_errors();
        libxml_use_internal_errors(true);

        // 1. Конвертируем байты в UTF-8, если это не так
        if (false === mb_detect_encoding($xmlContent, 'UTF-8', true)) {
            $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', 'Windows-1251');
        }

        // 2. Заменяем объявление кодировки внутри самого XML.
        // Т.к. simplexml_load_string не сработает корректно, читая UTF-8 как windows-1251.
        $xmlContent = str_ireplace('encoding="windows-1251"', 'encoding="UTF-8"', $xmlContent);

        // 3. Парсим XML
        $xml = simplexml_load_string($xmlContent);

        if (false === $xml) {
            $errors = libxml_get_errors();
            $errorMessages = array_map(fn ($e) => trim($e->message), $errors);
            libxml_clear_errors();

            // Показываем первые 100 символов ответа, чтобы было понятно, что пришло
            $preview = str_replace(["\n", "\r", "\t"], ' ', trim(substr($xmlContent, 0, 100)));

            throw new ExchangeRateException(sprintf('Не удалось разобрать XML-ответ от ЦБ РФ. Ошибки парсинга: %s. Начало ответа: "%s"', implode('; ', $errorMessages), $preview));
        }

        foreach ($xml->Valute as $valute) {
            if ((string) $valute->CharCode === $currencyCode) {
                $value = (float) str_replace(',', '.', (string) $valute->Value);
                $nominal = (int) $valute->Nominal;

                if (0 === $nominal) {
                    throw new ExchangeRateException('Некорректный номинал валюты');
                }

                $rate = $value / $nominal;

                // Логгируем результат
                $this->logger->info('Курс валюты получен', ['rate' => $rate, 'value' => $value, 'nominal' => $nominal]);

                return $value / $nominal;
            }
        }

        throw UnsupportedCurrencyException::forCurrency($currencyCode);
    }
}
