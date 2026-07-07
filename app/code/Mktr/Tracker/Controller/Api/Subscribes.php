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
use Mktr\Tracker\Helper\Data;

class Subscribes extends Action
{
    /**
     * @var Data
     */
    private $helper;

    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    public function execute()
    {
        $error = $this->helper->getFunc->isParamValid([
            'key' => 'KeyAuth',
            'date_from' => 'DateCheck|StartDate',
            'date_to' => 'DateCheck'
        ]);

        if ($error === null) {
            return $this->helper->getFunc->Output('unsubscribe', $this->helper->getPagesSubscribes->execute());
        }

        return $this->helper->getFunc->Output('status', $error);
    }
}
