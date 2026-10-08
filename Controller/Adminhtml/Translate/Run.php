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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Supertext\Translation\Api\SupertextException;
use Supertext\Translation\Model\Config;
use Supertext\Translation\Model\Translation\EntityTypes;
use Supertext\Translation\Model\Translation\Translator;

/** POST type, id, store, overwrite: translates one item into one store view (called by the translate page). */
class Run extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Supertext_Translation::translate';

    public function __construct(
        Context $context,
        private readonly Translator $translator,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Json $json */
        $json      = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $request   = $this->getRequest();
        $type      = (string) $request->getParam('type');
        $id        = (int) $request->getParam('id');
        $storeId   = (int) $request->getParam('store');
        $overwrite = (bool) $request->getParam('overwrite');

        if (!isset(EntityTypes::TYPES[$type]) || $id <= 0) {
            return $json->setHttpResponseCode(400)->setData(['ok' => false, 'message' => (string) __('Invalid request.')]);
        }

        if (!$this->_authorization->isAllowed(EntityTypes::get($type)['acl'])) {
            return $json->setHttpResponseCode(403)->setData(['ok' => false, 'message' => (string) __('You are not allowed to edit this item.')]);
        }

        if (\function_exists('set_time_limit')) {
            @set_time_limit($this->config->timeout() + 60);
        }

        try {
            $result = $this->translator->translate($type, $id, $storeId, $overwrite);
        } catch (SupertextException|LocalizedException $e) {
            $this->logger->warning(sprintf('Supertext: %s %d → store %d: %s', $type, $id, $storeId, $e->getMessage()));

            return $json->setData(['ok' => false, 'message' => $e->getMessage()]);
        }

        $kept = \count($result['kept']);

        $message = match ($result['status']) {
            'unchanged' => (string) __('Already translated, unchanged'),
            'created'   => (string) __('Created (%1 fields)', \count($result['translated'])),
            default     => (string) __('Translated (%1 fields)', \count($result['translated'])),
        };

        if ($result['status'] !== 'unchanged' && $kept > 0) {
            $message .= ' · ' . __('%1 kept', $kept);
        }

        $data = ['ok' => true, 'status' => $result['status'], 'message' => $message, 'notes' => $result['notes']];

        if (isset($result['targetId'])) {
            $field         = EntityTypes::get($type)['edit'];
            $data['editUrl'] = $this->getUrl($field[0], [$field[1] => $result['targetId']]);
        }

        return $json->setData($data);
    }
}
