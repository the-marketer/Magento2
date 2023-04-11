<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mktr\Tracker\Helper\Data;

class Brands extends Action
{
    // private static $cons = null;
    private static $ins = [
        "Help" => null
    ];

    private static $error = null;
    private static $fileName = "brands";
    private static $secondName = "brand";

    private static $data;
    private static $url;

    public function __construct(Context $context, Data $help)
    {
        parent::__construct($context);
        self::$ins['Help'] = $help;
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    private static function status()
    {
        return self::$error == null;
    }

    /** @noinspection PhpUnused */
    public function execute()
    {
        self::$error =  self::getHelp()->getFunc->isParamValid([
            'key' => 'Required|Key'
        ]);

        if ($this->status()) {
            return self::getHelp()->getFunc->readOrWrite(self::$fileName, self::$secondName, $this);
        }

        return self::getHelp()->getFunc->Output('status', self::$error);
    }

    public static function freshData(): array
    {
        $brandAttribute = self::getHelp()->getConfig->getBrandAttribute();
        self::$url = self::getHelp()->getBaseUrl . 'catalogsearch/result/?q=';
        self::$data = [];
        foreach ($brandAttribute as $item) {
            foreach (self::getHelp()->getBrands->get($item)->getOptions() as $option) {
                if ($option->getValue()) {
                    self::$data[] = [
                        'name' => $option->getLabel(),
                        'id' => $option->getValue(),
                        'url' => self::$url . $option->getLabel()
                    ];
                }
            }
        }

        return self::$data;
    }
}
