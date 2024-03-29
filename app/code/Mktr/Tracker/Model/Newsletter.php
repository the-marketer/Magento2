<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

class Newsletter extends \Magento\Newsletter\Model\Subscriber
{
    private static $Mktr = null;

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$Mktr == null) {
            self::$Mktr = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Model\Config');
        }
        return self::$Mktr;
    }

    public function sendConfirmationSuccessEmail()
    {
        if (self::getHelp()->getOptIn() == 0) {
            return parent::sendConfirmationSuccessEmail();
        }
        return $this;
    }

    public function sendUnsubscriptionEmail()
    {
        if (self::getHelp()->getOptIn() == 0) {
            return parent::sendUnsubscriptionEmail();
        }
        return $this;
    }
}
