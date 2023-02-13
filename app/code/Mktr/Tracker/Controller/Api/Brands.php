<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Newsletter\Model\Subscriber;
use Mktr\Tracker\Helper\Data;
use Mktr\Tracker\Model\Array2XML;
use Mktr\Tracker\Model\MktrApi;
use Mktr\Tracker\Model\MktrHelp;
use mysql_xdevapi\Exception;

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

    public function __construct(Context $context, Data $help) {
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

        if ($this->status())
        {
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
