<?php

/**
 * Demo setup, run on every start of the demo container (demo/entrypoint.sh) and in CI:
 *
 *   php demo/setup.php [/path/to/magento]
 *
 * Makes sure the shop has store views for German (Switzerland), French (Switzerland) and
 * Italian (Switzerland) next to English, the sample category, products, CMS page and block
 * exist in English, and the DEMO_ADMIN_* / DEMO_EDITOR_* accounts exist. Never changes what
 * is already there (existing accounts, content and translations stay as they are).
 * Passwords are read from the environment and never printed. Accounts log in with their
 * email address as user name.
 */

declare(strict_types=1);

use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Store\Model\ResourceModel\Store as StoreResource;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;
use Magento\User\Model\UserFactory;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = $argv[1] ?? (getenv('MAGENTO_ROOT') ?: '/var/www/html');
require $root . '/app/bootstrap.php';

$log = static function (string $message): void {
    fwrite(STDOUT, '[supertext-demo] ' . $message . "\n");
};

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om        = $bootstrap->getObjectManager();
$om->get(State::class)->setAreaCode(Area::AREA_ADMINHTML);

$storeManager = $om->get(StoreManagerInterface::class);
$storeManager->setCurrentStore(0);
$config  = $om->get(WriterInterface::class);
$changed = false;

// --- Store views ----------------------------------------------------------------------
$default = $storeManager->getDefaultStoreView();
$views   = [
    // code => [name, locale]
    'de_ch' => ['Deutsch (Schweiz)', 'de_CH'],
    'fr_ch' => ['Français (Suisse)', 'fr_CH'],
    'it_ch' => ['Italiano (Svizzera)', 'it_CH'],
];

foreach ($views as $code => [$name, $locale]) {
    $store = $om->create(StoreFactory::class)->create()->load($code, 'code');

    if ($store->getId()) {
        continue;
    }

    $store->setCode($code)
        ->setName($name)
        ->setWebsiteId((int) $default->getWebsiteId())
        ->setGroupId((int) $default->getStoreGroupId())
        ->setSortOrder(10 + count($storeManager->getStores()))
        ->setIsActive(1);
    $om->get(StoreResource::class)->save($store);
    $config->save('general/locale/code', $locale, 'stores', (int) $store->getId());
    $log(sprintf('Store view %s (%s) added.', $name, $locale));
    $changed = true;
}

if ($changed) {
    $storeManager->reinitStores();
}

// The default store view is English (Switzerland's shops often are); call it so.
if ($default->getName() === 'Default Store View') {
    $default->setName('English');
    $om->get(StoreResource::class)->save($default);
}

// --- Sample content (English, default values) -----------------------------------------
$sample = json_decode((string) file_get_contents(__DIR__ . '/sample-content.json'), true, 512, JSON_THROW_ON_ERROR);

$categoryId = (int) $om->get(CategoryCollectionFactory::class)->create()
    ->addAttributeToFilter('url_key', $sample['category']['url_key'])
    ->getFirstItem()->getId();

if (!$categoryId) {
    $category = $om->get(CategoryFactory::class)->create();
    $category->setStoreId(0)
        ->setParentId((int) $storeManager->getStore($default->getId())->getRootCategoryId())
        ->setIsActive(true)
        ->setIncludeInMenu(true)
        ->setIsAnchor(true);

    foreach (['name', 'url_key', 'description', 'meta_title', 'meta_keywords', 'meta_description'] as $field) {
        $category->setData($field, $sample['category'][$field]);
    }

    $categoryId = (int) $om->get(CategoryRepositoryInterface::class)->save($category)->getId();
    $log('Sample category added.');
    $changed = true;
}

$products = $om->get(ProductRepositoryInterface::class);

foreach ($sample['products'] as $data) {
    try {
        $products->get($data['sku']);

        continue;
    } catch (\Magento\Framework\Exception\NoSuchEntityException) {
        // Create it.
    }

    $product = $om->get(ProductFactory::class)->create();
    $product->setStoreId(0)
        ->setSku($data['sku'])
        ->setTypeId(Type::TYPE_SIMPLE)
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setWebsiteIds([(int) $default->getWebsiteId()])
        ->setVisibility(Visibility::VISIBILITY_BOTH)
        ->setStatus(Status::STATUS_ENABLED)
        ->setPrice($data['price'])
        ->setWeight(0.3)
        ->setCategoryIds([$categoryId])
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 25, 'is_in_stock' => 1]);

    foreach (['name', 'url_key', 'short_description', 'description', 'meta_title', 'meta_keyword', 'meta_description'] as $field) {
        $product->setData($field, $data[$field]);
    }

    $products->save($product);
    $log(sprintf('Sample product "%s" added.', $data['name']));
    $changed = true;
}

$page = $om->get(PageCollectionFactory::class)->create()
    ->addFieldToFilter('identifier', $sample['page']['identifier'])
    ->addStoreFilter(0, false)
    ->getFirstItem();

