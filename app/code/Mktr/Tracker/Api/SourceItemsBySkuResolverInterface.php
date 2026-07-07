<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Api;

interface SourceItemsBySkuResolverInterface
{
    public function isEnabled(): bool;

    /**
     * @param string $sku
     * @return array
     */
    public function execute(string $sku): array;
}
