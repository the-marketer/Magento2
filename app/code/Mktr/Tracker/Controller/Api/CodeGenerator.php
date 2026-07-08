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
use Mktr\Tracker\Model\DiscountCode;

class CodeGenerator extends Action
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var DiscountCode
     */
    private $discountCode;

    public function __construct(Context $context, Data $helper, DiscountCode $discountCode)
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->discountCode = $discountCode;
    }

    public function execute()
    {
        if (!$this->helper->getRequest->getParam("mime-type")) {
            $this->helper->getRequest->setParam("mime-type", 'json');
        }

        $error = $this->helper->getFunc->isParamValid([
            'key' => 'KeyAuth',
            'expiration_date' => 'DateCheck',
            'value' => 'Required|Int',
            'type' => "Required|RuleCheck"
        ]);

        if ($error === null) {
            try {
                $gCode = $this->discountCode->getNewCode($this->helper->getRequest->getParams());
            } catch (\Throwable $e) {
                return $this->helper->getFunc->Output(['status' => 'Unable to generate discount code']);
            }

            return $this->helper->getFunc->Output(['code' => $gCode->getCouponCodeGenerator()->getCode()]);
        }

        return $this->helper->getFunc->Output(['status' => $error]);
    }
}
