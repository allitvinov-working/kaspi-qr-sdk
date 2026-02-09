<?php

namespace KaspiQrSdk\Request;

use KaspiQrSdk\KaspiScheme;
use KaspiQrSdk\Exception\KaspiSdkException;
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
        $deviceToken = $this->getDeviceToken();
        if (!$deviceToken) {
            throw new KaspiSdkException('DeviceToken is required for creating QR code');
        }

        $data = [
            'DeviceToken' => $deviceToken,
            'Amount' => $amount,
        ];

        if ($externalId !== null) {
            $data['ExternalId'] = $externalId;
        }

        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $endpoint = $isMobile ? 'qr/create-link' : 'qr/create';
            $logger->debug("Request ({$endpoint})", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl($isMobile ? 'qr/create-link' : 'qr/create'),
                ['Content-type' => 'application/json'],
                json_encode($this->getPrepareData($data))
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (qr/create)", $httpResponse);
        }

        return InvoiceResponse::fromResponse($httpResponse);
    }

    /**
     * Получение статуса операции
     */
    public function getPaymentInfo(int $qrPaymentId): PaymentInfoResponse
    {
        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $logger->debug("Request (payment/status)", ['qrPaymentId' => $qrPaymentId]);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'GET',
                $this->getBaseUrl('payment/status/' . $qrPaymentId),
                ['Content-type' => 'application/json']
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (payment/status)", $httpResponse);
        }

        return PaymentInfoResponse::fromResponse($httpResponse);
    }

    /**
     * Получение деталей операции (для схем 2 и 3)
     */
    public function getPaymentDetails(int $qrPaymentId): array
    {
        $deviceToken = $this->getDeviceToken();
        if (!$deviceToken) {
            throw new KaspiSdkException('DeviceToken is required for getting payment details');
        }

        $url = sprintf(
            'payment/details?QrPaymentId=%d&DeviceToken=%s',
            $qrPaymentId,
            $deviceToken
        );

        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $logger->debug("Request (payment/details)", [
                'qrPaymentId' => $qrPaymentId,
                'deviceToken' => $deviceToken
            ]);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'GET',
                $this->getBaseUrl($url),
                ['Content-type' => 'application/json']
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (payment/details)", $httpResponse);
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
        $scheme = $this->getScheme();

        // Для схемы EASY возврат через API невозможен
        if ($scheme === KaspiScheme::EASY) {
            throw new KaspiSdkException(
                'Refund is not available via API for EASY scheme. Use Kaspi Pay app instead.'
            );
        }

        $deviceToken = $this->getDeviceToken();
        if (!$deviceToken) {
            throw new KaspiSdkException('DeviceToken is required for refund');
        }

        $data = [
            'DeviceToken' => $deviceToken,
            'QrPaymentId' => $qrPaymentId,
            'Amount' => $amount,
        ];

        // Для схемы STANDARD требуется QrReturnId (возврат с участием покупателя)
        if ($scheme === KaspiScheme::STANDARD) {
            if ($qrReturnId === null) {
                throw new KaspiSdkException(
                    'QrReturnId is required for STANDARD scheme refund'
                );
            }
            $data['QrReturnId'] = $qrReturnId;
        }

        // Для схемы STRONG добавляем OrganizationBin (возврат без участия покупателя)
        if ($scheme === KaspiScheme::STRONG) {
            $organizationBin = $this->getOrganizationBin();
            if (is_null($organizationBin)) {
                throw new KaspiSdkException(
                    'OrganizationBin is required for STRONG scheme'
                );
            }
            $data['OrganizationBin'] = $organizationBin;
        }

        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $logger->debug("Request (payment/return)", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl('payment/return'),
                ['Content-type' => 'application/json'],
                json_encode($data)
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (payment/return)", $httpResponse);
        }

        return RefundResponse::fromResponse($httpResponse);
    }

    /**
     * Отмена счёта на удалённую оплату (только для схемы 3)
     */
    public function cancelRemotePayment(int $qrPaymentId): CancelResponse
    {
        $scheme = $this->getScheme();
        if ($scheme !== KaspiScheme::STRONG) {
            throw new KaspiSdkException(
                'Remote payment cancellation is only available for STRONG scheme'
            );
        }

        $deviceToken = $this->getDeviceToken();
        if (!$deviceToken) {
            throw new KaspiSdkException('DeviceToken is required for cancel remote payment');
        }

        $organizationBin = $this->getOrganizationBin();
        if (!$organizationBin) {
            throw new KaspiSdkException('OrganizationBin is required for STRONG scheme');
        }

        $data = [
            'DeviceToken' => $deviceToken,
            'QrPaymentId' => $qrPaymentId,
            'OrganizationBin' => $organizationBin,
        ];

        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $logger->debug("Request (remote/cancel)", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl('remote/cancel'),
                ['Content-type' => 'application/json'],
                json_encode($data)
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (remote/cancel)", $httpResponse);
        }

        return CancelResponse::fromResponse($httpResponse);
    }
}