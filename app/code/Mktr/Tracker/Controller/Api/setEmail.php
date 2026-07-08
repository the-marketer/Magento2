<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Newsletter\Model\Subscriber;
use Mktr\Tracker\Helper\Data;

class setEmail extends Action
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(Context $context, Data $helper, Subscriber $subscriber)
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->subscriber = $subscriber;
    }

    public function execute()
    {
        $lines = "";
        $result = $this->helper->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);

        $tApi = $this->helper->getSessionName . "Api";
        $fName = $this->helper->getSessionName . 'setEmail';

        $sApi = $this->helper->getSession->{"get" . $tApi}();

        if ($sApi !== null) {
            $sEmail = $this->helper->getSession->{"get" . $fName}();
            if ($sEmail !== null) {
                $skip = false;
                $nws = $this->subscriber->loadByEmail($sEmail["email_address"]);
                $info = ["email" => $sEmail['email_address']];

                if ($nws && $nws->getStatus() == Subscriber::STATUS_SUBSCRIBED) {
                    $customer = $this->helper->getCustomerData
                        ->setWebsiteId($this->helper->getWebsite->getId())
                        ->loadByEmail($sEmail['email_address']);
                    $customerAddressId = $customer->getDefaultShipping();
                    if ($customerAddressId) {
                        $address = $this->helper->getCustomerAddress
                            ->load($customer->getDefaultShipping());

                        $customerData = $address->getData();
                        if (isset($customerData['telephone'])) {
                            $info["phone"] = $this->helper->getFunc->validateTelephone($customerData['telephone']);
                        }
                    }
                    if ($customer->getName() !== null && $customer->getName() !== ' ') {
                        $info["name"] = $customer->getName();
                    } elseif ($customer->getEmail() !== null && $customer->getFirstname() === null && $customer->getLastname() === null) {
                        $info["name"] = explode("@", $customer->getEmail())[0];
                    } elseif ($customer->getFirstname() !== null && $customer->getLastname() !== null) {
                        $info["name"] = $customer->getFirstname() . ' ' . $customer->getLastname();
                    } elseif ($customer->getFirstname() !== null) {
                        $info["name"] = $customer->getFirstname();
                    } elseif ($customer->getLastname() !== null) {
                        $info["name"] = $customer->getLastname();
                    } else {
                        $info["name"] = explode("@", $sEmail['email_address'])[0];
                    }

                    $this->helper->getApi->send("add_subscriber", $info);
                    $lines = "setEmailAdd";
                } else {
                    $skip = true;
                    $lines = "setEmailRemove";
                }

                if ($skip === true || $this->helper->getApi->getStatus() == 200) {
                    $fNameP = $this->helper->getSessionName . 'setPhone';
                    if ($this->helper->getSession->{"get" . $fNameP}()) {
                        $this->helper->getSession->{"uns" . $fNameP}();
                    }
                    $this->helper->getSession->{"uns" . $fName}();
                    if ($skip !== true) {
                        $result->setContents("console.log('" . $lines . "', '" .
                            $this->helper->getApi->getStatus() . "', '" .
                            $this->helper->getApi->getBody() . "', '" .
                            $this->helper->getApi->getUrl() . "','" .
                            json_encode($this->helper->getApi->getParam()) . "');");
                    }
                } else {
                    $result->setContents("console.log('null');");
                }
            } else {
                $result->setContents("console.log('null');");
            }
            $this->helper->getSession->{"uns" . $tApi}();
        } else {
            $fNameP = $this->helper->getSessionName . 'setPhone';

            if ($this->helper->getSession->{"get" . $fNameP}()) {
                $this->helper->getSession->{"uns" . $fNameP}();
            }
            $this->helper->getSession->{"uns" . $fName}();

            $result->setContents("console.log('null');");
        }
        return $result;
    }
}
