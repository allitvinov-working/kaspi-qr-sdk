<?php
namespace KaspiQrSdk;

use Psr\Log\LoggerInterface;

class Config
{
    private ?string $organizationBin = null;
    private ?string $deviceToken = null;
    private string $scheme;
    private string $apiVersion;
    private ?string $apiKey = null;
    private string $baseDomain;
    private ?string $caPath = null;
    private ?string $certPath = null;
    private ?string $keyPath = null;
    private ?string $keyPass = null;
    private ?LoggerInterface $logger = null;
    private bool $testMode = false;
    private const SCHEME_PORTS = [
        KaspiScheme::EASY     => 8543,
        KaspiScheme::STANDARD => 8544,
        KaspiScheme::STRONG   => 8545,
    ];

    public function __construct(string $scheme, string $baseDomain, string $apiVersion = 'v01')
    {
        $this->scheme = $scheme;
        $this->baseDomain = $baseDomain;
        $this->apiVersion = $apiVersion;
    }

    public function getPort(): int
    {
        return self::SCHEME_PORTS[$this->scheme] ?? 8543;
    }

    /**
     * Создание конфига для схемы EASY (r1)
     */
    public static function createEasy(string $apiKey, string $baseDomain): self
    {
        $config = new self(KaspiScheme::EASY, $baseDomain);
        $config->setApiKey($apiKey);
        return $config;
    }

    /**
     * Создание конфига для схемы STANDARD (r2)
     */
    public static function createStandard(
        string $baseDomain,
        string $certPath,
        string $keyPath,
        string $keyPass,
        ?string $caPath = null
    ): self {
        $config = new self(KaspiScheme::STANDARD, $baseDomain);
        $config->setCertPath($certPath);
        $config->setKeyPath($keyPath);
        $config->setKeyPass($keyPass);
        $config->setCaPath($caPath);
        return $config;
    }

    /**
     * Создание конфига для схемы STRONG (r3)
     */
    public static function createStrong(
        string $organizationBin,
        string $baseDomain,
        string $certPath,
        string $keyPath,
        string $keyPass,
        ?string $caPath = null
    ): self {
        $config = new self(KaspiScheme::STRONG, $baseDomain);
        $config->setOrganizationBin($organizationBin);
        $config->setCertPath($certPath);
        $config->setKeyPath($keyPath);
        $config->setKeyPass($keyPass);
        $config->setCaPath($caPath);
        return $config;
    }

    public function getOrganizationBin(): ?string
    {
        return $this->organizationBin;
    }

    public function setOrganizationBin(?string $v): self
    {
        $this->organizationBin = $v;
        return $this;
    }

    public function getDeviceToken(): ?string
    {
        return $this->deviceToken;
    }

    public function setDeviceToken(?string $v): self
    {
        $this->deviceToken = $v;
        return $this;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $v): self
    {
        $this->apiKey = $v;
        return $this;
    }

    public function getBaseDomain(): string
    {
        return $this->baseDomain;
    }

    public function getCaPath(): ?string
    {
        return $this->caPath;
    }

    public function setCaPath(?string $v): self
    {
        $this->caPath = $v;
        return $this;
    }

    public function getCertPath(): ?string
    {
        return $this->certPath;
    }

    public function setCertPath(?string $v): self
    {
        $this->certPath = $v;
        return $this;
    }

    public function getKeyPath(): ?string
    {
        return $this->keyPath;
    }

    public function setKeyPath(?string $v): self
    {
        $this->keyPath = $v;
        return $this;
    }

    public function getKeyPass(): ?string
    {
        return $this->keyPass;
    }

    public function setKeyPass(?string $v): self
    {
        $this->keyPass = $v;
        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $v): self
    {
        $this->logger = $v;
        return $this;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function setTestMode(bool $v): self
    {
        $this->testMode = $v;
        return $this;
    }
}