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
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Mktr\Tracker\Model\OAuth\Client as OAuthClient;

class Callback extends Action
{
    const ADMIN_RESOURCE = 'Mktr_Tracker::mktr_tracker';

    private $redirectFactory;
    private $oauthClient;
    private $backendSession;
    private $configWriter;
    private $encryptor;

    public function __construct(
        Context $context,
        RedirectFactory $redirectFactory,
        OAuthClient $oauthClient,
        Session $backendSession,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->redirectFactory = $redirectFactory;
        $this->oauthClient = $oauthClient;
        $this->backendSession = $backendSession;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
    }

    public function execute()
    {
        $redirectParams = ['section' => 'mktr_tracker'];
        $scopeType = $this->backendSession->getMktrOauthScopeType() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeCode = $this->backendSession->getMktrOauthScopeCode();

        if ($scopeType !== ScopeConfigInterface::SCOPE_TYPE_DEFAULT && $scopeCode !== null) {
            if ($scopeType === \Magento\Store\Model\ScopeInterface::SCOPE_STORE) {
                $redirectParams['store'] = $scopeCode;
            } else {
                $redirectParams['website'] = $scopeCode;
            }
        }

        $redirect = $this->redirectFactory->create()
            ->setPath('adminhtml/system_config/edit', $redirectParams);

        $error = (string) $this->getRequest()->getParam('error');
        $state = (string) $this->getRequest()->getParam('state');
        $code = (string) $this->getRequest()->getParam('code');
        $expectedState = (string) $this->backendSession->getMktrOauthState();
        $codeVerifier = (string) $this->backendSession->getMktrOauthCodeVerifier();
        $redirectUri = (string) $this->backendSession->getMktrOauthRedirectUri();

        $this->clearOauthSession();

        if ($error !== '') {
            $this->messageManager->addErrorMessage(__('Connection to theMarketer was cancelled.'));
            return $redirect;
        }

        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            $this->messageManager->addErrorMessage(__('Invalid OAuth state. Please try connecting again.'));
            return $redirect;
        }

        if ($code === '' || $codeVerifier === '') {
            $this->messageManager->addErrorMessage(__('Missing authorization code. Please try connecting again.'));
            return $redirect;
        }

        if ($redirectUri === '') {
            $this->messageManager->addErrorMessage(__('Missing OAuth session. Please try connecting again.'));
            return $redirect;
        }

        $credentials = $this->oauthClient->exchangeAuthorizationCode($code, $redirectUri, $codeVerifier);

        if ($credentials === null) {
            $this->messageManager->addErrorMessage(__('Could not retrieve credentials from theMarketer. Please try again.'));
            return $redirect;
        }

        $this->saveEncryptedValue('mktr_tracker/tracker/tracking_key', $credentials['tracking_key'], $scopeType, $scopeCode);
        $this->saveEncryptedValue('mktr_tracker/tracker/rest_key', $credentials['rest_key'], $scopeType, $scopeCode);
        $this->saveEncryptedValue('mktr_tracker/tracker/customer_id', $credentials['customer_id'], $scopeType, $scopeCode);
        $this->configWriter->save('mktr_tracker/tracker/status', 1, $scopeType, $scopeCode);

        $this->messageManager->addSuccessMessage(__('theMarketer credentials were imported successfully.'));

        return $redirect;
    }

    private function saveEncryptedValue(string $path, string $value, string $scopeType, $scopeCode): void
    {
        $this->configWriter->save($path, $this->encryptor->encrypt($value), $scopeType, $scopeCode);
    }

    private function clearOauthSession(): void
    {
        $this->backendSession->unsMktrOauthState();
        $this->backendSession->unsMktrOauthCodeVerifier();
        $this->backendSession->unsMktrOauthScopeType();
        $this->backendSession->unsMktrOauthScopeCode();
        $this->backendSession->unsMktrOauthRedirectUri();
    }
}
