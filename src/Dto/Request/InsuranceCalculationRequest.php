<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DTO для входящего запроса расчета стоимости страховки.
 */
final readonly class InsuranceCalculationRequest
{
    public function __construct(
        #[Assert\Positive(message: 'Страховая сумма должна быть положительным числом')]
        public int $insuranceSum,
        #[Assert\NotBlank(message: 'Дата начала поездки обязательна')]
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'Дата начала должна быть в формате YYYY-MM-DD'
        )]
        public string $startDate,
        #[Assert\NotBlank(message: 'Дата окончания поездки обязательна')]
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'Дата окончания должна быть в формате YYYY-MM-DD'
        )]
        public string $endDate,
        #[Assert\NotBlank(message: 'Код валюты обязателен')]
        #[Assert\Length(
            min: 3,
            max: 3,
            exactMessage: 'Код валюты должен состоять ровно из 3 символов'
        )]
        #[Assert\Regex(
            pattern: '/^[A-Z]{3}$/',
            message: 'Код валюты должен содержать только 3 заглавные латинские буквы (например, EUR, USD)'
        )]
        public string $currencyCode,
    ) {
    }

    /**
     * Кросс-полевая валидация: дата начала не должна быть позже даты окончания.
     * Этот метод вызывается автоматически Symfony Validator благодаря атрибуту #[Assert\Callback].
     */
    #[Assert\Callback]
    public function validateDatesOrder(ExecutionContextInterface $context): void
    {
        // Проверяем, что обе даты валидны (прошли Regex-валидацию)
        if (!$this->isValidDateString($this->startDate) || !$this->isValidDateString($this->endDate)) {
            return;
        }

        $start = new \DateTimeImmutable($this->startDate);
        $end = new \DateTimeImmutable($this->endDate);

        if ($start > $end) {
            $context->buildViolation('Дата начала не может быть позже даты окончания')
                ->atPath('startDate')
                ->addViolation();
        }
    }

    /**
     * Вспомогательный метод для преобразования строки в DateTimeImmutable.
     */
    public function getStartDateObject(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->startDate);
    }

    public function getEndDateObject(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->endDate);
    }

    private function isValidDateString(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        // Дополнительно проверяем, что дата реально существует (например, 2026-02-30 невалидна)
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }
}
