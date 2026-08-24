<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

namespace Mktr\Tracker\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;
use Mktr\Tracker\Model\Config as TrackerConfig;
use Mktr\Tracker\Model\FileSystem as TrackerFileSystem;
use Psr\Log\LoggerInterface;

class TestConnection extends Action
{
    const ADMIN_RESOURCE = 'Mktr_Tracker::mktr_tracker';
    private const API_ENDPOINT = 'https://t.themarketer.com/api/v1/product_reviews';
    private const TRACKING_SCRIPT_URL = 'https://t.themarketer.com/t/j/';

    private $redirectFactory;
    private $scopeConfig;
    private $httpClient;
    private $trackerConfig;
    private $trackerFileSystem;
    private $logger;

    public function __construct(
        Context $context,
        RedirectFactory $redirectFactory,
        ScopeConfigInterface $scopeConfig,
        Curl $httpClient,
        TrackerConfig $trackerConfig,
        TrackerFileSystem $trackerFileSystem,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->redirectFactory = $redirectFactory;
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->trackerConfig = $trackerConfig;
        $this->trackerFileSystem = $trackerFileSystem;
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

        $trackingKey = $this->trackerConfig->decryptSensitiveValue(
            $this->scopeConfig->getValue('mktr_tracker/tracker/tracking_key', $scopeType, $scopeCode)
        );
        $status = (int)$this->scopeConfig->getValue('mktr_tracker/tracker/status', $scopeType, $scopeCode);
        $pushStatus = (int)$this->scopeConfig->getValue('mktr_tracker/tracker/push_status', $scopeType, $scopeCode);
        $cronFeed = (int)$this->scopeConfig->getValue('mktr_tracker/tracker/cron_feed', $scopeType, $scopeCode);
        $cronReview = (int)$this->scopeConfig->getValue('mktr_tracker/tracker/cron_review', $scopeType, $scopeCode);
        $cronSubscribe = (int)$this->scopeConfig->getValue('mktr_tracker/tracker/cron_subscribe', $scopeType, $scopeCode);
        $updateFeed = $this->scopeConfig->getValue('mktr_tracker/tracker/update_feed', $scopeType, $scopeCode);
        $updateReview = $this->scopeConfig->getValue('mktr_tracker/tracker/update_review', $scopeType, $scopeCode);
        $updateSubscribe = $this->scopeConfig->getValue('mktr_tracker/tracker/update_subscribe', $scopeType, $scopeCode);
        $restKey = $this->trackerConfig->decryptSensitiveValue(
            $this->scopeConfig->getValue('mktr_tracker/tracker/rest_key', $scopeType, $scopeCode)
        );
        $customerId = $this->trackerConfig->decryptSensitiveValue(
            $this->scopeConfig->getValue('mktr_tracker/tracker/customer_id', $scopeType, $scopeCode)
        );
        $results = [];

        if (empty($restKey) || empty($customerId)) {
            $this->messageManager->addErrorMessage(__('Please fill in REST API Key and Customer ID before testing the connection.'));
            return $this->redirectFactory->create()->setPath('adminhtml/system_config/edit', $redirectParams);
        }

        $this->addResult(
            $results,
            'module_status',
            $status === 1 ? 'ok' : 'warning',
            $status === 1 ? 'Tracker module is enabled.' : 'Tracker module is disabled; frontend events and cron sync will not run.'
        );

        $this->testStorage($results);
        $this->testCronConfig($results, $cronFeed, $updateFeed, 'feed');
        $this->testCronConfig($results, $cronReview, $updateReview, 'reviews');
        $this->testCronConfig($results, $cronSubscribe, $updateSubscribe, 'unsubscribes');
        $this->testPushFiles($results, $pushStatus);
        $this->testTrackingScript($results, (string)$trackingKey, $scopeType, $scopeCode);
        $this->testRestApi($results, (string)$restKey, (string)$customerId);

        $failed = array_filter($results, function ($result) {
            return $result['status'] === 'failed';
        });
        $warnings = array_filter($results, function ($result) {
            return $result['status'] === 'warning';
        });

        $logContext = [
            'scope_type' => $scopeType,
            'scope_code' => $scopeCode,
            'results' => $results,
        ];

        if ($failed) {
            $this->logger->warning('TheMarketer integration test failed', $logContext);
            $this->messageManager->addErrorMessage(
                __('TheMarketer integration test failed. Check var/log/system.log for detailed results.')
            );
        } elseif ($warnings) {
            $this->logger->warning('TheMarketer integration test passed with warnings', $logContext);
            $this->messageManager->addWarningMessage(
                __('TheMarketer integration test passed with warnings. Check var/log/system.log for details.')
            );
        } else {
            $this->logger->info('TheMarketer integration test passed', $logContext);
            $this->messageManager->addSuccessMessage(__('TheMarketer integration test passed.'));
        }

        return $this->redirectFactory->create()->setPath('adminhtml/system_config/edit', $redirectParams);
    }

