<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mktr\Tracker\Helper\Data;

class Loader extends Template
{
    const actions = [
        /* "checkout_cart_index" => "Cart", */
        "cms_index_index" => "__sm__view_homepage",
        "catalog_category_view" => "__sm__view_category",
        "catalog_product_view" => "__sm__view_product",
        "onepagecheckout_index_index" => "__sm__initiate_checkout",
        "checkout_onepage_index" => "__sm__initiate_checkout",
        /** TODO: Magento 2 - "checkout_index_index" => "__sm__initiate_checkout" */
        "checkout_index_index" => "__sm__initiate_checkout",
        "catalogsearch_result_index" => "__sm__search",
        "onestepcheckout_index_index" => "__sm__initiate_checkout",
        "searchanise_result_index" => "__sm__search"
    ];

    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $actionName = null;

    public function __construct(Context $context, Data $help, array $data = [])
    {
        self::$ins['Help'] = $help;
        parent::__construct($context, $data);
    }

    public static function getEventName()
    {
        return self::actions[self::actionName()] ?? null;
    }

    public static function actionName()
    {
        if (self::$actionName === null) {   /** TODO: Magento 2 */
            self::$actionName = self::getHelp()->getRequest->getFullActionName();
        }
        return self::$actionName;
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    /** @noinspection PhpUnused */
    protected function _toHtml(): string
    {
        if (self::getHelp()->getConfig->getStatus() === 0 || empty(self::getHelp()->getConfig->getKey())) {
            return '';
        }

        $lines = [];

        $lines[] = vsprintf(self::getHelp()->getConfig->getLoader(), [self::getHelp()->getConfig->getKey()]);

        $loadJS = [];

        $eventName = self::getEventName();

        if ($eventName != null) {
            $lines[] = "dataLayer.push(".self::getHelp()->getManager->getEvent($eventName)->toJson().");";
        }

        // $lines[] = "console.log('|".self::actionName()."|','eax');";

        foreach (self::getHelp()->getConfig->getEventsObs() as $event => $Name) {
            $fName = self::getHelp()->getSessionName.$event;

            $eventData = self::getHelp()->getSession->{"get".$fName}();
            if ($eventData) {
                $lines[] = "dataLayer.push(".self::getHelp()->getManager->getEvent($Name[1], $eventData)->toJson().");";
                if ($Name[0]) {
                    $loadJS[$event] = true;
                } else {
                    self::getHelp()->getSession->{"uns".$fName}();
                }
            }
        }

        $baseURL = self::getHelp()->getBaseUrl;

        foreach ($loadJS as $k => $v) {
            $lines[] = '(function(){ let add = document.createElement("script"); add.async = true; add.src = "'.$baseURL.'mktr/api/'.$k.'"; let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); })();';
        }

        $lines[] = "
        window.isLoad = false;
        require(['Magento_Customer/js/customer-data'], function (customerData) {
            var cart = customerData.get('cart');
            var count = cart().summary_count;
            cart.subscribe(function () {
                if (cart().summary_count !== count && window.isLoad) {
                    count = cart().summary_count;
                    (function(){ let add = document.createElement('script');add.async = true; add.src = '".$baseURL."mktr/api/LoadEvents';
                    let s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(add,s); })();
                } else {
                    window.isLoad = true;
                }
            });
        });";

        $lines[] = 'window.MktrDebug = function () { if (typeof dataLayer != undefined) { for (let i of dataLayer) { console.log("Mktr","Google",i); } } };';

        // $lines[] = 'console.log("Mktr","ActionName","'.self::actionName().'");';

        $wh =  [self::getHelp()->getSpace(), implode(self::getHelp()->getSpace(), $lines)];
        $rep = ["%space%","%implode%"];
        /** @noinspection JSUnresolvedVariable */
        return str_replace($rep, $wh, '<!-- Mktr Script Start -->%space%<script type="text/javascript">%space%%implode%%space%</script>%space%<!-- Mktr Script END -->');
    }
}
