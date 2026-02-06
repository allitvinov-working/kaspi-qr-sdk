<?php

namespace KaspiQrSdk\Request;

use KaspiQrSdk\KaspiScheme;
use KaspiQrSdk\Response\CancelResponse;
use KaspiQrSdk\Response\InvoiceResponse;
use KaspiQrSdk\Response\PaymentInfoResponse;
use KaspiQrSdk\Response\RefundResponse;
use GuzzleHttp\Psr7\Request;

final class Merchant extends AbstractRequest
{
    /**
     * Создание QR-токена или ссылки на оплату
     *
     * @param float $amount Сумма покупки
     * @param string|null $externalId Идентификатор покупки в системе Партнера
     * @param bool $isMobile true для создания ссылки, false для QR-токена
     */
    public function create(float $amount, ?string $externalId = null, bool $isMobile = false): InvoiceResponse
    {
        $data = [
            'DeviceToken' => $this->deviceToken,
            'Amount' => $amount,
        ];

        if ($externalId !== null) {
            $data['ExternalId'] = $externalId;
        }

        if ($this->debugMode) {
            $endpoint = $isMobile ? 'qr/create-link' : 'qr/create';
            $this->logger->debug("Request ({$endpoint})", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl($isMobile ? 'qr/create-link' : 'qr/create'),
                ['Content-type' => 'application/json'],
                json_encode($this->getPrepareData($data))
            )
        );

        if ($this->debugMode) {
            $this->logger->debug("Response (qr/create)", $httpResponse);
        }

        return InvoiceResponse::fromResponse($httpResponse);
    }

    /**
     * Получение статуса операции
     */
    public function getPaymentInfo(int $qrPaymentId): PaymentInfoResponse
    {
        if ($this->debugMode) {
            $this->logger->debug("Request (payment/status)", ['qrPaymentId' => $qrPaymentId]);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'GET',
                $this->getBaseUrl('payment/status/' . $qrPaymentId),
                ['Content-type' => 'application/json']
            )
        );

        if ($this->debugMode) {
            $this->logger->debug("Response (payment/status)", $httpResponse);
        }

        return PaymentInfoResponse::fromResponse($httpResponse);
    }

    /**
     * Получение деталей операции (для схем 2 и 3)
     */
    public function getPaymentDetails(int $qrPaymentId): array
    {
        $url = sprintf(
            'payment/details?QrPaymentId=%d&DeviceToken=%s',
            $qrPaymentId,
            $this->deviceToken
        );

        if ($this->debugMode) {
            $this->logger->debug("Request (payment/details)", [
                'qrPaymentId' => $qrPaymentId,
                'deviceToken' => $this->deviceToken
            ]);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'GET',
                $this->getBaseUrl($url),
                ['Content-type' => 'application/json']
            )
        );

        if ($this->debugMode) {
            $this->logger->debug("Response (payment/details)", $httpResponse);
        }

        return $httpResponse['Data'];
    }

    /**
     * Возврат покупки (полный или частичный)
     *
     * Для схемы 2 требуется QrReturnId (возврат с участием покупателя)
     * Для схемы 3 QrReturnId не требуется (возврат без участия покупателя)
     */
    public function refund(int $qrPaymentId, float $amount, ?int $qrReturnId = null): RefundResponse
    {
        // Для схемы EASY возврат через API невозможен
        if ($this->scheme === KaspiScheme::EASY) {
            throw new KaspiSdkException(
                'Refund is not available via API for EASY scheme. Use Kaspi Pay app instead.'
            );
        }

        $data = [
            'DeviceToken' => $this->deviceToken,
            'QrPaymentId' => $qrPaymentId,
            'Amount' => $amount,
        ];

        // Для схемы STANDARD требуется QrReturnId (возврат с участием покупателя)
        if ($this->scheme === KaspiScheme::STANDARD) {
            if ($qrReturnId === null) {
                throw new KaspiSdkException(
                    'QrReturnId is required for STANDARD scheme refund'
                );
            }
            $data['QrReturnId'] = $qrReturnId;
        }

        // Для схемы STRONG добавляем OrganizationBin (возврат без участия покупателя)
        if ($this->scheme === KaspiScheme::STRONG) {
            if (is_null($this->organizationBin)) {
                throw new KaspiSdkException(
                    'OrganizationBin is required for STRONG scheme'
                );
            }
            $data['OrganizationBin'] = $this->organizationBin;
        }

        if ($this->debugMode) {
            $this->logger->debug("Request (payment/return)", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl('payment/return'),
                ['Content-type' => 'application/json'],
                json_encode($data)
            )
        );

        if ($this->debugMode) {
            $this->logger->debug("Response (payment/return)", $httpResponse);
        }

        return RefundResponse::fromResponse($httpResponse);
    }

    /**
     * Отмена счёта на удалённую оплату (только для схемы 3)
     */
    public function cancelRemotePayment(int $qrPaymentId): CancelResponse
    {
        if ($this->scheme !== KaspiScheme::STRONG) {
            throw new \KaspiQrSdk\Exception\KaspiSdkException(
                'Remote payment cancellation is only available for STRONG scheme'
            );
        }

        $data = [
            'DeviceToken' => $this->deviceToken,
            'QrPaymentId' => $qrPaymentId,
            'OrganizationBin' => $this->organizationBin,
        ];

        if ($this->debugMode) {
            $this->logger->debug("Request (remote/cancel)", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl('remote/cancel'),
                ['Content-type' => 'application/json'],
                json_encode($data)
            )
        );

        if ($this->debugMode) {
            $this->logger->debug("Response (remote/cancel)", $httpResponse);
        }

        return CancelResponse::fromResponse($httpResponse);
    }
}