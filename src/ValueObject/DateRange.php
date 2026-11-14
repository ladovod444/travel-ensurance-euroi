<?php

declare(strict_types=1);

namespace App\ValueObject;

final class DateRange
{
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;
    private int $days;

    public function __construct(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Дата начала не может быть позже даты окончания');
        }

        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->days = $this->calculateDays();
    }

    private function calculateDays(): int
    {
        $diff = $this->startDate->diff($this->endDate);

        return $diff->days + 1; // Включаем оба дня
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getDays(): int
    {
        return $this->days;
    }
}
