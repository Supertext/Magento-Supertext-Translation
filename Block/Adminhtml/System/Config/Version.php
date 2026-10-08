<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Supertext\Translation\Model\Config;

/** The installed version (from the module's composer.json), linked to its GitHub release. */
class Version extends Field
{
    public function __construct(Context $context, private readonly Config $config, array $data = [])
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $version = $this->config->version() ?: '–';
        $url     = $this->config->releaseUrl();
        $html    = $url === ''
            ? $this->escapeHtml($version)
            : '<a href="' . $this->escapeUrl($url) . '" target="_blank" rel="noopener">' . $this->escapeHtml($version) . '</a>';

        return '<div class="control-value" style="padding-top:7px">' . $html . '</div>';
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element): bool
    {
        return false;
    }
}
