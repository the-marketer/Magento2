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
use Mktr\Tracker\Helper\Data;

class CodeGenerator extends Action
{
    private static $ins = [
        "Help" => null,
        "CodeGen" => null
    ];

    private static $error;

    public function __construct(\Magento\Framework\App\Action\Context $context, Data $help)
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
    /** TODO: Magento 2 */
    public static function getCodeGen()
    {
        if (self::$ins["CodeGen"] == null) {
            self::$ins["CodeGen"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Model\DiscountCode');
        }
        return self::$ins["CodeGen"];
    }

    private static function status()
    {
        return self::$error == null;
    }

    public function execute()
    {
        if (!self::getHelp()->getRequest->getParam("mime-type")) {
            self::getHelp()->getRequest->setParam("mime-type", 'json');
        }
        
        self::$error =  self::getHelp()->getFunc->isParamValid([
            'key' => 'Required|Key',
            'expiration_date' => 'DateCheck',
            'value' => 'Required|Int',
            'type' => "Required|RuleCheck"
        ]);

        if (self::status()) {
            $gCode = self::getCodeGen()->getNewCode(self::getHelp()->getRequest->getParams());

            return self::getHelp()->getFunc->Output([ 'code' => $gCode->getCouponCodeGenerator()->getCode() ]);
        }
        return self::getHelp()->getFunc->Output([ 'status' => self::$error ]);
    }
}
