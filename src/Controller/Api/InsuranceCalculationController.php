<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\InsuranceCalculationRequest;
use App\Infrastructure\ExchangeRate\ExchangeRateException;
use App\Service\InsurancePremiumCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

final class InsuranceCalculationController extends AbstractController
{
    #[Route('/api/insurance/calculate', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        InsuranceCalculationRequest $request,
        InsurancePremiumCalculator $calculator,
    ): JsonResponse {
        try {
            $response = $calculator->calculate($request);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        } catch (ExchangeRateException $e) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'Ошибка получения курса валюты: '.$e->getMessage(), $e);
        }

        return $this->json($response);
    }
}

