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

/** The API key field, with the links to create an account and generate the key. */
class ApiKey extends Field
{
    public function __construct(Context $context, private readonly Config $config, array $data = [])
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $links = __('Paste the key as Supertext shows it, with or without "Supertext-Auth-Key".') . '<br>'
            . __('No Supertext account yet?')
            . ' <a href="' . Config::SIGNUP_URL . '" target="_blank" rel="noopener">' . __('Create one at supertext.com.') . '</a> '
            . __('Generate your API key at')
            . ' <a href="' . Config::API_KEY_URL . '" target="_blank" rel="noopener">supertext.com → Integrations → API</a> '
            . __('(requires the Admin role).');

        if ($this->config->apiKeyFromEnvironment()) {
            return '<div class="control-value" style="padding-top:7px">'
                . $this->escapeHtml(__('Set by the SUPERTEXT_API_KEY environment variable on the server.'))
                . '</div>';
        }

        $element->setComment($links);

        return parent::_getElementHtml($element);
    }
}
