<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Option;

class Provider implements \Magento\Framework\Option\ArrayInterface
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
            ['value' => 0, 'label' => __("WebSite")],
            ['value' => 1, 'label' => __("The Marketer")]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     * @noinspection PhpUnused
     */
    public function toArray(): array
    {
        return [
            0 => __("WebSite"),
            1 => __("The Marketer")
        ];
    }
}
