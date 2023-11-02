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
        "cms_index_index" => "__sm__view_homepage",
        "catalog_category_view" => "__sm__view_category",
        "catalog_product_view" => "__sm__view_product",
        "onepagecheckout_index_index" => "__sm__initiate_checkout",
        "checkout_onepage_index" => "__sm__initiate_checkout",
        "checkout_index_index" => "__sm__initiate_checkout",
        "onestepcheckout_index_index" => "__sm__initiate_checkout",
        "hyva_checkout_index_index" => "__sm__initiate_checkout",
        "searchanise_result_index" => "__sm__search",
        "catalogsearch_result_index" => "__sm__search"
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

        $lines = [ 'window.mktr = window.mktr || { pending: [], retryCount: 0 };' ];
        $lines[] = 'window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr", "Google", i); } } };';
        $lines[] = 'window.mktr.eventPush = function (data = {}) {
            if (typeof dataLayer != "undefined") { dataLayer.push(data); } else {
                window.mktr.pending.push(data); setTimeout(window.mktr.retry, 1000);
            }
        }';

        $baseURL = self::getHelp()->getBaseUrl;

        $lines[] = 'window.mktr.loadScript = function (mktrPage = null) {
            if (mktrPage !== null) { let time = (new Date()).getTime(); let url = "'.$baseURL.'mktr/api/"+mktrPage;
                let add = document.createElement("script"); add.async = true; add.src = url + ( url.includes("?") ? "&mk=" : "?mk=") + time;
                let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); } }';
        $lines[] = 'window.mktr.loadEvents = function () { window.mktr.loadScript("LoadEvents"); };';
        $lines[] = 'window.mktr.retry = function () {
            if (typeof dataLayer != "undefined") {
                for (let data of window.mktr.pending) { dataLayer.push(data); }        
            } else if (window.mktr.retryCount < 6) {
                window.mktr.retryCount++; setTimeout(window.mktr.retry, 1000);
            }
        };';

        $lines[] = vsprintf(self::getHelp()->getConfig->getLoader(), [self::getHelp()->getConfig->getKey()]);

        $loadJS = [];

        $eventName = self::getEventName();

        if ($eventName != null) {
            $lines[] = "window.mktr.eventPush(".self::getHelp()->getManager->getEvent($eventName)->toJson().");";
        }

        $lines[] = "
        window.isLoad = false;
        require(['Magento_Customer/js/customer-data'], function (customerData) {
            var cart = customerData.get('cart');
            var count = cart().summary_count;
            cart.subscribe(function () {
                if (cart().summary_count !== count && window.isLoad) { count = cart().summary_count; window.mktr.loadEvents(); } else { window.isLoad = true; }
            });
        });
        setTimeout(window.mktr.loadEvents, 1000);
        ";
        $lines[] = 'window.addEventListener("click", function(event){ if (event.target.matches("' . str_replace('"','\"',self::getHelp()->getConfig->getSelectors()) . '")) { setTimeout(window.mktr.loadEvents, 3000); } });';

        $lines[] = 'window.MktrDebug = function () { if (typeof dataLayer != undefined) { for (let i of dataLayer) { console.log("Mktr","Google",i); } } };';

        // $lines[] = 'console.log("Mktr","ActionName","'.self::actionName().'");';

        $wh =  [self::getHelp()->getSpace(), implode(self::getHelp()->getSpace(), $lines)];
        $rep = ["%space%","%implode%"];
        /** @noinspection JSUnresolvedVariable */
        return str_replace($rep, $wh, '<!-- Mktr Script Start -->%space%<script type="text/javascript">%space%%implode%%space%</script>%space%<!-- Mktr Script END -->');
    }
}
