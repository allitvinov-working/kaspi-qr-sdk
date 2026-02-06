<?php
namespace KaspiQrSdk;

use GuzzleHttp\Client;
use KaspiQrSdk\Request\Merchant;
use KaspiQrSdk\Request\Partner;
use KaspiQrSdk\Request\Emulator;

final class KaspiQrClient
{
    public Partner $partner;
    public Merchant $merchant;
    public Emulator $emulator;

    private Config $config;
    private string $version = 'v01';

    private array $schemeOptions = [
        KaspiScheme::EASY     => ['port' => 8543],
        KaspiScheme::STANDARD => ['port' => 8544],
        KaspiScheme::STRONG   => ['port' => 8545],
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->validateConfig();

        $scheme = $config->getScheme();
        if (!isset($this->schemeOptions[$scheme])) {
            throw new \InvalidArgumentException("Invalid scheme: {$scheme}");
        }

        $port = $this->schemeOptions[$scheme]['port'];
        $baseUrl = $this->collectUrl($config->getBaseDomain(), $port, $scheme);

        $clientOptions = array_merge(
            ['base_uri' => $baseUrl],
            $this->getHeaders($config),
            $this->getSslOptions($config)
        );

        $client = new Client($clientOptions);

        $this->partner = new Partner(
            $client,
            $baseUrl,
            $scheme,
            $config->getOrganizationBin(),
            $config->getDeviceToken(),
            $config->getApiKey(),
            $config->getLogger()
        );

        $this->merchant = new Merchant(
            $client,
            $baseUrl,
            $scheme,
            $config->getOrganizationBin(),
            $config->getDeviceToken(),
            $config->getApiKey(),
            $config->getLogger()
        );

        $this->emulator = new Emulator(
            $client,
            $baseUrl,
            $scheme,
            $config->getOrganizationBin(),
            $config->getDeviceToken(),
            $config->getApiKey(),
            $config->getLogger()
        );
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

    private function collectUrl(string $baseDomain, int $port, string $scheme): string
    {
        return sprintf('%s:%d/%s/%s', $baseDomain, $port, $scheme, $this->version);
    }

    /**
     * Обновить deviceToken после регистрации устройства
     */
    public function setDeviceToken(string $deviceToken): void
    {
        $this->config->setDeviceToken($deviceToken);
        // Пересоздаём merchant с новым токеном
        // ... или добавить метод setDeviceToken в Merchant
    }
}