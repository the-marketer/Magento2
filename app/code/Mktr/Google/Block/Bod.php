<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Google\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Bod extends Template
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

        return '<!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.$key.'" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->';
    }
}
