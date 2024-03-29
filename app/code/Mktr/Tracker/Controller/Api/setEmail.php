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

class setEmail extends Action
{
    // private static $cons = null;

    private static $ins = [
        "Help" => null,
        "Subscriber" => null
    ];

    public function __construct(Context $context, Data $help, \Magento\Newsletter\Model\Subscriber $subscriber)
    {
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
        $lines = "";
        $result = self::getHelp()->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);
        
        $tApi = self::getHelp()->getSessionName."Api";
        $fName = self::getHelp()->getSessionName.'setEmail';

        $sApi = self::getHelp()->getSession->{"get".$tApi}();

        if ($sApi !== null) {
            $sEmail = self::getHelp()->getSession->{"get".$fName}();
            if ($sEmail !== null) {
                /** @noinspection DuplicatedCode */
                $nws = self::getSubscriber()->loadByEmail($sEmail["email_address"]);

                $info = [
                    "email" => $sEmail['email_address']
                ];

                if ($nws && $nws->getStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED) {
                    $customer = self::getHelp()->getCustomerData
                        ->setWebsiteId(self::getHelp()->getWebsite->getId())
                        ->loadByEmail($sEmail['email_address']);
                    $customerAddressId = $customer->getDefaultShipping();
                    if ($customerAddressId) {
                        $address = self::getHelp()->getCustomerAddress
                            ->load($customer->getDefaultShipping());

                        $customerData = $address->getData();
                        $info["phone"] = self::getHelp()->getFunc->validateTelephone($customerData['telephone']);
                    }
                    if ($customer->getName() !== null && $customer->getName() !== ' ') {
                        $info["name"] = $customer->getName();
                    } elseif ($customer->getEmail() !== null && $customer->getFirstname() === null && $customer->getLastname() === null) {
                        $info["name"] = explode("@", $customer->getEmail())[0];
                    } elseif ($customer->getFirstname() !== null && $customer->getLastname() !== null) {
                        $info["name"] = $customer->getFirstname().' '.$customer->getLastname();
                    } elseif ($customer->getFirstname() !== null) {
                        $info["name"] = $customer->getFirstname();
                    } elseif ($customer->getLastname() !== null) {
                        $info["name"] = $customer->getLastname();
                    } else {
                        $info["name"] = explode("@", $sEmail['email_address'])[0];
                    }

                    self::getHelp()->getApi->send("add_subscriber", $info);
                    $lines = "setEmailAdd";
                } else {
                    self::getHelp()->getApi->send("remove_subscriber", $info);
                    $lines = "setEmailRemove";
                }

                if (self::getHelp()->getApi->getStatus() == 200) {
                    $fNameP = self::getHelp()->getSessionName . 'setPhone';
                    if (self::getHelp()->getSession->{"get".$fNameP}()) {
                        self::getHelp()->getSession->{"uns".$fNameP}();
                    }
                    self::getHelp()->getSession->{"uns".$fName}();
                }

                /** TODO Magento 1 - setBody() | Magento 2 - setContents()  */
                $result->setContents("console.log('".$lines."', '".
                    self::getHelp()->getApi->getStatus()."', '".
                    self::getHelp()->getApi->getBody()."', '".
                    self::getHelp()->getApi->getUrl()."','".
                    json_encode(self::getHelp()->getApi->getParam())."');");
            } else {
                $result->setContents("console.log('null');");
            }
            self::getHelp()->getSession->{"uns".$tApi}();
        } else {
            $fNameP = self::getHelp()->getSessionName . 'setPhone';

            if (self::getHelp()->getSession->{"get".$fNameP}()) {
                self::getHelp()->getSession->{"uns".$fNameP}();
            }
            self::getHelp()->getSession->{"uns".$fName}();

            $result->setContents("console.log('null');");
        }
        return $result;
    }
}
