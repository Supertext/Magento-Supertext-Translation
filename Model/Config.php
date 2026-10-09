<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Supertext\Translation\Api\CurlTransport;
use Supertext\Translation\Api\SupertextClient;
use Supertext\Translation\Model\Translation\EntityTypes;

/**
 * The module's settings (Stores → Configuration → Supertext → Translation), with the
 * environment variables SUPERTEXT_API_KEY and SUPERTEXT_API_ENDPOINT taking precedence,
 * and the store views as translation targets.
 */
class Config
{
    public const PATH_API_KEY     = 'supertext/api/api_key';
    public const PATH_ENVIRONMENT = 'supertext/api/environment';
    public const PATH_ENDPOINT    = 'supertext/api/endpoint';
    public const PATH_TIMEOUT     = 'supertext/api/timeout';
    public const PATH_CODE        = 'supertext/language/code';
    public const PATH_TONE        = 'supertext/language/tone';
    public const PATH_CMS_STATUS  = 'supertext/cms/status';

    public const SIGNUP_URL  = 'https://www.supertext.com/person/en/account/signin';
    public const API_KEY_URL = 'https://www.supertext.com/en/integrations/api';
    public const REPOSITORY  = 'https://github.com/Supertext/Magento-Supertext-Translation';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager,
        private readonly PackageInfo $packageInfo,
    ) {
    }

    public function apiKey(): string
    {
        $env = (string) getenv('SUPERTEXT_API_KEY');

        if (trim($env) !== '') {
            return SupertextClient::normalizeKey($env);
        }

        $stored = (string) $this->scopeConfig->getValue(self::PATH_API_KEY);

        return SupertextClient::normalizeKey($stored === '' ? '' : (string) $this->encryptor->decrypt($stored));
    }

    public function apiKeyFromEnvironment(): bool
    {
        return trim((string) getenv('SUPERTEXT_API_KEY')) !== '';
    }

    public function endpointFromEnvironment(): bool
    {
        return trim((string) getenv('SUPERTEXT_API_ENDPOINT')) !== '';
    }

    public function baseUrl(): string
    {
        $env = trim((string) getenv('SUPERTEXT_API_ENDPOINT'));

        return SupertextClient::baseUrlFor(
            (string) $this->scopeConfig->getValue(self::PATH_ENVIRONMENT),
            $env !== '' ? $env : (string) $this->scopeConfig->getValue(self::PATH_ENDPOINT),
        );
    }

    public function timeout(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::PATH_TIMEOUT);

        return $value >= 30 ? min($value, 1800) : 180;
    }

    public function client(): SupertextClient
    {
        return new SupertextClient($this->apiKey(), $this->baseUrl(), new CurlTransport(), $this->timeout());
    }

    /** "same" (as the original) or "disabled" */
    public function cmsStatus(): string
    {
        return $this->scopeConfig->getValue(self::PATH_CMS_STATUS) === 'disabled' ? 'disabled' : 'same';
    }

    /** The store's locale, e.g. "de_CH" (store id 0: the default locale). */
    public function locale(int $storeId): string
    {
        return $storeId === 0
            ? (string) $this->scopeConfig->getValue('general/locale/code')
            : (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Sent to Supertext as target language: the override, else the locale as a tag ("de-CH"). */
    public function targetCode(int $storeId): string
    {
        $override = trim((string) $this->scopeConfig->getValue(self::PATH_CODE, ScopeInterface::SCOPE_STORE, $storeId));

        return $override !== '' ? $override : EntityTypes::formatTag($this->locale($storeId));
    }

    /** Source language: the default locale's language ("en"). */
    public function sourceCode(): string
    {
        return EntityTypes::formatTag($this->locale(0));
    }

    public function tone(int $storeId): string
    {
        $tone = (string) $this->scopeConfig->getValue(self::PATH_TONE, ScopeInterface::SCOPE_STORE, $storeId);

        return \in_array($tone, ['more', 'less'], true) ? $tone : 'default';
    }

    /**
     * Store views that can be translated into, with their language.
     *
     * @return list<array{id: int, name: string, code: string, website: string, locale: string, target: string, active: bool, sameLanguage: bool}>
     */
    public function storeViews(): array
    {
        $source = strtolower(strtok(EntityTypes::formatTag($this->locale(0)), '-') ?: '');
        $rows   = [];

        foreach ($this->storeManager->getStores(false) as $store) {
            $id     = (int) $store->getId();
            $target = $this->targetCode($id);
            $rows[] = [
                'id'           => $id,
                'name'         => (string) $store->getName(),
                'code'         => (string) $store->getCode(),
                'website'      => (string) $this->storeManager->getWebsite($store->getWebsiteId())->getName(),
                'locale'       => $this->locale($id),
                'target'       => $target,
                'active'       => (bool) $store->getIsActive(),
                'sameLanguage' => strtolower(strtok($target, '-') ?: '') === $source,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['website'], $a['id']] <=> [$b['website'], $b['id']]);

        return $rows;
    }

    public function version(): string
    {
        return (string) $this->packageInfo->getVersion('Supertext_Translation');
    }

    public function releaseUrl(): string
    {
        $version = $this->version();

        return preg_match('/^\d+\.\d+\.\d+$/', $version) ? self::REPOSITORY . '/releases/tag/v' . $version : '';
    }
}
