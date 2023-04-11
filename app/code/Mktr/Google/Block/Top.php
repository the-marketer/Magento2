<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Google\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Top extends Template
{
    private $config;
    public function __construct(Context $context, array $data = [])
    {
        $this->config = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
 
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
 
        $storeID = $storeManager->getStore()->getStoreId();
        
        $status = $this->config->getValue('mktr_google/google/status', 'store', $storeID);

        if ($status == 0) {
            return '';
        }

        $key = $this->config->getValue('mktr_google/google/tracking', 'store', $storeID);
        
        return "<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','".$key."');</script>
<!-- End Google Tag Manager -->";
    }
}
