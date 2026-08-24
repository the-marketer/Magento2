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

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Newsletter\Model\Subscriber;
use Mktr\Tracker\Helper\Data;

class SaveOrder implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(Data $helper, Subscriber $subscriber)
    {
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

            $result->setContents(
                'console.log(' .
                json_encode('SaveOrder', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ', ' .
                json_encode((int) $this->helper->getApi->getStatus(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) .
                ');'
            );
        }
        return $result;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $request->isPost()
            && strtolower((string) $request->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }
}
