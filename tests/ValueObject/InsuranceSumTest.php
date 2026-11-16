<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\InsuranceSum;
use PHPUnit\Framework\TestCase;

class InsuranceSumTest extends TestCase
{
    public function testValidInsuranceSum30000(): void
    {
        $sum = new InsuranceSum(30000);

        $this->assertEquals(30000, $sum->getAmount());
        $this->assertEquals(0.6, $sum->getCoefficient());
    }

    public function testValidInsuranceSum50000(): void
    {
        $sum = new InsuranceSum(50000);

        $this->assertEquals(50000, $sum->getAmount());
        $this->assertEquals(0.8, $sum->getCoefficient());
    }

    public function testInvalidInsuranceSum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Страховая сумма 40000 не поддерживается/');

        new InsuranceSum(40000);
    }
}
