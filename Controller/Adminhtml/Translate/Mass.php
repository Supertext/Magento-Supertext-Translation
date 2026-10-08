<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Controller\Adminhtml\Translate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Supertext\Translation\Model\Translation\EntityTypes;

/** Mass action of the Products, Pages and Blocks lists: collects the selection and opens the translate page. */
class Mass extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Supertext_Translation::translate';

    private const MAX_ITEMS = 100;

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly ProductCollectionFactory $products,
        private readonly PageCollectionFactory $pages,
        private readonly BlockCollectionFactory $blocks,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $type     = (string) $this->getRequest()->getParam('type');
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $factory = match ($type) {
            'product'   => $this->products,
            'cms_page'  => $this->pages,
            'cms_block' => $this->blocks,
            default     => null,
        };

        if ($factory === null) {
            $this->messageManager->addErrorMessage(__('Unknown content type.'));

            return $redirect->setRefererUrl();
        }

        $ids = EntityTypes::ids($this->filter->getCollection($factory->create())->getAllIds());

        if ($ids === []) {
            $this->messageManager->addErrorMessage(__('Select at least one item to translate.'));

            return $redirect->setRefererUrl();
        }

        if (\count($ids) > self::MAX_ITEMS) {
            $this->messageManager->addNoticeMessage(__('Only the first %1 selected items are translated at once.', self::MAX_ITEMS));
            $ids = \array_slice($ids, 0, self::MAX_ITEMS);
        }

        return $redirect->setPath('supertext/translate/index', ['type' => $type, 'ids' => implode(',', $ids)]);
    }
}
