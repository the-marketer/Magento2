<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

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

    private const LOADER = '(function(d, s, i) { var f = d.getElementsByTagName(s)[0], j = d.createElement(s);j.async = true; j.src = "https://t.themarketer.com/t/j/" + i; f.parentNode.insertBefore(j, f);})(document, "script", "%s")';

    const configNames = [
        'status' => 'mktr_tracker/tracker/status',
        'tracking_key' => 'mktr_tracker/tracker/tracking_key',
        'rest_key' => 'mktr_tracker/tracker/rest_key',
        'customer_id'=>'mktr_tracker/tracker/customer_id',
        'cron_feed' => 'mktr_tracker/tracker/cron_feed',
        'update_feed' => 'mktr_tracker/tracker/update_feed',
        'cron_review' => 'mktr_tracker/tracker/cron_review',
        'update_review' => 'mktr_tracker/tracker/update_review',
        'cron_subscribe' => 'mktr_tracker/tracker/cron_subscribe',
        'update_subscribe' => 'mktr_tracker/tracker/update_subscribe',
        'opt_in' => 'mktr_tracker/tracker/opt_in',
        'push_status' => 'mktr_tracker/tracker/push_status',
        'default_stock' => 'mktr_tracker/tracker/default_stock',
        'allow_export' => 'mktr_tracker/tracker/allow_export',
        'stock_source' => 'mktr_tracker/tracker/stock_source',
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
        'size' => null,
        'stock_source' => 'all'
    ];

    const observerGetEvents = [
        "addToCart"=> [false, "__sm__add_to_cart"],
        "removeFromCart"=> [false, "__sm__remove_from_cart"],
        "addToWishlist"=> [false, "__sm__add_to_wishlist"],
        "removeFromWishlist"=> [false, "__sm__remove_from_wishlist"],
        "saveOrder"=> [true, "__sm__order"],
        "setEmail"=> [true, "__sm__set_email"]
    ];

    const discountRules = [
        0 => "fixedValue",
        1 => "percentage",
        2 => "freeShipping"
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array<int|string, array<string, mixed>>
     */
    private $configCache = [];

    /**
     * @var int|string|null
     */
    private $scopeCode = null;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /** @noinspection PhpUnused */
    public function getDiscountRules(): array
    {
        return self::discountRules;
    }

    /** @noinspection PhpUnused */
    public function getDateStart(): string
    {
        return self::DATE_START_FORMAT;
    }

    /** @noinspection PhpUnused */
    public function getDateEnd(): string
    {
        return self::DATE_END_FORMAT;
    }

    /** @noinspection PhpUnused */
    public function getEventsObs(): array
    {
        return self::observerGetEvents;
    }

    /** @noinspection PhpUnused */
    public function getLoader(): string
    {
        return self::LOADER;
    }

    /** @noinspection PhpUnused */
    public function getFireBase(): string
    {
        return self::FireBase;
    }

    /** @noinspection PhpUnused */
    public function getFireBaseMessaging(): string
    {
        return self::FireBaseMessaging;
    }

    /**
     * @param int|string $store
     */
    public function setScopeCode($store): void
    {
        $this->scopeCode = $store;
    }

    public function getScopeCode()
    {
        if ($this->scopeCode === null) {
            $this->scopeCode = $this->storeManager->getStore()->getStoreId();
        }

        return $this->scopeCode;
    }

    private function getScopeCacheKey()
    {
        return (string) $this->getScopeCode();
    }

    public function getStoreValue($name, $store)
    {
        if (isset(self::configNames[$name])) {
            return $this->scopeConfig->getValue(self::configNames[$name], self::scopeType, $store);
        }

        return $this->scopeConfig->getValue($name, self::scopeType, $store);
    }

    public function getValue($name)
    {
        $scopeKey = $this->getScopeCacheKey();

        if (!array_key_exists($scopeKey, $this->configCache)) {
            $this->configCache[$scopeKey] = [];
        }

        if (!array_key_exists($name, $this->configCache[$scopeKey])) {
            if (isset(self::configNames[$name])) {
                $this->configCache[$scopeKey][$name] = $this->scopeConfig->getValue(
                    self::configNames[$name],
                    self::scopeType,
                    $this->getScopeCode()
                );
                if (in_array($name, ['color', 'size', 'brand'], true)) {
                    $this->configCache[$scopeKey][$name] = $this->configCache[$scopeKey][$name] !== null
                        && $this->configCache[$scopeKey][$name] !== ''
                        ? explode("|", $this->configCache[$scopeKey][$name])
                        : [];
                }
            } else {
                $this->configCache[$scopeKey][$name] = $this->scopeConfig->getValue(
                    $name,
                    self::scopeType,
                    $this->getScopeCode()
                );
            }
        }

        return $this->configCache[$scopeKey][$name];
    }

    /** @noinspection PhpUnused */
    public function getStatus(): int
    {
        return (int) $this->getValue('status');
    }

    /** @noinspection PhpUnused */
    public function getKey()
    {
        return $this->getValue('tracking_key');
    }

    /** @noinspection PhpUnused */
    public function getRestKey()
    {
        return $this->getValue('rest_key');
    }

    /** @noinspection PhpUnused */
    public function getOptIn(): int
    {
        return (int) $this->getValue('opt_in');
    }

    /** @noinspection PhpUnused */
    public function getPushStatus(): int
    {
        return (int) $this->getValue('push_status');
    }

    /** @noinspection PhpUnused */
    public function getDefaultStock(): int
    {
        return (int) $this->getValue('default_stock');
    }

    /** @noinspection PhpUnused */
    public function getAllowExport(): int
    {
        return (int) $this->getValue('allow_export');
    }

    /** @noinspection PhpUnused */
    public function getCustomerId()
    {
        return $this->getValue('customer_id');
    }

    /** @noinspection PhpUnused */
    public function getBrandAttribute()
    {
        return $this->getValue('brand');
    }

    /** @noinspection PhpUnused */
    public function getColorAttribute()
    {
        return $this->getValue('color');
    }

    /** @noinspection PhpUnused */
    public function getSizeAttribute()
    {
        return $this->getValue('size');
    }

    /** @noinspection PhpUnused */
    public function getCronFeed(): int
    {
        return (int) $this->getValue('cron_feed');
    }

    /** @noinspection PhpUnused */
    public function getSelectors()
    {
        return $this->getValue('selectors');
    }

    /** @noinspection PhpUnused */
    public function getUpdateFeed()
    {
        return $this->getValue('update_feed');
    }

    /** @noinspection PhpUnused */
    public function getCronReview(): int
    {
        return (int) $this->getValue('cron_review');
    }

    /** @noinspection PhpUnused */
    public function getCronSubscribe(): int
    {
        return (int) $this->getValue('cron_subscribe');
    }

    /** @noinspection PhpUnused */
    public function getUpdateReview()
    {
        return $this->getValue('update_review');
    }

    /** @noinspection PhpUnused */
    public function getUpdateSubscribe()
    {
        return $this->getValue('update_subscribe');
    }

    /** @noinspection PhpUnused */
    public function getStockSource()
    {
        return $this->getValue('stock_source');
    }
}
