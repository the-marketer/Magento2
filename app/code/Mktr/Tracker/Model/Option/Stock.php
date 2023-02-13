<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/
namespace Mktr\Tracker\Model\Option;

class Stock implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Options getter
     *
     * @return array
     * @noinspection PhpUnused
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('Out of Stock')],
            ['value' => 1, 'label' => __('In Stock')],
            ['value' => 2, 'label' => __('In supplier stock')]
        ];
    }

    /** @noinspection PhpUnused */
    public function toArray(): array
    {
        return [
            0 => __('Out of Stock'),
            1 => __('In Stock'),
            2 => __('In supplier stock')
        ];
    }
}
