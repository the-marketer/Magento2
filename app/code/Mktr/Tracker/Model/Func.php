<?php
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

use Exception;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;

class Func
{
    private const CACHE_TTL_SECONDS = 86400;
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 250;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var FileSystem
     */
    private $fileSystem;

    /**
     * @var RawFactory
     */
    private $rawFactory;

    /**
     * @var array|null
     */
    private $params;

    /**
     * @var string
     */
    private $dateFormat = 'Y-m-d';

    /**
     * @var string
     */
    private $output = '';

    /**
     * @var int|string|null
     */
    private $storeId = null;

    public function __construct(
        Config $config,
        HttpRequest $request,
        StoreManagerInterface $storeManager,
        FileSystem $fileSystem,
        RawFactory $rawFactory
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->fileSystem = $fileSystem;
        $this->rawFactory = $rawFactory;
    }

    /** @noinspection PhpUnused
     * @noinspection PhpRedundantOptionalArgumentInspection
     */
    public function digit2($num): string
    {
        return str_replace(',', '', number_format((float) $num, 2, '.', ''));
    }

    /** @noinspection PhpUnused */
    public function validateTelephone($phone)
    {
        return preg_replace("/\D/", "", $phone);
    }

    public function toJson($data = null)
    {
        /** @noinspection PhpComposerExtensionStubsInspection */
        return json_encode(($data === null ? [] : $data), JSON_UNESCAPED_SLASHES);
    }

