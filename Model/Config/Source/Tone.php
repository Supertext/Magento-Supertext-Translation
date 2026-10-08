<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Tone implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('Supertext default')],
            ['value' => 'more', 'label' => __('Formal (Sie, vous)')],
            ['value' => 'less', 'label' => __('Informal (du, tu)')],
        ];
    }
}
