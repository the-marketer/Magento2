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
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Mktr\Tracker\Api\SourceItemsBySkuResolverInterface;

class SourceItemsBySkuResolver implements SourceItemsBySkuResolverInterface
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var GetSourceItemsBySkuInterface
     */
    private $sourceItemsBySku;

    public function __construct(
        ModuleManager $moduleManager,
        GetSourceItemsBySkuInterface $sourceItemsBySku
    ) {
        $this->moduleManager = $moduleManager;
        $this->sourceItemsBySku = $sourceItemsBySku;
    }

    public function isEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Inventory');
    }

    public function execute(string $sku): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return $this->sourceItemsBySku->execute($sku);
    }
}
