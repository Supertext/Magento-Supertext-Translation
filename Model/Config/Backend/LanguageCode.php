<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/** Optional Supertext language code per store view, e.g. "fr-CH". */
class LanguageCode extends Value
{
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if ($value !== '' && !preg_match('/^[A-Za-z]{2,3}([-_][A-Za-z0-9]{2,8})*$/', $value)) {
            throw new LocalizedException(__('"%1" is not a valid language code. Use a code such as de-CH or fr.', $value));
        }

        $this->setValue(str_replace('_', '-', $value));

        return parent::beforeSave();
    }
}