    private function testRestApi(array &$results, string $restKey, string $customerId): void
    {
        try {
            $params = ['k' => $restKey, 'u' => $customerId, 't' => strtotime(date('Y-m-d'))];
            $this->prepareHttpClient();
            $this->httpClient->get(self::API_ENDPOINT . '?' . http_build_query($params));

            $statusCode = $this->httpClient->getStatus();
            $responseBody = trim((string)$this->httpClient->getBody());
            $accessDenied = in_array(strtolower($responseBody), ['access not allowed', 'false'], true);

            $this->addResult(
                $results,
                'rest_api',
                $statusCode >= 200 && $statusCode < 300 && !$accessDenied ? 'ok' : 'failed',
                $statusCode >= 200 && $statusCode < 300 && !$accessDenied
                    ? 'REST API credentials are accepted by product_reviews.'
                    : 'REST API credentials or product_reviews endpoint failed.',
                [
                    'endpoint' => self::API_ENDPOINT,
                    'method' => 'GET',
                    'status_code' => $statusCode,
                    'response_body' => substr($responseBody, 0, 1000),
                ]
            );
        } catch (\Exception $e) {
            $this->addResult(
                $results,
                'rest_api',
                'failed',
                'REST API request threw an exception.',
                ['message' => $e->getMessage()]
            );
        }
    }

    private function testTrackingScript(array &$results, string $trackingKey, string $scopeType, $scopeCode): void
    {
        if ($trackingKey === '') {
            $this->addResult($results, 'tracking_script', 'warning', 'Tracking API Key is empty; frontend tracking loader cannot be tested.');
            return;
        }

        try {
            $endpoint = self::TRACKING_SCRIPT_URL . rawurlencode($trackingKey);
            $attempts = [];
            $referers = $this->getTrackingScriptReferers($scopeType, $scopeCode);
            $statusCode = 0;
            $responseBody = '';
            $successfulReferer = null;

            foreach ($referers as $referer) {
                $this->prepareHttpClient();
                if ($referer !== '') {
                    $this->httpClient->setOption(CURLOPT_REFERER, $referer);
                }
                $this->httpClient->setOption(CURLOPT_USERAGENT, 'Mozilla/5.0 TheMarketer Magento connection test');
                $this->httpClient->get($endpoint);

                $statusCode = $this->httpClient->getStatus();
                $responseBody = substr(trim((string)$this->httpClient->getBody()), 0, 1000);
                $attempts[] = [
                    'referer' => $referer,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                ];

                if ($statusCode >= 200 && $statusCode < 300) {
                    $successfulReferer = $referer;
                    break;
                }
            }

            $this->addResult(
                $results,
                'tracking_script',
                $statusCode >= 200 && $statusCode < 300 ? 'ok' : 'failed',
                $statusCode >= 200 && $statusCode < 300
                    ? 'Tracking loader script is reachable.'
                    : 'Tracking loader script is not reachable.',
                [
                    'endpoint' => self::TRACKING_SCRIPT_URL . '[redacted]',
                    'method' => 'GET',
                    'referer' => $successfulReferer ?? ($referers[0] ?? ''),
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'attempts' => $attempts,
                ]
            );
        } catch (\Exception $e) {
            $this->addResult(
                $results,
                'tracking_script',
                'failed',
                'Tracking loader request threw an exception.',
                ['message' => $e->getMessage()]
            );
        }
    }

