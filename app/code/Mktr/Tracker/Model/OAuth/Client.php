<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

namespace Mktr\Tracker\Model\OAuth;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Client
{
    private const DEFAULT_AUTHORIZE_URL = 'https://app.themarketer.com/oauth/plugin-connect';
    private const DEFAULT_TOKEN_URL = 'https://t.themarketer.com/api/v1/plugin-oauth/token';
    private const DEFAULT_CLIENT_ID = 'plugin-magento2';
    private const PLATFORM = 'magento2';

    private $scopeConfig;
    private $httpClient;
    private $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Curl $httpClient,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function getAuthorizeUrl(array $params): string
    {
        $baseUrl = (string) $this->scopeConfig->getValue('mktr_tracker/oauth/authorize_url');

        if ($baseUrl === '') {
            $baseUrl = self::DEFAULT_AUTHORIZE_URL;
        }

        return rtrim($baseUrl, '?') . '?' . http_build_query($params);
    }

    /**
     * @return array{tracking_key:string,rest_key:string,customer_id:string}|null
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri, string $codeVerifier): ?array
    {
        $tokenUrl = (string) $this->scopeConfig->getValue('mktr_tracker/oauth/token_url');

        if ($tokenUrl === '') {
            $tokenUrl = self::DEFAULT_TOKEN_URL;
        }

        $clientId = (string) $this->scopeConfig->getValue('mktr_tracker/oauth/client_id');

        if ($clientId === '') {
            $clientId = self::DEFAULT_CLIENT_ID;
        }

        $payload = json_encode([
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
            'platform' => self::PLATFORM,
            'client_id' => $clientId,
        ]);

        try {
            $this->httpClient->setHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
            $this->httpClient->setOption(CURLOPT_TIMEOUT, 15);
            $this->httpClient->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->httpClient->post($tokenUrl, $payload);

            $status = $this->httpClient->getStatus();
            $body = $this->httpClient->getBody();

            if ($status < 200 || $status >= 300) {
                $this->logger->warning('theMarketer plugin OAuth token exchange failed', [
                    'status' => $status,
                    'body' => $body,
                ]);

                return null;
            }

            $response = json_decode($body, true);

            if (!is_array($response)) {
                return null;
            }

            if (isset($response['data']) && is_array($response['data'])) {
                $response = $response['data'];
            }

            if (
                empty($response['tracking_key'])
                || empty($response['rest_key'])
                || empty($response['customer_id'])
            ) {
                return null;
            }

            return [
                'tracking_key' => (string) $response['tracking_key'],
                'rest_key' => (string) $response['rest_key'],
                'customer_id' => (string) $response['customer_id'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('theMarketer plugin OAuth token exchange exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