if (!$page->getId()) {
    $page = $om->get(PageFactory::class)->create();
    $page->setData($sample['page'] + ['is_active' => 1, 'page_layout' => '1column', 'store_id' => [0]]);
    $om->get(PageRepositoryInterface::class)->save($page);
    $log('Sample CMS page added.');
    $changed = true;
}

$block = $om->get(BlockCollectionFactory::class)->create()
    ->addFieldToFilter('identifier', $sample['block']['identifier'])
    ->addStoreFilter(0, false)
    ->getFirstItem();

if (!$block->getId()) {
    $block = $om->get(BlockFactory::class)->create();
    $block->setData($sample['block'] + ['is_active' => 1, 'store_id' => [0]]);
    $om->get(BlockRepositoryInterface::class)->save($block);
    $log('Sample CMS block added.');
    $changed = true;
}

// --- Editor role: catalog and content, plus Supertext ---------------------------------
$roleName = 'Supertext editor';
$role     = $om->get(RoleCollectionFactory::class)->create()
    ->addFieldToFilter('role_name', $roleName)
    ->addFieldToFilter('role_type', RoleGroup::ROLE_TYPE)
    ->getFirstItem();

if (!$role->getId()) {
    $role = $om->get(RoleFactory::class)->create();
    $role->setRoleName($roleName)
        ->setUserType((string) UserContextInterface::USER_TYPE_ADMIN)
        ->setUserId(0)
        ->setRoleType(RoleGroup::ROLE_TYPE)
        ->setParentId(0)
        ->setTreeLevel(1)
        ->save();
    $om->get(RulesFactory::class)->create()->setRoleId($role->getId())->setResources([
        'Magento_Backend::dashboard',
        'Magento_Catalog::catalog',
        'Magento_Catalog::catalog_inventory',
        'Magento_Catalog::products',
        'Magento_Catalog::categories',
        'Magento_Backend::content',
        'Magento_Backend::content_elements',
        'Magento_Cms::page',
        'Magento_Cms::save',
        'Magento_Cms::block',
        'Magento_Cms::media_gallery',
        'Supertext_Translation::translate',
    ])->saveRel();
    $log('Role "Supertext editor" added.');
}

// --- Demo accounts --------------------------------------------------------------------
$adminRole = (int) $om->get(RoleCollectionFactory::class)->create()
    ->addFieldToFilter('role_name', 'Administrators')
    ->addFieldToFilter('role_type', RoleGroup::ROLE_TYPE)
    ->getFirstItem()->getId();

$accounts = [
    // variable prefix, fallback prefix, role, name
    ['DEMO_ADMIN', 'MAGENTO_ADMIN', $adminRole, 'Admin'],
    ['DEMO_EDITOR', null, (int) $role->getId(), 'Editor'],
];

$users = $om->get(UserCollectionFactory::class);

foreach ($accounts as [$prefix, $fallback, $roleId, $last]) {
    $email    = trim((string) (getenv($prefix . '_EMAIL') ?: ($fallback ? getenv($fallback . '_EMAIL') : '')));
    $password = (string) (getenv($prefix . '_PASSWORD') ?: ($fallback ? getenv($fallback . '_PASSWORD') : ''));

    if ($email === '' || $password === '') {
        $log(sprintf('%s_EMAIL / %s_PASSWORD not set: no account created.', $prefix, $prefix));

        continue;
    }

    if ($users->create()->addFieldToFilter('username', $email)->getSize() > 0
        || $users->create()->addFieldToFilter('email', $email)->getSize() > 0) {
        continue;
    }

    $user = $om->get(UserFactory::class)->create();
    $user->setData([
        'username'  => $email,
        'firstname' => 'Demo',
        'lastname'  => $last,
        'email'     => $email,
        'password'  => $password,
        'is_active' => 1,
        'interface_locale' => 'en_US',
    ]);
    $user->setRoleId($roleId);

    try {
        $errors = $user->validate();

        if ($errors !== true) {
            throw new \Magento\Framework\Validator\Exception(__(implode(' ', array_map('strval', (array) $errors))));
        }

        $user->save();
    } catch (\Magento\Framework\Validator\Exception | \Magento\Framework\Exception\LocalizedException $e) {
        // The message names the rule, never the password.
        $log(sprintf('%s_EMAIL / %s_PASSWORD do not meet Magento\'s rules (%s): account skipped.', $prefix, $prefix, str_replace("\n", ' ', $e->getMessage())));

        continue;
    }

    $log(sprintf('Account from %s_EMAIL created.', $prefix));
}
unset($password);

// The installer's throwaway admin goes once the demo admin exists.
$demoAdmin = trim((string) (getenv('DEMO_ADMIN_EMAIL') ?: getenv('MAGENTO_ADMIN_EMAIL')));

if ($demoAdmin !== '' && $users->create()->addFieldToFilter('username', $demoAdmin)->getSize() > 0) {
    foreach ($users->create()->addFieldToFilter('email', ['like' => '%@supertext-demo.invalid']) as $installer) {
        $installer->delete();
        $log('Installer account removed.');
    }
}

if ($changed) {
    $cache = $om->get(CacheManager::class);
    $cache->clean($cache->getAvailableTypes());
}

$log('Demo setup done.');
