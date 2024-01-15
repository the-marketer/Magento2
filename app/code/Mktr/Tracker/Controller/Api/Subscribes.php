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

class Subscribes extends Action
{
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $error = null;

    private static function status()
    {
        return self::$error == null;
    }

    public function __construct(\Magento\Framework\App\Action\Context $context)
    {
        parent::__construct($context);
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    public function execute()
    {
        self::$error = self::getHelp()->getFunc->isParamValid([
            'key' => 'Required|Key',
            'date_from' => 'DateCheck|StartDate',
            'date_to' => 'DateCheck'
        ]);

        if (self::status()) {
            return self::getHelp()->getFunc->Output('unsubscribe', self::getHelp()->getPagesSubscribes->execute());
        }

        return self::getHelp()->getFunc->Output('status', self::$error);
    }
}
