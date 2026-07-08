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

use Magento\Framework\App\Action\HttpGetActionInterface;
use Mktr\Tracker\Helper\Data;

class LoadEvents implements HttpGetActionInterface
{
    /**
     * @var Data
     */
    private $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    public function execute()
    {
        $lines = [];
        $loadJS = [];
        foreach ($this->helper->getConfig->getEventsObs() as $event => $Name) {
            $fName = $this->helper->getSessionName . $event;

            $eventData = $this->helper->getSession->{"get" . $fName}();

            if ($eventData) {
                $lines[] = "window.mktr.eventPush(" . $this->helper->getManager->getEvent($Name[1], $eventData)->toJson() . ");";
                if (!$Name[0]) {
                    $this->helper->getSession->{"uns" . $fName}();
                } else {
                    if ($Name[0]) {
                        $loadJS[$event] = true;
                    } else {
                        $this->helper->getSession->{"uns" . $fName}();
                    }
                }
            }
        }

        foreach ($loadJS as $k => $v) {
            $lines[] = 'if (window.mktr.postAction) { window.mktr.postAction("' . $k . '"); }';
        }

        $result = $this->helper->getPageRaw;
        $result->setHeader('Content-type', 'application/javascript; charset=utf-8;', 1);
        $result->setContents(implode($this->helper->getSpace(), $lines) . PHP_EOL);
        return $result;
    }
}
