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
            if (\Magento\Framework\App\ObjectManager::getInstance()->get("\Magento\Framework\Module\Manager")->isEnabled('Magento_Inventory')) {
                self::$MSI = true;
            } else {
                self::$MSI = false;
            }
        }
        return self::$MSI;
    }

    public static function getList ($type = 0) {
        if (self::$list === null) {
            self::$list = [
                [ 'all' => __('All') ],
                [ [ 'value' => 'all', 'label' => __('All') ] ]
            ];
            if (self::checkMSI()) {
                foreach (self::getMSI()->getList(self::getCriteria())->getItems() as $v) {
                    self::$list[0][$v['source_code']] = __($v['name']);
                    self::$list[1][] = [ 'value' => $v['source_code'], 'label' => __($v['name']) ];
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