    public function validateDate($date, $format = 'Y-m-d')
    {
        $this->dateFormat = $format;
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

    public function correctDate($date = null, $format = "Y-m-d H:i")
    {
        return $date !== null && $date != "0000-00-00 00:00:00" ? date($format, strtotime($date)) : null;
    }

    public function getOutPut()
    {
        return $this->output;
    }

    public function justOutput($data, $data1 = null, $type = null)
    {
        return $this->Output($data, $data1, $type, false);
    }

    public function setStoreId($id)
    {
        $this->storeId = $id;
    }

    public function getWebsiteId($store)
    {
        try {
            return $this->storeManager->getWebsite($store)->getDefaultGroup()->getDefaultStoreId();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getStoreId()
    {
        if ($this->storeId === null) {
            $store = $this->request->getParam('store', false);

            if ($store !== false) {
                try {
                    $store = $this->storeManager->getStore($store)->getId();
                } catch (Exception $e) {
                    $store = $this->getWebsiteId($store);
                }
            }
            if ($store !== false) {
                $this->storeId = $store;
                $this->storeManager->setCurrentStore($store);
            } else {
                $this->storeId = $this->storeManager->getStore()->getId();
            }
        }

        return $this->storeId;
    }

    public function Write($action)
    {
        if (!$this->request->getParam("mime-type")) {
            $this->request->setParam("mime-type", 'xml');
        }

        $params = $this->request->getParams();

        $fileName = $this->buildCacheFileName($action->getName(), $params);

        $module = $this->fileSystem->setWorkDirectory("Storage");
        $module->deleteExpiredFiles(self::CACHE_TTL_SECONDS);

        $out = $action->freshData();

        $result = $this->Output($action->getName(), [$action->getSecondName() => $out]);

        $module->writeFile($fileName, $this->getOutPut());

        return $result;
    }

    public function readOrWrite($fName, $secondName, $action)
    {
        if (!$this->request->getParam("mime-type")) {
            $this->request->setParam("mime-type", 'xml');
        }
        $module = $this->fileSystem->setWorkDirectory("Storage");
        $params = $this->request->getParams();

        $fileName = $this->buildCacheFileName($fName, $params);
        $module->deleteExpiredFiles(self::CACHE_TTL_SECONDS);

        if (isset($params['read']) && $module->isExists($fileName) && !$module->isExpired($fileName, self::CACHE_TTL_SECONDS)) {
            $out = $module->readFile($fileName);

            if ($out !== false) {
                return $this->justOutput($out);
            }
        }
        $out = $action->freshData();
        $result = $this->Output($fName, [$secondName => $out]);

        $module->writeFile($fileName, $this->getOutPut());

        return $result;
    }

    public function getPageParam(): int
    {
        $page = (int) ($this->request->getParam('page', self::DEFAULT_PAGE));

        return max(self::DEFAULT_PAGE, $page);
    }

    public function getLimitParam(): int
    {
        $limit = (int) ($this->request->getParam('limit', self::DEFAULT_LIMIT));

        return min(self::MAX_LIMIT, max(1, $limit));
    }

    private function buildCacheFileName(string $name, array $params): string
    {
        $mimeType = $params['mime-type'] ?? 'xml';
        $page = isset($params['page']) ? $this->getPageParam() : 'all';
        $limit = isset($params['limit']) ? $this->getLimitParam() : self::DEFAULT_LIMIT;
        $rawKey = implode('|', [
            $name,
            $this->getStoreId(),
            $params['start_date'] ?? '',
            $params['end_date'] ?? '',
            $page,
            $limit,
            $mimeType
        ]);
        $secret = (string) ($this->config->getRestKey() ?: $this->config->getCustomerId() ?: 'mktr_tracker');

        return $name . '.' . hash_hmac('sha256', $rawKey, $secret) . '.' . $mimeType;
    }

    public function Output($data, $data1 = null, $type = null, $convert = true, ?int $httpStatusCode = null)
    {
        $type = $type ?? $this->request->getParam('mime-type') ?? "xml";

        $result = $this->rawFactory->create();
        $httpStatusCode = $httpStatusCode ?? $this->resolveHttpStatusCode($data, $data1);

        if ($httpStatusCode !== 200) {
            $result->setHttpResponseCode($httpStatusCode);
        }

        $this->output = "";

        if ($type === 'json') {
            $result->setHeader('Content-type', 'application/json; charset=utf-8;', 1);

            if ($convert) {
                if ($data1 !== null) {
                    $data = [$data => $data1];
                }

                $this->output = $this->toJson($data);
            }
        } else {
            $result->setHeader('Content-type', 'application/xhtml+xml; charset=utf-8;', 1);

            if ($convert) {
                $this->output = Array2XML::cXML($data, $data1)->saveXML();
            }
        }

        if (!$convert) {
            $this->output = $data;
        }

        return $result->setContents($this->output);
    }

    private function resolveHttpStatusCode($data, $data1 = null): int
    {
        if ($data === 'status' && $data1 === 'Incorrect Authorization') {
            return 401;
        }

        if (is_array($data) && ($data['status'] ?? null) === 'Incorrect Authorization') {
            return 401;
        }

        return 200;
    }

    public function isParamValid($checkParam = null)
    {
        $this->params = $this->request->getParams();

        if ($this->params === null) {
            return "oops";
        }

        if ($checkParam === null) {
            return null;
        }

        $error = null;

        foreach ($checkParam as $k => $v) {
            if ($v !== null) {
                $check = explode("|", $v);
                foreach ($check as $do) {
                    if ($error === null) {
                        switch ($do) {
                            case "Required":
                                if (!isset($this->params[$k])) {
                                    $error = "Missing Parameter " . $k;
                                }
                                break;
                            case "DateCheck":
                                if (isset($this->params[$k]) && !$this->validateDate($this->params[$k])) {
                                    $error = "Incorrect Date";
                                }
                                break;
                            case "StartDate":
                                if (isset($this->params[$k]) && strtotime($this->params[$k]) > \time()) {
                                    $error = "Incorrect Start Date";
                                }
                                break;
                            case "Key":
                            case "KeyAuth":
                                if (!$this->isAuthorizedByBearerToken()) {
                                    $error = "Incorrect Authorization";
                                }
                                break;
                            case "RuleCheck":
                                if (isset($this->params[$k]) && !isset($this->config->getDiscountRules()[$this->params[$k]])) {
                                    $error = "Incorrect Rule Type";
                                }
                                break;
                            case "Int":
                                if (isset($this->params[$k]) && !is_numeric($this->params[$k])) {
                                    $error = "Incorrect Value";
                                }
                                break;
                            case "allow_export":
                                if ($this->config->getAllowExport() === 0) {
                                    $error = "Export not Allow";
                                }
                                break;
                            default:
                        }
                    }
                }
            }
        }

        return $error;
    }

    private function isAuthorizedByBearerToken(): bool
    {
        $authHeader = $this->request->getHeader('Authorization');
        $token = null;

        if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }

        $expectedToken = (string) $this->config->getRestKey();

        return $token !== null && hash_equals($expectedToken, (string) $token);
    }
}
