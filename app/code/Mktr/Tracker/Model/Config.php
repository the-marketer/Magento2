<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Model;

class Config
{
    const scopeType = 'store';

    const DATE_START_FORMAT = "Y-m-d 00:00:00";
    const DATE_END_FORMAT = "Y-m-d 23:59:59";

    const FireBase = 'const firebaseConfig = {
  apiKey: "AIzaSyA3c9lHIzPIvUciUjp1U2sxoTuaahnXuHw",
  projectId: "themarketer-e5579",
  messagingSenderId: "125832801949",
  appId: "1:125832801949:web:0b14cfa2fd7ace8064ae74",
};
firebase.initializeApp(firebaseConfig);';
    const FireBaseMessaging = 'importScripts("https://www.gstatic.com/firebasejs/9.4.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/9.4.0/firebase-messaging-compat.js");
importScripts("./firebase-config.js");
importScripts("https://t.themarketer.com/firebase.js");';
/* TODO: LINK */
    private static $loader = '(function(d, s, i) { var f = d.getElementsByTagName(s)[0], j = d.createElement(s);j.async = true; j.src = "https://t.themarketer.com/t/j/" + i; f.parentNode.insertBefore(j, f);})(document, "script", "%s")';

    const configNames = [
        'status' => 'mktr_tracker/tracker/status',
        'tracking_key' => 'mktr_tracker/tracker/tracking_key',
        'rest_key' => 'mktr_tracker/tracker/rest_key',
        'customer_id'=>'mktr_tracker/tracker/customer_id',
        'cron_feed' => 'mktr_tracker/tracker/cron_feed',
        'update_feed' => 'mktr_tracker/tracker/update_feed',
        'cron_review' => 'mktr_tracker/tracker/cron_feed',
        'update_review' => 'mktr_tracker/tracker/update_feed',
        'opt_in' => 'mktr_tracker/tracker/opt_in',
        'push_status' => 'mktr_tracker/tracker/push_status',
        'default_stock' => 'mktr_tracker/tracker/default_stock',
        'allow_export' => 'mktr_tracker/tracker/allow_export',
        'selectors' => 'mktr_tracker/tracker/selectors',
        'brand' => 'mktr_tracker/attribute/brand',
        'color' => 'mktr_tracker/attribute/color',
        'size' => 'mktr_tracker/attribute/size'
    ];

    const configValues = [
        'status' => null,
        'tracking_key' => null,
        'rest_key' => null,
        'customer_id'=> null,
        'opt_in' => null,
        'push_status' => null,
        'default_stock' => null,
        'allow_export' => null,
        'selectors' => null,
        'brand' => null,
        'color' => null,
        'size' => null
    ];

    private static $configValues = [];

    const observerGetEvents = [
        "addToCart"=> [false, "__sm__add_to_cart"],
        "removeFromCart"=> [false, "__sm__remove_from_cart"],
        "addToWishlist"=> [false, "__sm__add_to_wishlist"],
        "removeFromWishlist"=> [false, "__sm__remove_from_wishlist"],
        "saveOrder"=> [true, "__sm__order"],
        "setEmail"=> [true, "__sm__set_email"],
        "setPhone"=> [false, "__sm__set_phone"]
    ];

    private static $scopeCode = null;   // 'default';

    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    const discountRules = [
        0 => "fixedValue",
        1 => "percentage",
        2 => "freeShipping"
    ];

    /** @noinspection PhpUnused */
    public static function getDiscountRules()
    {
        return self::discountRules;
    }

    private static $configData = null;

    /** @noinspection PhpUnused */
    public static function getDateStart()
    {
        return self::DATE_START_FORMAT;
    }

    /** @noinspection PhpUnused */
    public static function getDateEnd()
    {
        return self::DATE_END_FORMAT;
    }

    /** @noinspection PhpUnused */
    public static function getEventsObs()
    {
        return self::observerGetEvents;
    }

    /** @noinspection PhpUnused */
    public static function getLoader(): string
    {
        return self::$loader;
    }
    /** @noinspection PhpUnused */
    public static function getFireBase(): string
    {
        return self::FireBase;
    }
    /** @noinspection PhpUnused */
    public static function getFireBaseMessaging(): string
    {
        return self::FireBaseMessaging;
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
    private static function getConfig()
    {
        if (self::$configData == null)
        {
            self::$configData = \Magento\Framework\App\ObjectManager::getInstance()->get("\Magento\Framework\App\Config\ScopeConfigInterface");
        }
        return self::$configData;
    }

    /** @noinspection PhpUnused */
    public static function setScopeCode($store)
    {
        self::$configValues = [];
        self::$scopeCode = $store;
    }

    /** @noinspection PhpUnused */
    public static function getScopeCode()
    {
        if (self::$scopeCode == null)
        {
            self::$scopeCode = self::getHelp()->getStore->getStoreId();
        }
        return self::$scopeCode;
    }

    public static function getStoreValue($name, $store)
    {
        if (isset(self::configNames[$name]))
        {
            return self::getConfig()->getValue(self::configNames[$name], self::scopeType, $store);
        } else {
            return self::getConfig()->getValue($name, self::scopeType, $store);
        }
    }

    public static function getValue($name)
    {
        if (empty(self::$configValues[$name]))
        {
            if (isset(self::configNames[$name]))
            {
                self::$configValues[$name] = self::getConfig()->getValue(self::configNames[$name], self::scopeType, self::getScopeCode());
                if (in_array($name, array('color','size','brand')))
                {
                    self::$configValues[$name] = explode("|", self::$configValues[$name]);
                }
            } else {
                self::$configValues[$name] = self::getConfig()->getValue($name, self::scopeType, self::getScopeCode());
            }
        }

        return self::$configValues[$name];
    }

    /** @noinspection PhpUnused */
    public static function getStatus(): int
    {
        return (int) self::getValue('status');
    }

    /** @noinspection PhpUnused */
    public static function getKey()
    {
        return self::getValue('tracking_key');
    }

    /** @noinspection PhpUnused */
    public static function getRestKey()
    {
        return self::getValue('rest_key');
    }

    /** @noinspection PhpUnused */
    public static function getOptIn(): int
    {
        return (int) self::getValue('opt_in');
    }

    /** @noinspection PhpUnused */
    public static function getPushStatus(): int
    {
        return (int) self::getValue('push_status');
    }

    /** @noinspection PhpUnused */
    public static function getDefaultStock(): int
    {
        return (int) self::getValue('default_stock');
    }

    /** @noinspection PhpUnused */
    public static function getAllowExport(): int
    {
        return (int) self::getValue('allow_export');
    }

    /** @noinspection PhpUnused */
    public static function getCustomerId()
    {
        return self::getValue('customer_id');
    }

    /** @noinspection PhpUnused */
    public static function getBrandAttribute()
    {
        return self::getValue('brand');
    }

    /** @noinspection PhpUnused */
    public static function getColorAttribute()
    {
        return self::getValue('color');
    }

    /** @noinspection PhpUnused */
    public static function getSizeAttribute()
    {
        return self::getValue('size');
    }
    /** @noinspection PhpUnused */
    public static function getCronFeed()
    {
        return (int) self::getValue('cron_feed');
    }

    /** @noinspection PhpUnused */
    public static function getUpdateFeed()
    {
        return self::getValue('update_feed');
    }

    /** @noinspection PhpUnused */
    public static function getCronReview()
    {
        return (int) self::getValue('cron_review');
    }

    /** @noinspection PhpUnused */
    public static function getUpdateReview()
    {
        return self::getValue('update_review');
    }
}
