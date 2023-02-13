<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mktr\Tracker\Helper\Data;

class Feed extends Action
{
    private static $ins = [
        "Help" => null
    ];

    private static $error = null;
    private static $params = null;
    private static $fileName = "products";
    private static $secondName = "product";

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
        /** @noinspection DuplicatedCode */
        self::$error =  self::getHelp()->getFunc->isParamValid(self::$params,[
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
        return self::getHelp()->getPagesFeed->freshData();
    }
}
