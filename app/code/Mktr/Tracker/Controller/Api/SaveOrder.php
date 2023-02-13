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

class SaveOrder extends Action
{
    // private static $cons = null;

    private static $ins = [
        "Help" => null,
        "Subscriber" => null
    ];

    public function __construct(Context $context, Data $help, \Magento\Newsletter\Model\Subscriber $subscriber) {
        parent::__construct($context);
        self::$ins['Subscriber'] = $subscriber;
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

    /** TODO: Magento 2 */
    public static function getSubscriber()
    {
        if (self::$ins["Subscriber"] == null) {
            self::$ins["Subscriber"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Newsletter\Model\Subscriber');
        }
        return self::$ins["Subscriber"];
    }

    public function execute()
    {
        $result = self::getHelp()->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);

        $fName = vsprintf(self::getHelp()->getSessionName, array('saveOrder'));
        $sOrder = self::getHelp()->getSession->{"get".$fName}();

        if ($sOrder !== null) {
            self::getHelp()->getApi->send("save_order", $sOrder);

            $nws = self::getSubscriber()->loadByEmail($sOrder["email_address"]);

            if ($nws && $nws->getStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED)
            {
                if (!empty($sOrder["email_address"]))
                {
                    $fNameS = "set".vsprintf(self::getHelp()->getSessionName, array('setEmail'));
                    self::getHelp()->getSession->{$fNameS}(
                        self::getHelp()->getManager->schemaValidate(
                            $sOrder, self::getHelp()->getManager->getEventsSchema('setEmail')
                        )
                    );
                }

                if (!empty($sOrder["phone"]))
                {
                    $fNameS = "set".vsprintf(self::getHelp()->getSessionName, array('setPhone'));
                    self::getHelp()->getSession->{$fNameS}([ 'phone' => $sOrder["phone"] ]);
                }
            }
            if (self::getHelp()->getApi->getStatus() == 200)
            {
                self::getHelp()->getSession->{"uns".$fName}();
            }

            /** TODO Magento 1 - setBody() | Magento 2 - setContents()  */
            $result->setContents("console.log('SaveOrder', '".
                self::getHelp()->getApi->getStatus()."', '".
                self::getHelp()->getApi->getBody()."', '".
                self::getHelp()->getApi->getUrl()."');");
        }
        return $result;
    }
}

