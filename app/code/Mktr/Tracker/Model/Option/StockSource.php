<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Option;

class StockSource implements \Magento\Framework\Option\ArrayInterface
{
    private static $ins = [
        "MSI" => null,
        "Criteria" => null
    ];

    private static $MSI = null;
    private static $list = null;

    public static function getMSI()
    {
        if (self::$ins["MSI"] == null) {
            self::$ins["MSI"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\InventoryApi\Api\SourceRepositoryInterface');
        }
        return self::$ins["MSI"];
    }

    public static function getCriteria()
    {
        if (self::$ins["Criteria"] == null) {
            self::$ins["Criteria"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Framework\Api\SearchCriteriaBuilder')->create();
        }
        return self::$ins["Criteria"];
    }
    
    public static function checkMSI () {
        if (self::$MSI === null) {
            if (!interface_exists('\Magento\InventoryApi\Api\SourceRepositoryInterface')) {
                self::$MSI = false;
                return self::$MSI;
            }

            try {
                self::$MSI = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get("\Magento\Framework\Module\Manager")
                    ->isEnabled('Magento_Inventory');
            } catch (\Throwable $e) {
                self::$MSI = false;
            }
        }
        return self::$MSI;
    }

    private static function getSourceValue($source, $key)
    {
        if (is_array($source) && isset($source[$key])) {
            return $source[$key];
        }

        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (is_object($source) && method_exists($source, $method)) {
            return $source->{$method}();
        }

        return null;
    }

    public static function getList ($type = 0) {
        if (self::$list === null) {
            self::$list = [
                [ 'all' => __('All') ],
                [ [ 'value' => 'all', 'label' => __('All') ] ]
            ];
            if (self::checkMSI()) {
                try {
                    foreach (self::getMSI()->getList(self::getCriteria())->getItems() as $v) {
                        $sourceCode = self::getSourceValue($v, 'source_code');
                        $name = self::getSourceValue($v, 'name');

                        if ($sourceCode === null || $name === null) {
                            continue;
                        }

                        self::$list[0][$sourceCode] = __($name);
                        self::$list[1][] = [ 'value' => $sourceCode, 'label' => __($name) ];
                    }
                } catch (\Throwable $e) {
                    return self::$list[$type];
                }
            }
        }
        return self::$list[$type];
    }
    /**
     * Options getter
     *
     * @return array
     * @noinspection PhpUnused
     */
    public function toOptionArray(): array
    {
        return self::getList(1);
    }

    /** @noinspection PhpUnused */
    public function toArray(): array
    {
        return self::getList(0);
    }
}