    private function getTrackingScriptReferers(string $scopeType, $scopeCode): array
    {
        $referers = [];
        foreach (['web/secure/base_url', 'web/unsecure/base_url'] as $path) {
            $referer = trim((string)$this->scopeConfig->getValue($path, $scopeType, $scopeCode));
            if ($referer !== '' && !in_array($referer, $referers, true)) {
                $referers[] = $referer;
            }
        }

        return $referers ?: [''];
    }

    private function testStorage(array &$results): void
    {
        $testFile = 'connection-test.tmp';

        try {
            $storage = $this->trackerFileSystem->setWorkDirectory('Storage');
            $storagePath = $storage->getPath();
            $storage->writeFile($testFile, (string)time());

            if (!$storage->isExists($testFile)) {
                $this->addResult(
                    $results,
                    'storage',
                    'failed',
                    'Tracker Storage test file could not be written.',
                    ['path' => $storagePath . $testFile]
                );
                return;
            }

            if (!$storage->deleteFile($testFile)) {
                $this->addResult(
                    $results,
                    'storage',
                    'warning',
                    'Tracker Storage is writable, but the test file could not be deleted.',
                    ['path' => $storagePath . $testFile]
                );
                return;
            }

            $this->addResult($results, 'storage', 'ok', 'Tracker Storage directory is writable.', ['path' => $storagePath]);
        } catch (\Exception $e) {
            $this->addResult(
                $results,
                'storage',
                'failed',
                'Tracker Storage write/delete test failed.',
                ['path' => 'var/mktr_tracker/Storage/', 'message' => $e->getMessage()]
            );
        }
    }

    private function testPushFiles(array &$results, int $pushStatus): void
    {
        if ($pushStatus !== 1) {
            $this->addResult($results, 'push_notifications', 'ok', 'Push notifications are disabled; service worker files are not required.');
            return;
        }

        $missing = [];
        foreach (['firebase-config.js', 'firebase-messaging-sw.js'] as $file) {
            if (!is_file(BP . '/pub/' . $file)) {
                $missing[] = $file;
            }
        }

        $this->addResult(
            $results,
            'push_notifications',
            $missing ? 'warning' : 'ok',
            $missing
                ? 'Push notifications are enabled, but service worker files are missing. Save Config should regenerate them.'
                : 'Push notification service worker files exist.',
            ['missing_files' => $missing]
        );
    }

    private function testCronConfig(array &$results, int $enabled, $interval, string $label): void
    {
        if ($enabled !== 1) {
            $this->addResult($results, 'cron_' . $label, 'ok', sprintf('Cron %s is disabled.', $label));
            return;
        }

        $valid = is_numeric($interval) && (float)$interval > 0;
        $this->addResult(
            $results,
            'cron_' . $label,
            $valid ? 'ok' : 'warning',
            $valid
                ? sprintf('Cron %s interval is configured.', $label)
                : sprintf('Cron %s is enabled, but interval is empty or invalid.', $label),
            ['interval_hours' => $interval]
        );
    }

    private function prepareHttpClient(): void
    {
        $this->httpClient->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $this->httpClient->setOption(CURLOPT_TIMEOUT, 5);
        $this->httpClient->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $this->httpClient->setOption(CURLOPT_SSL_VERIFYHOST, 2);
    }

    private function addResult(array &$results, string $check, string $status, string $message, array $context = []): void
    {
        $results[] = [
            'check' => $check,
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ];
    }
}
