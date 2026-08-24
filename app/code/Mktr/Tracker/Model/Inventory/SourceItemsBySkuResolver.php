<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Inventory;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Mktr\Tracker\Api\SourceItemsBySkuResolverInterface;

class SourceItemsBySkuResolver implements SourceItemsBySkuResolverInterface
{
    private const SOURCE_ITEMS_BY_SKU = 'Magento\InventoryApi\Api\GetSourceItemsBySkuInterface';

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function __construct(
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    public function isEnabled(): bool
    {
        try {
            return $this->moduleManager->isEnabled('Magento_Inventory')
                && interface_exists(self::SOURCE_ITEMS_BY_SKU);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function execute(string $sku): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            return $this->objectManager->get(self::SOURCE_ITEMS_BY_SKU)->execute($sku);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
