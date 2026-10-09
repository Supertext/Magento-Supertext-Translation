<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Translation;

/**
 * What can be translated, and which fields. Keep "What is translated" in docs/DEVELOPER.md
 * and docs/USER_GUIDE.md in sync with this list.
 *
 * Products and categories (kind "eav") get store-view values of their store-scoped
 * attributes. CMS pages and blocks (kind "cms") have no store-view values: a translation
 * is a copy with the same identifier, assigned to the target store view.
 */
final class EntityTypes
{
    public const TYPES = [
        'product' => [
            'kind'     => 'eav',
            'acl'      => 'Magento_Catalog::products',
            'label'    => 'Products',
            'title'    => 'name',
            'fields'   => [
                'name'              => ['html' => false, 'size' => 255],
                'short_description' => ['html' => true, 'size' => 0],
                'description'       => ['html' => true, 'size' => 0],
                'meta_title'        => ['html' => false, 'size' => 255],
                'meta_keyword'      => ['html' => false, 'size' => 0],
                'meta_description'  => ['html' => false, 'size' => 255],
            ],
            'urlKey'   => 'name',
            'edit'     => ['catalog/product/edit', 'id'],
        ],
        'category' => [
            'kind'     => 'eav',
            'acl'      => 'Magento_Catalog::categories',
            'label'    => 'Categories',
            'title'    => 'name',
            'fields'   => [
                'name'             => ['html' => false, 'size' => 255],
                'description'      => ['html' => true, 'size' => 0],
                'meta_title'       => ['html' => false, 'size' => 255],
                'meta_keywords'    => ['html' => false, 'size' => 0],
                'meta_description' => ['html' => false, 'size' => 0],
            ],
            'urlKey'   => 'name',
            'edit'     => ['catalog/category/edit', 'id'],
        ],
        'cms_page' => [
            'kind'     => 'cms',
            'acl'      => 'Magento_Cms::save',
            'label'    => 'Pages',
            'title'    => 'title',
            'fields'   => [
                'title'            => ['html' => false, 'size' => 255],
                'content_heading'  => ['html' => false, 'size' => 255],
                'content'          => ['html' => true, 'size' => 0],
                'meta_title'       => ['html' => false, 'size' => 255],
                'meta_keywords'    => ['html' => false, 'size' => 0],
                'meta_description' => ['html' => false, 'size' => 0],
            ],
            'urlKey'   => null,
            'edit'     => ['cms/page/edit', 'page_id'],
        ],
        'cms_block' => [
            'kind'     => 'cms',
            'acl'      => 'Magento_Cms::block',
            'label'    => 'Blocks',
            'title'    => 'title',
            'fields'   => [
                'title'   => ['html' => false, 'size' => 255],
                'content' => ['html' => true, 'size' => 0],
            ],
            'urlKey'   => null,
            'edit'     => ['cms/block/edit', 'block_id'],
        ],
    ];

    /** @return array<string, mixed> */
    public static function get(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown content type "%s".', $type));
        }

        return self::TYPES[$type];
    }

    /** "product_listing" (the grid a mass action came from) → "product" */
    public static function fromListing(string $namespace): ?string
    {
        return [
            'product_listing'   => 'product',
            'cms_page_listing'  => 'cms_page',
            'cms_block_listing' => 'cms_block',
        ][$namespace] ?? null;
    }

    /**
     * @param mixed $ids
     *
     * @return list<int>
     */
    public static function ids($ids): array
    {
        $ids = \is_array($ids) ? $ids : explode(',', (string) $ids);

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /** "de_CH" or "de-ch" → "de-CH" */
    public static function formatTag(string $tag): string
    {
        $parts = preg_split('/[-_]/', trim($tag)) ?: [];
        $out   = [strtolower((string) array_shift($parts))];

        foreach ($parts as $part) {
            $out[] = \strlen($part) === 2 ? strtoupper($part) : ucfirst(strtolower($part));
        }

        return implode('-', array_filter($out, static fn (string $part): bool => $part !== ''));
    }
}
