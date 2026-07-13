<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

namespace Mktr\Tracker\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mktr\Tracker\Model\OAuth\Client as OAuthClient;

class Start extends Action
{
    const ADMIN_RESOURCE = 'Mktr_Tracker::mktr_tracker';

    private $redirectFactory;
    private $oauthClient;
    private $backendSession;
    private $storeManager;

    public function __construct(
        Context $context,
        RedirectFactory $redirectFactory,
        OAuthClient $oauthClient,
        Session $backendSession,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->redirectFactory = $redirectFactory;
        $this->oauthClient = $oauthClient;
        $this->backendSession = $backendSession;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $state = bin2hex(random_bytes(16));
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $this->backendSession->setMktrOauthState($state);
        $this->backendSession->setMktrOauthCodeVerifier($codeVerifier);
        $this->backendSession->setMktrOauthScopeType($this->resolveScopeType());
        $this->backendSession->setMktrOauthScopeCode($this->resolveScopeCode());

        $redirectUri = $this->buildCallbackUrl(
            $this->backendSession->getMktrOauthScopeType(),
            $this->backendSession->getMktrOauthScopeCode()
        );
        $this->backendSession->setMktrOauthRedirectUri($redirectUri);

        $authorizeUrl = $this->oauthClient->getAuthorizeUrl([
            'platform' => 'magento2',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'store_url' => $this->getStoreBaseUrl(),
        ]);

        return $this->redirectFactory->create()->setUrl($authorizeUrl);
    }

    private function resolveScopeType(): string
    {
        if ($this->getRequest()->getParam('store') !== null) {
            return ScopeInterface::SCOPE_STORE;
        }

        if ($this->getRequest()->getParam('website') !== null) {
            return ScopeInterface::SCOPE_WEBSITE;
        }

        return ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    }

    private function resolveScopeCode()
    {
        if ($this->getRequest()->getParam('store') !== null) {
            return $this->getRequest()->getParam('store');
        }

        if ($this->getRequest()->getParam('website') !== null) {
            return $this->getRequest()->getParam('website');
        }

        return null;
    }

    private function getStoreBaseUrl(): string
    {
        $storeId = $this->getRequest()->getParam('store');

        if ($storeId !== null) {
            return $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        }

        if ($this->getRequest()->getParam('website') !== null) {
            $website = $this->storeManager->getWebsite($this->getRequest()->getParam('website'));
            $store = $this->storeManager->getStore($website->getDefaultStore()->getId());

            return $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        }

        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
    }

    private function buildCallbackUrl(string $scopeType, $scopeCode): string
    {
        $params = [
            '_nosid' => true,
            '_secure' => true,
        ];

        if ($scopeType === ScopeInterface::SCOPE_STORE && $scopeCode !== null) {
            $params['store'] = $scopeCode;
        } elseif ($scopeType === ScopeInterface::SCOPE_WEBSITE && $scopeCode !== null) {
            $params['website'] = $scopeCode;
        }

        return $this->_url->getUrl('mktr/connect/callback', $params);
    }
}
