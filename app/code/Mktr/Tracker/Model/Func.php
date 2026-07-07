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
                $this->storeId = $this->storeManager->getStore()->getStoreId();
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

        if (isset($params['start_date'])) {
            $script = base64_encode($params['start_date'] . '-' . $this->getStoreId());
        } else {
            $script = $this->getStoreId();
        }

        $fileName = $action->getName() . "." . $script . "." . $params["mime-type"];

        $module = $this->fileSystem->setWorkDirectory("Storage");

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

        if (isset($params['start_date'])) {
            $script = base64_encode($params['start_date'] . '-' . $this->getStoreId());
        } else {
            $script = $this->getStoreId();
        }

        $pageKey = '';
        if (isset($params['page']) || isset($params['limit'])) {
            $pageKey = '.p' . ($params['page'] ?? 'all') . '.l' . ($params['limit'] ?? '50');
        }

        $fileName = $fName . "." . $script . $pageKey . "." . $params["mime-type"];

        if (isset($params['read']) && $module->isExists($fileName)) {
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

    public function Output($data, $data1 = null, $type = null, $convert = true)
    {
        $type = $type ?? $this->request->getParam('mime-type') ?? "xml";

        $result = $this->rawFactory->create();

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
                                    $error = "Incorrect Date " .
                                        $k . " - " .
                                        $this->params[$k] . " - " .
                                        $this->dateFormat;
                                }
                                break;
                            case "StartDate":
                                if (isset($this->params[$k]) && strtotime($this->params[$k]) > \time()) {
                                    $error = "Incorrect Start Date " .
                                        $k . " - " .
                                        $this->params[$k] . " - Today is " .
                                        date($this->dateFormat, \time());
                                }
                                break;
                            case "Key":
                                $providedKey = isset($this->params[$k]) ? (string) $this->params[$k] : '';
                                $expectedKey = (string) $this->config->getRestKey();
                                if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
                                    $error = "Incorrect REST API Key";
                                }
                                break;
                            case "KeyAuth":
                                $authHeader = $this->request->getHeader('Authorization');
                                $token = null;

                                if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                                    $token = $matches[1];
                                }

                                $expectedToken = (string) $this->config->getRestKey();
                                if ($token && hash_equals($expectedToken, (string) $token)) {
                                    break;
                                }

                                if (!$token || !hash_equals($expectedToken, (string) $token)) {
                                    $error = "Incorrect Authorization";
                                }
                                break;
                            case "RuleCheck":
                                if (isset($this->params[$k]) && !isset($this->config->getDiscountRules()[$this->params[$k]])) {
                                    $error = "Incorrect Rule Type " . $this->params[$k];
                                }
                                break;
                            case "Int":
                                if (isset($this->params[$k]) && !is_numeric($this->params[$k])) {
                                    $error = "Incorrect Value " . $this->params[$k];
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
}
