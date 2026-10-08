<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'live', 'label' => __('Live (api.supertext.com)')],
            ['value' => 'staging', 'label' => __('Staging')],
            ['value' => 'testing', 'label' => __('Testing')],
        ];
    }
}
