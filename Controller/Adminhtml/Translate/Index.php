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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\Model\View\Result\Page;
use Supertext\Translation\Model\Translation\EntityTypes;

/** The "Translate with Supertext" page: ?type=product&ids=1,2 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Supertext_Translation::translate';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $type = (string) $this->getRequest()->getParam('type', 'product');

        if (!isset(EntityTypes::TYPES[$type])) {
            $this->messageManager->addErrorMessage(__('Unknown content type.'));

            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('admin/dashboard');
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(\in_array($type, ['product', 'category'], true) ? 'Magento_Catalog::catalog' : 'Magento_Backend::content');
        $page->getConfig()->getTitle()->prepend(__('Translate with Supertext'));

        return $page;
    }
}
