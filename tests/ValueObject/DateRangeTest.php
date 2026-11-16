<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\DateRange;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    /**
     * Проверяем создание корректного диапазона дат и подсчет количества дней (включая оба граничных дня).
     */
    public function testValidDateRange(): void
    {
        $start = new \DateTimeImmutable('2026-11-18');
        $end = new \DateTimeImmutable('2026-11-20');

        $range = new DateRange($start, $end);

        $this->assertEquals(3, $range->getDays());
        $this->assertEquals($start, $range->getStartDate());
        $this->assertEquals($end, $range->getEndDate());
    }

    /**
     * Проверяем, что диапазон из одного дня (дата начала совпадает с датой окончания) равен 1 дн.
     */
    public function testSameDayRange(): void
    {
        $date = new \DateTimeImmutable('2026-11-17');

        $range = new DateRange($date, $date);

        $this->assertEquals(1, $range->getDays());
    }

    /**
     * Проверяем выброс исключения, если дата начала диапазона указана позже даты окончания.
     */
    public function testInvalidDateRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Дата начала не может быть позже даты окончания/');

        $start = new \DateTimeImmutable('2026-11-15');
        $end = new \DateTimeImmutable('2026-11-10');

        new DateRange($start, $end);
    }
}
