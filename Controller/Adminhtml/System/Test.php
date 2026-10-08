<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Supertext\Translation\Api\SupertextException;
use Supertext\Translation\Model\Config;

/** "Test connection" in the configuration: a free call that checks the saved API key. */
class Test extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Supertext_Translation::config';

    public function __construct(Context $context, private readonly Config $config)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Json $json */
        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($this->config->apiKey() === '') {
            return $json->setData(['ok' => false, 'message' => (string) __('No API key is saved yet. Save the configuration first.')]);
        }

        try {
            $this->config->client()->validateApiKey();
        } catch (SupertextException $e) {
            return $json->setData(['ok' => false, 'message' => $e->getMessage()]);
        }

        return $json->setData(['ok' => true, 'message' => (string) __('Connected. The API key works.')]);
    }
}
