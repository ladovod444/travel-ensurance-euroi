<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class ApiExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Обрабатываем только API-запросы
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        // Сначала извлекаем ValidationFailedException
        $validation = $this->extractValidationFailed($exception);

        // Определяем HTTP-статус: берем из исключения или ставим 500 по умолчанию
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $logLevel = $statusCode >= 500 ? LogLevel::ERROR : LogLevel::WARNING;

        // Записываем ошибки в лог
        $this->logger->log($logLevel, 'API Exception', [
            'path'        => $request->getPathInfo(),
            'method'      => $request->getMethod(),
            'status'      => $statusCode,
            'exception'   => $exception::class,
            'message'     => $exception->getMessage(),
            'stack_trace' => $statusCode >= 500 ? $exception->getTraceAsString() : null, // Скрываем трейс для 4xx
        ]);

        // Формируем код и сообщение ошибки
        [$finalStatusCode, $message] = match (true) {
            null !== $validation => [
                // Берем статус из обертки (обычно 422), или 400 как фоллбэк
                $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_BAD_REQUEST,
                'Ошибки валидации',
            ],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                $exception->getMessage() ?: 'Ошибка запроса',
            ],
            default => [
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Внутренняя ошибка сервера',
            ],
        };

        // Формируем базовый ответ
        $data = [
            'error' => $message,
        ];

        // Если есть нарушения валидации, добавляем детали
        if (null !== $validation) {
            $violations = [];
            foreach ($validation->getViolations() as $v) {
                // Т.к. может быть несколько ошибок формируем массив
                $violations[$v->getPropertyPath()][] = $v->getMessage();
            }
            $data['violations'] = $violations;
        }

        $response = new JsonResponse($data, $finalStatusCode);
        $event->setResponse($response);
    }

    private function extractValidationFailed(\Throwable $e): ?ValidationFailedException
    {
        if ($e instanceof ValidationFailedException) {
            return $e;
        }

        $previous = $e->getPrevious();

        return $previous instanceof ValidationFailedException ? $previous : null;
    }
}
