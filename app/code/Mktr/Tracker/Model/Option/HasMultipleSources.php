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

class HasMultipleSources extends \Magento\Framework\App\Config\Value
{

    private static $ins = [
        "MSI" => null,
        "Criteria" => null
    ];

    private static $MSI = null;
    
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
                self::$MSI = 1;
            } else {
                self::$MSI = 0;
            }
        }
       
        return self::$MSI;
    }
    /**
     * Options getter
     *
     * @return mixed
     * @noinspection PhpUnused
     */
    public function afterLoad()
    {
        $setMSI = 0;

        if (self::checkMSI()) {
            foreach (self::getMSI()->getList(self::getCriteria())->getItems() as $v) {
                if ($v['source_code'] !== 'default') {
                    $setMSI = 1;
                    break;
                }
            }
        }

        $this->setValue($setMSI);

        return parent::afterLoad();
    }

    /**
     * @return SetTimestamp
     */
    public function beforeSave()
    {
         $setMSI = 0;

        if (self::checkMSI()) {
            foreach (self::getMSI()->getList(self::getCriteria())->getItems() as $v) {
                if ($v['source_code'] !== 'default') {
                    $setMSI = 1;
                    break;
                }
            }
        }

        $this->setValue($setMSI);
        return parent::beforeSave();
    }
}
