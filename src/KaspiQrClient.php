<?php
namespace KaspiQrSdk;

use GuzzleHttp\Client;
use KaspiQrSdk\Request\Merchant;
use KaspiQrSdk\Request\Partner;
use KaspiQrSdk\Request\Emulator;
use KaspiQrSdk\Exception\KaspiSdkException;

final class KaspiQrClient
{
    public Partner $partner;
    public Merchant $merchant;

    private Config $config;
    private Client $httpClient;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->validateConfig();

        $clientOptions = array_merge(
            $this->getSslOptions($config),
            $this->getHeaders($config)
        );

        $this->httpClient = new Client($clientOptions);

        // Создаем объекты запросов, передавая только клиент и конфиг
        $this->partner = new Partner($this->httpClient, $config);
        $this->merchant = new Merchant($this->httpClient, $config);
    }

    private function validateConfig(): void
    {
        $scheme = $this->config->getScheme();

        // Для EASY требуется ApiKey
        if ($scheme === KaspiScheme::EASY) {
            if (empty($this->config->getApiKey())) {
                throw new \InvalidArgumentException('ApiKey is required for EASY scheme');
            }
        }

        // Для STANDARD и STRONG требуются сертификаты
        if ($scheme === KaspiScheme::STANDARD || $scheme === KaspiScheme::STRONG) {
            if (empty($this->config->getCertPath()) || empty($this->config->getKeyPath())) {
                throw new \InvalidArgumentException('Certificate and key are required for STANDARD/STRONG schemes');
            }
        }

        // Для STRONG требуется OrganizationBin
        if ($scheme === KaspiScheme::STRONG) {
            if (empty($this->config->getOrganizationBin())) {
                throw new \InvalidArgumentException('OrganizationBin is required for STRONG scheme');
            }
        }
    }

    private function getHeaders(Config $config): array
    {
        $headers = [
            'headers' => [
                'X-Request-ID' => $this->generateRequestId(),
                'Content-Type' => 'application/json',
            ]
        ];

        // Api-Key только для схемы EASY
        if ($config->getScheme() === KaspiScheme::EASY && $config->getApiKey()) {
            $headers['headers']['Api-Key'] = $config->getApiKey();
        }

        return $headers;
    }

    private function getSslOptions(Config $config): array
    {
        $scheme = $config->getScheme();

        // Для EASY сертификаты не нужны
        if ($scheme === KaspiScheme::EASY) {
            return $config->isTestMode() ? ['verify' => false] : [];
        }

        // Для STANDARD и STRONG нужны клиентские сертификаты
        $options = [
            'timeout' => 10,
            'cert' => [$config->getCertPath(), $config->getKeyPass()],
            'ssl_key' => [$config->getKeyPath(), $config->getKeyPass()],
        ];

        if ($config->isTestMode()) {
            $options['verify'] = false;
        } elseif ($config->getCaPath()) {
            $options['verify'] = $config->getCaPath();
        }

        return $options;
    }

    private function generateRequestId(): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }

    /**
     * Обновить deviceToken после регистрации устройства
     */
    public function setDeviceToken(string $deviceToken): void
    {
        $this->config->setDeviceToken($deviceToken);
        // Нет необходимости пересоздавать merchant, так как он использует конфиг
    }

    /**
     * Получить текущий конфиг
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Получить HTTP клиент (может быть полезно для расширенного использования)
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }
}