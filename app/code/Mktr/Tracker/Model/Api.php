<?php
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection SpellCheckingInspection */
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Api
{
    private const API_URL = "https://t.themarketer.com/api/v1/";

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Curl
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $timeOut = 1;

    /**
     * @var array|null
     */
    private $params;

    /**
     * @var string|null
     */
    private $lastUrl;

    /**
     * @var array|null
     */
    private $info;

    /**
     * @var string|null
     */
    private $body;

    /**
     * @var bool|null
     */
    private $requestType;

    public function __construct(
        Config $config,
        Curl $httpClient,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /** @noinspection PhpUnused */
    public function send($name, $data = [], $post = true)
    {
        return $this->REST(self::API_URL . $name, $data, $post);
    }

    /** @noinspection PhpUnused */
    public function getParam()
    {
        return $this->params;
    }

    /** @noinspection PhpUnused */
    public function getUrl()
    {
        return $this->lastUrl;
    }

    /** @noinspection PhpUnused */
    public function getStatus()
    {
        return $this->info["http_code"];
    }

    /** @noinspection PhpUnused */
    public function getContent()
    {
        return $this->body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function REST($url, $data = [], $post = true)
    {
        try {
            if (empty($this->config->getRestKey())) {
                return $this;
            }

            $this->params = array_merge([
                'k' => $this->config->getRestKey(),
                'u' => $this->config->getCustomerId()
            ], $data);

            $this->requestType = $post;

            if ($this->requestType) {
                $this->lastUrl = $url;
            } else {
                $this->lastUrl = $url . '?' . http_build_query($this->params);
            }

            $this->httpClient->setOption(CURLOPT_CONNECTTIMEOUT, $this->timeOut);
            $this->httpClient->setOption(CURLOPT_TIMEOUT, $this->timeOut);
            $this->httpClient->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $this->httpClient->setOption(CURLOPT_SSL_VERIFYHOST, 2);

            if ($this->requestType) {
                $this->httpClient->post($this->lastUrl, $this->params);
            } else {
                $this->httpClient->get($this->lastUrl);
            }

            $this->body = $this->httpClient->getBody();
            $this->info = ['http_code' => $this->httpClient->getStatus()];
        } catch (\Exception $e) {
            $this->body = null;
            $this->info = ['http_code' => 0];
            $this->logger->warning('TheMarketer API request failed', [
                'url' => $url,
                'message' => $e->getMessage()
            ]);
        }

        return $this;
    }
}
