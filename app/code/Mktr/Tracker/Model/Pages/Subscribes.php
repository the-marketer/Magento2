<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Pages;

class Subscribes
{
    private static $ins = [
        "Help" => null,
        "Config" => null,
        "Subscriber" => null
    ];

    private static $error = null;

    private static function status()
    {
        return self::$error == null;
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
    public static function getStoreList()
    {
        if (self::$ins["Config"] == null) {
            self::$ins["Config"] = [];
            foreach (\Magento\Framework\App\ObjectManager::getInstance()
                         ->get('\Magento\Store\Api\StoreRepositoryInterface')
                         ->getList() as $k) {
                if (self::getHelp()->getConfig->getStoreValue("status", $k->getId()) &&
                    self::getHelp()->getConfig->getStoreValue("rest_key", $k->getId()) === self::getHelp()->getConfig->getRestKey()) {
                    self::$ins["Config"][] = $k->getId();
                }
            }
        }
        return self::$ins["Config"];
    }

    public static function getSubscriber()
    {
        if (self::$ins["Subscriber"] == null) {
            self::$ins["Subscriber"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Newsletter\Model\Subscriber');
        }
        return self::$ins["Subscriber"];
    }

    public function execute()
    {
        $tt = time();

        $f = self::getHelp()->getRequest->getParam("date_from") ?? null;
        $t = self::getHelp()->getRequest->getParam("date_to") ?? null;

        if ($f === null || $t === null) { $tt = time(); }
        
        if ($f === null) {
            $f = (strtotime('00:00:00', $tt) - 86400);
        } else {
            $f = strtotime($f. '00:00:00');
        }

        if ($t === null) {
            $t = strtotime('23:59:59', $tt);
        } else {
            $t = strtotime($t.' 23:59:59');
        }
        
        $o = self::getHelp()->getApi->send("unsubscribed_emails", [ 'date_from' => $f, 'date_to' => $t ], false);

        $r = json_decode($o->getContent());

        if ($r !== null) {
            $restKey = self::getHelp()->getConfig->getRestKey();
            $subStore = self::getHelp()->getData->subStore;
            
            if (!isset($subStore[$restKey])) {
                $subStore[$restKey] = [];
            }
            
            foreach ($subStore[$restKey] as $k => $v) {
                if (($tt - $v) > 86400) {
                    unset($subStore[$restKey][$k]);
                }
            }
    
            foreach ($r as $email) {
                if (array_key_exists($email, $subStore[$restKey])) {
                    continue;
                }
                $e = self::getSubscriber()->loadByEmail($email);
    
                $subStore[$restKey][$email] = $tt;
                $e->setStatus(\Magento\Newsletter\Model\Subscriber::STATUS_UNSUBSCRIBED)->save();
            }
    
            self::getHelp()->getData->subStore = $subStore;
            self::getHelp()->getData->save();
            $xml = ['status' => $r];
        } else {
            $xml = ['status' => 'N\A'];
        }

        return $xml;
    }
}
