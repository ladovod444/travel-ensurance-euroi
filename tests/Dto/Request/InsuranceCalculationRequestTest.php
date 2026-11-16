<?php

declare(strict_types=1);

namespace App\Tests\Dto\Request;

use App\Dto\Request\InsuranceCalculationRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class InsuranceCalculationRequestTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * Проверяем, что запрос с корректными данными проходит валидацию без ошибок.
     */
    public function testValidRequest(): void
    {
        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-16',
            endDate: '2026-11-19',
            currencyCode: 'EUR'
        );

        $errors = $this->validator->validate($request);
        $this->assertCount(0, $errors);
    }

    /**
     * Проверяем, что поездка на один день (дата начала совпадает с датой окончания) считается валидной.
     */
    public function testSameDayIsValid(): void
    {
        $request = new InsuranceCalculationRequest(
            insuranceSum: 50000,
            startDate: '2026-11-16',
            endDate: '2026-11-16',
            currencyCode: 'USD'
        );

        $errors = $this->validator->validate($request);
        $this->assertCount(0, $errors);
    }

    /**
     * Проверяем ошибку валидации, если дата начала поездки позже даты окончания.
     */
    public function testStartDateAfterEndDateFails(): void
    {
        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-23',
            endDate: '2026-11-16',
            currencyCode: 'EUR'
        );

        $errors = $this->validator->validate($request);
        $this->assertCount(1, $errors);
        $this->assertEquals('Дата начала не может быть позже даты окончания', $errors[0]->getMessage());
    }

    /**
     * Проверяем ошибку валидации, если код валюты передан в нижнем регистре.
     */
    public function testInvalidCurrencyCodeFormatFails(): void
    {
        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-16',
            endDate: '2026-11-19',
            currencyCode: 'eur' // должно быть заглавными
        );

        $errors = $this->validator->validate($request);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('заглавные', $errors[0]->getMessage());
    }
}
