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
use Mktr\Tracker\Model\MktrConfig;
use Mktr\Tracker\Model\MktrEvents;
use Mktr\Tracker\Model\MktrHelp;
use Magento\Framework\App\ObjectManager;

class LoadEvents extends Action
{
    // private static $cons = null;

    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    public function __construct(Context $context, Data $help) {
        parent::__construct($context);
        self::$ins['Help'] = $help;
        // self::$cons = $this;
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
        foreach (self::getHelp()->getConfig->getEventsObs() as $event=>$Name)
        {
            if (!$Name[0]) {
                $fName = "get".vsprintf(self::getHelp()->getSessionName, array($event));

                $eventData = self::getHelp()->getSession->{$fName}();

                if ($eventData) {
                    $lines[] = "dataLayer.push(".self::getHelp()->getManager->getEvent($Name[1], $eventData)->toJson().");";

                    $uName = "uns".vsprintf(self::getHelp()->getSessionName, array($event));
                    self::getHelp()->getSession->{$uName}();
                }
            }
        }

        $result = self::getHelp()->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);
        /** TODO Magento 1 - setBody() | Magento 2 - setContents()  */
        $result->setContents(implode(self::getHelp()->getSpace(), $lines).PHP_EOL);
        return $result;
    }
}

