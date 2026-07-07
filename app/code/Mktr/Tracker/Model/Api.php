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
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $mURL = "https://t.themarketer.com/api/v1/";

    private static $timeOut = null;

    private static $httpClient = null;
    private static $logger = null;

    private static $params = null;
    private static $lastUrl = null;

    private static $info = null;
    private static $exec = null;
    private static $requestType = null;

    private static $return = null;

    public function __construct(
        Config $config,
        Curl $httpClient,
        LoggerInterface $logger
    )
    {
        self::$ins["Config"] = $config;
        self::$httpClient = $httpClient;
        self::$logger = $logger;
        self::$return = $this;
    }
    /** TODO: Magento 2 */
    public static function getConfig()
    {
        return self::$ins["Config"];
    }

    /** @noinspection PhpUnused */
    public static function send($name, $data = [], $post = true)
    {
        return self::REST(self::$mURL . $name, $data, $post);
    }

    /** @noinspection PhpUnused */
    public static function getParam()
    {
        return self::$params;
    }

    /** @noinspection PhpUnused */
    public static function getUrl()
    {
        return self::$lastUrl;
    }

    /** @noinspection PhpUnused */
    public static function getStatus()
    {
        return self::$info["http_code"];
    }

    /** @noinspection PhpUnused */
    public static function getContent()
    {
        return self::$exec;
    }

    public static function getBody()
    {
        return self::$exec;
    }

    private static function getHttpClient()
    {
        return self::$httpClient;
    }

    private static function getLogger()
    {
        return self::$logger;
    }

    public static function REST($url, $data = [], $post = true)
    {
        try {
            if (empty(self::getConfig()->getRestKey())) {
                return false;
            }

            if (self::$timeOut == null) {
                self::$timeOut = 1;
            }

            self::$params = array_merge([
                'k' => self::getConfig()->getRestKey(),
                'u' => self::getConfig()->getCustomerId()
            ], $data);

            self::$requestType = $post;

            if (self::$requestType) {
                self::$lastUrl = $url;
            } else {
                self::$lastUrl = $url .'?'. http_build_query(self::$params);
            }

            $client = self::getHttpClient();
            $client->setOption(CURLOPT_CONNECTTIMEOUT, self::$timeOut);
            $client->setOption(CURLOPT_TIMEOUT, self::$timeOut);
            $client->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $client->setOption(CURLOPT_SSL_VERIFYHOST, 2);

            if (self::$requestType) {
                $client->post(self::$lastUrl, self::$params);
            } else {
                $client->get(self::$lastUrl);
            }

            self::$exec = $client->getBody();
            self::$info = ['http_code' => $client->getStatus()];
        } catch (\Exception $e) {
            self::$exec = null;
            self::$info = ['http_code' => 0];
            self::getLogger()->warning('TheMarketer API request failed', [
                'url' => $url,
                'message' => $e->getMessage()
            ]);
        }
        return self::$return;
    }
}
