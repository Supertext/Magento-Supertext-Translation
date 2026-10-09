<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Translation;

use Supertext\Translation\Api\SupertextClient;
use Supertext\Translation\Api\SupertextException;
use Supertext\Translation\Model\Config;

/** One entry point for the translate page, its endpoint and the console command. */
class Translator
{
    private ?SupertextClient $client = null;

    public function __construct(
        private readonly Config $config,
        private readonly EavTranslator $eav,
        private readonly CmsTranslator $cms,
    ) {
    }

    public function exists(string $type, int $id): bool
    {
        return $this->handler($type)->exists($type, $id);
    }

    public function title(string $type, int $id): string
    {
        return $this->handler($type)->title($type, $id);
    }

    /** @param list<int> $storeIds @return array<int, string> */
    public function states(string $type, int $id, array $storeIds): array
    {
        return $this->handler($type)->states($type, $id, $storeIds);
    }

    /**
     * @return array{status: string, title: string, translated: list<string>, kept: list<string>, notes: list<string>}
     *               status: "translated", "created" (new CMS copy) or "unchanged"
     *
     * @throws SupertextException|\Magento\Framework\Exception\LocalizedException
     */
    public function translate(string $type, int $id, int $storeId, bool $overwrite): array
    {
        if ($this->config->apiKey() === '') {
            throw new SupertextException(
                'Supertext is not set up yet: an administrator needs to enter the API key in Stores → Configuration → Supertext → Translation. No Supertext account yet? Create one at %1. Generate your API key at %2 (requires the Admin role).',
                [Config::SIGNUP_URL, Config::API_KEY_URL],
            );
        }

        if ($storeId <= 0) {
            throw new SupertextException('Choose a store view to translate into.');
        }

        $this->client ??= $this->config->client();

        return $this->handler($type)->translate($this->client, $type, $id, $storeId, $overwrite);
    }

    private function handler(string $type): EavTranslator|CmsTranslator
    {
        return EntityTypes::get($type)['kind'] === 'cms' ? $this->cms : $this->eav;
    }
}
