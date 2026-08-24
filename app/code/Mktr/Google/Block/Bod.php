<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Google\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;

class Bod extends Template
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Escaper
     */
    private $escaper;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Escaper $escaper,
        array $data = []
    ) {
        $this->config = $context->getScopeConfig();
        $this->storeManager = $storeManager;
        $this->escaper = $escaper;
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $storeID = $this->storeManager->getStore()->getStoreId();

        $status = $this->config->getValue('mktr_google/google/status', 'store', $storeID);

        if ($status == 0) {
            return '';
        }
        $key = rawurlencode((string) $this->config->getValue('mktr_google/google/tracking', 'store', $storeID));
        $url = $this->escaper->escapeUrl('https://www.googletagmanager.com/ns.html?id=' . $key);

        return '<!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="' . $url . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->';
    }
}
