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

class LoadEvents extends Action
{
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

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

    public function execute()
    {
        $lines = [];
        $loadJS = [];
        foreach (self::getHelp()->getConfig->getEventsObs() as $event => $Name) {
            $fName = self::getHelp()->getSessionName.$event;

            $eventData = self::getHelp()->getSession->{"get".$fName}();

            if ($eventData) {
                $lines[] = "window.mktr.eventPush(".self::getHelp()->getManager->getEvent($Name[1], $eventData)->toJson().");";
                if (!$Name[0]) {
                    self::getHelp()->getSession->{"uns".$fName}();
                } else {
                    if ($Name[0]) {
                        $loadJS[$event] = true;
                    } else {
                        self::getHelp()->getSession->{"uns".$fName}();
                    }
                }
            }
        }

        foreach ($loadJS as $k => $v) {
            $lines[] = 'window.mktr.loadScript("'.$k.'");';
        }

        $result = self::getHelp()->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);
        /** TODO Magento 1 - setBody() | Magento 2 - setContents()  */
        $result->setContents(implode(self::getHelp()->getSpace(), $lines).PHP_EOL);
        return $result;
    }
}
