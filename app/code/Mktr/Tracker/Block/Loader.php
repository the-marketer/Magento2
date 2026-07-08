<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Block;

use Magento\Framework\App\Request\Http as HttpRequest;
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
        "hyva_checkout_index" => "__sm__initiate_checkout",
        "hyva_checkout_default" => "__sm__initiate_checkout",
        "hyva_checkout_index_index" => "__sm__initiate_checkout",
        "hyva_reactcheckout_reactcheckout_index" => "__sm__initiate_checkout",
        "hyvareactcheckout_reactcheckout_index" => "__sm__initiate_checkout",
        "hyvaReactCheckout_reactcheckout_index"=> "__sm__initiate_checkout",
        "hyva_react_checkout_react_checkout_index" => "__sm__initiate_checkout",
        "hyva_react_checkout_reactcheckout_index" => "__sm__initiate_checkout",
        "firecheckout_index_index" => "__sm__initiate_checkout",
        "searchanise_result_index" => "__sm__search",
        "catalogsearch_result_index" => "__sm__search"
    ];

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var HttpRequest
     */
    private $request;

    public function __construct(Context $context, Data $helper, HttpRequest $request, array $data = [])
    {
        $this->helper = $helper;
        $this->request = $request;
        parent::__construct($context, $data);
    }

    private function getEventName()
    {
        return self::actions[$this->getActionName()] ?? null;
    }

    private function getActionName()
    {
        return $this->request->getFullActionName();
    }

    protected function _toHtml(): string
    {
        if ($this->helper->getConfig->getStatus() === 0 || empty($this->helper->getConfig->getKey())) {
            return '';
        }

        $lines = ['window.mktr = window.mktr || { pending: [], retryCount: 0, version: "1.2.3" };', 'window.dataLayer = window.dataLayer || [];'];
        $lines[] = 'window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr", "Google", i); } } };';
        $lines[] = 'window.mktr.eventPush = function (data = {}) {
            if (typeof dataLayer != "undefined") { dataLayer.push(data); } else {
                window.mktr.pending.push(data); setTimeout(window.mktr.retry, 1000);
            }
        }';

        $baseURL = json_encode((string) $this->helper->getBaseUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $lines[] = 'window.mktr.loadScript = function (mktrPage = null) {
            if (mktrPage !== null) { let time = (new Date()).getTime(); let url = ' . $baseURL . ' + "mktr/api/" + mktrPage;
                let add = document.createElement("script"); add.async = true; add.src = url + ( url.includes("?") ? "&mk=" : "?mk=") + time;
                let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); } }';
        $lines[] = 'window.mktr.postAction = function (mktrPage = null) {
            if (mktrPage !== null && typeof fetch !== "undefined") {
                fetch(' . $baseURL . ' + "mktr/api/" + mktrPage, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {"X-Requested-With": "XMLHttpRequest"}
                });
            }
        };';
        $lines[] = 'window.mktr.loadEvents = function () { window.mktr.loadScript("LoadEvents"); };';
        $lines[] = 'window.mktr.retry = function () {
            if (typeof dataLayer != "undefined") {
                for (let data of window.mktr.pending) { dataLayer.push(data); }        
            } else if (window.mktr.retryCount < 6) {
                window.mktr.retryCount++; setTimeout(window.mktr.retry, 1000);
            }
        };';

        $trackingKey = json_encode((string) $this->helper->getConfig->getKey(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $lines[] = vsprintf($this->helper->getConfig->getLoader(), [$trackingKey]);

        $eventName = $this->getEventName();

        if ($eventName != null) {
            $lines[] = "window.mktr.eventPush(" . $this->helper->getManager->getEvent($eventName)->toJson() . ");";
        }

        $lines[] = "
        window.isLoad = false;
        if (typeof require !== 'undefined') {
            require(['Magento_Customer/js/customer-data'], function (customerData) {
                var cart = customerData.get('cart');
                var count = cart().summary_count;
                cart.subscribe(function () {
                    if (cart().summary_count !== count && window.isLoad) { count = cart().summary_count; window.mktr.loadEvents(); } else { window.isLoad = true; }
                });
            });
        }
        setTimeout(window.mktr.loadEvents, 1000);
        ";
        $selector = $this->helper->getConfig->getSelectors();

        if (!empty($selector)) {
            $lines[] = 'window.addEventListener("click", function(event){ 
                let selector1 = ' . json_encode($selector, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';
                let closestElem1 = event.target.closest(selector1);
                let closestElem2 = event.target.matches(selector1);
                if (closestElem1 || closestElem2) { setTimeout(window.mktr.loadEvents, 3000); }
            });';
        }

        $wh = [$this->helper->getSpace(), implode($this->helper->getSpace(), $lines)];
        $rep = ["%space%", "%implode%"];
        return str_replace($rep, $wh, '<!-- Mktr Script Start -->%space%<script type="text/javascript">%space%%implode%%space%</script>%space%<!-- Mktr Script END -->');
    }
}
