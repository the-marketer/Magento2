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

class SaveOrder extends Action
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
        $result = $this->helper->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);

        $fName = $this->helper->getSessionName . 'saveOrder';
        $sOrder = $this->helper->getSession->{"get" . $fName}();

        if ($sOrder !== null) {
            $this->helper->getApi->send("save_order", $sOrder);

            $nws = $this->subscriber->loadByEmail($sOrder["email_address"]);

            if ($nws && $nws->getStatus() == Subscriber::STATUS_SUBSCRIBED) {
                if (!empty($sOrder["email_address"])) {
                    $fNameS = "set" . $this->helper->getSessionName . 'setEmail';
                    $this->helper->getSession->{$fNameS}(
                        $this->helper->getManager->schemaValidate(
                            $sOrder,
                            $this->helper->getManager->getEventsSchema('setEmail')
                        )
                    );
                }
            }
            if ($this->helper->getApi->getStatus() == 200) {
                $this->helper->getSession->{"uns" . $fName}();
            }

            $result->setContents("console.log('SaveOrder', '" .
                $this->helper->getApi->getStatus() . "', '" .
                $this->helper->getApi->getBody() . "', '" .
                $this->helper->getApi->getUrl() . "');");
        }
        return $result;
    }
}
