<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Encrypted;
use Supertext\Translation\Api\SupertextClient;

/** Stored encrypted; a pasted "Supertext-Auth-Key " prefix is removed. */
class ApiKey extends Encrypted
{
    public function beforeSave()
    {
        $value = (string) $this->getValue();

        if ($value !== '' && !preg_match('/^\*+$/', $value)) {
            $this->setValue(SupertextClient::normalizeKey($value));
        }

        return parent::beforeSave();
    }
}
