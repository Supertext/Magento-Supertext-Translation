<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Block\Adminhtml\Button;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/** "Translate with Supertext" on an edit page: opens the translate page for the saved item. */
abstract class AbstractButton implements ButtonProviderInterface
{
    protected const TYPE = '';

    protected const ID_PARAM = 'id';

    public function __construct(private readonly Context $context)
    {
    }

    public function getButtonData(): array
    {
        $id = (int) $this->context->getRequest()->getParam(static::ID_PARAM);

        if ($id <= 0 || !$this->context->getAuthorization()->isAllowed('Supertext_Translation::translate')) {
            return [];
        }

        $url = $this->context->getUrlBuilder()->getUrl('supertext/translate/index', ['type' => static::TYPE, 'ids' => $id]);

        return [
            'label'      => __('Translate with Supertext'),
            'class'      => 'supertext-translate',
            'on_click'   => sprintf("location.href = '%s';", $url),
            'sort_order' => 25,
        ];
    }
}
