<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CmsStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'same', 'label' => __('Same as the original')],
            ['value' => 'disabled', 'label' => __('Disabled (review first)')],
        ];
    }
}
