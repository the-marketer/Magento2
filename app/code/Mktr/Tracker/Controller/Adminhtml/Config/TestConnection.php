<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

namespace Mktr\Tracker\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class TestConnection extends Action
{
    const ADMIN_RESOURCE = 'Mktr_Tracker::mktr_tracker';

    private $redirectFactory;
    private $scopeConfig;
    private $httpClient;
    private $logger;

    public function __construct(
        Context $context,
        RedirectFactory $redirectFactory,
        ScopeConfigInterface $scopeConfig,
        Curl $httpClient,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->redirectFactory = $redirectFactory;
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function execute()
    {
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeCode = null;
        $redirectParams = ['section' => 'mktr_tracker'];

        if ($this->getRequest()->getParam('store') !== null) {
            $scopeType = ScopeInterface::SCOPE_STORE;
            $scopeCode = $this->getRequest()->getParam('store');
            $redirectParams['store'] = $scopeCode;
        } elseif ($this->getRequest()->getParam('website') !== null) {
            $scopeType = ScopeInterface::SCOPE_WEBSITE;
            $scopeCode = $this->getRequest()->getParam('website');
            $redirectParams['website'] = $scopeCode;
        }

        $restKey = $this->scopeConfig->getValue('mktr_tracker/tracker/rest_key', $scopeType, $scopeCode);
        $customerId = $this->scopeConfig->getValue('mktr_tracker/tracker/customer_id', $scopeType, $scopeCode);

        if (empty($restKey) || empty($customerId)) {
            $this->messageManager->addErrorMessage(__('Please fill in REST API Key and Customer ID before testing the connection.'));
            return $this->redirectFactory->create()->setPath('adminhtml/system_config/edit', $redirectParams);
        }

        try {
            $this->httpClient->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->httpClient->setOption(CURLOPT_TIMEOUT, 5);
            $this->httpClient->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $this->httpClient->setOption(CURLOPT_SSL_VERIFYHOST, 2);
            $this->httpClient->get(
                'https://t.themarketer.com/api/v1/health?' . http_build_query(['k' => $restKey, 'u' => $customerId])
            );

            $statusCode = $this->httpClient->getStatus();
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->messageManager->addSuccessMessage(__('Connection successful. Credentials are valid.'));
            } else {
                $this->messageManager->addErrorMessage(__('Connection failed. Please verify your credentials and endpoint availability.'));
            }
        } catch (\Exception $e) {
            $this->logger->warning('TheMarketer connection test failed', ['message' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Connection failed. Please verify your credentials and endpoint availability.'));
        }

        return $this->redirectFactory->create()->setPath('adminhtml/system_config/edit', $redirectParams);
    }
}
