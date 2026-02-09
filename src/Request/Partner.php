<?php

namespace KaspiQrSdk\Request;

use KaspiQrSdk\KaspiScheme;
use KaspiQrSdk\Exception\KaspiSdkException;
use KaspiQrSdk\Response\DeviceRegisterResponse;
use KaspiQrSdk\Response\TradePointResponse;
use GuzzleHttp\Psr7\Request;

final class Partner extends AbstractRequest
{
    /**
     * Получение списка торговых точек партнера
     * @return array<int, TradePointResponse>
     */
    public function tradePoints(): array
    {
        $url = 'partner/tradepoints';

        // Для STRONG схемы добавляем organizationBin в URL
        if ($this->getScheme() === KaspiScheme::STRONG) {
            $organizationBin = $this->getOrganizationBin();
            if (is_null($organizationBin)) {
                throw new KaspiSdkException(
                    'OrganizationBin is required for STRONG scheme'
                );
            }
            $url .= '/' . $organizationBin;
        }

        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $logger->debug("Request (tradePoints)", ['url' => $url]);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'GET',
                $this->getBaseUrl($url),
                ['Content-type' => 'application/json']
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (tradePoints)", $httpResponse);
        }

        return array_map(
            static fn($item) => TradePointResponse::fromResponse($item),
            $httpResponse['Data']
        );
    }

    /**
     * Регистрация устройства
     */
    public function register(string $deviceId, int $tradePointId): DeviceRegisterResponse
    {
        $data = [
            'DeviceId' => $deviceId,
            'TradePointId' => $tradePointId
        ];

        $logger = $this->getLogger();
        if ($this->isDebugMode() && $logger) {
            $logger->debug("Request (device/register)", $data);
        }

        $httpResponse = $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl('device/register'),
                ['Content-type' => 'application/json'],
                json_encode($this->getPrepareData($data))
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (device/register)", $httpResponse);
        }

        return DeviceRegisterResponse::fromResponse($httpResponse);
    }

    /**
     * Удаление устройства
     */
    public function delete(string $deviceToken): bool
    {
        $data = ['DeviceToken' => $deviceToken];

        // Для STRONG схемы добавляем OrganizationBin
        $scheme = $this->getScheme();
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
            $logger->debug("Request (device/delete)", $data);
        }

        $this->makeRequest(
            new Request(
                'POST',
                $this->getBaseUrl('device/delete'),
                ['Content-type' => 'application/json'],
                json_encode($data)
            )
        );

        if ($this->isDebugMode() && $logger) {
            $logger->debug("Response (device/delete)", ['success' => true]);
        }

        return true;
    }
}