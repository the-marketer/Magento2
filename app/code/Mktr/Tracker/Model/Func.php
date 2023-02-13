<?php
/** @noinspection SpellCheckingInspection */
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Model;

class Func
{
    private static $params;
    private static $dateFormat;

    private static $ins = [
        "Help" => null,
        "Config" => null
    ];
    /**
     * @var string
     */
    private static $getOut;
    private static $storeID = null;

    /** TODO: Magento 2 */
    public static function getConfig()
    {
        if (self::$ins["Config"] == null) {
            self::$ins["Config"] = \Magento\Framework\App\ObjectManager::getInstance()->get("\Mktr\Tracker\Model\Config");
        }
        return self::$ins["Config"];
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    /** @noinspection PhpUnused
     * @noinspection PhpRedundantOptionalArgumentInspection
     */
    public static function digit2($num): string
    {
        // return sprintf('%.2f', (float) $num);
        return number_format((float) $num, 2, '.', ',');
    }

    /** @noinspection PhpUnused */
    public static function validateTelephone($phone)
    {
        return preg_replace("/\D/", "", $phone);
    }

    public static function toJson($data = null){
        /** @noinspection PhpComposerExtensionStubsInspection */
        return json_encode(($data === null ? [] : $data), JSON_UNESCAPED_SLASHES);
    }

    public static function validateDate($date, $format = 'Y-m-d')
    {
        self::$dateFormat = $format;
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public static function correctDate($date = null, $format = "Y-m-d H:i")
    {
        return $date !== null ? date($format, strtotime($date)) : $date;
    }

    public static function getOutPut()
    {
        return self::$getOut;
    }

    public static function justOutput($data, $data1=null, $type = null)
    {
        return self::Output($data, $data1, $type, false);
    }

    public static function setStoreId($id)
    {
        self::$storeID = $id;
    }

    public static function getStoreId()
    {
        if (self::$storeID == null) {
            self::$storeID = self::getHelp()->getStore->getStoreId();
        }
        return self::$storeID;
    }

    public static function Write($action)
    {
        if (!self::getHelp()->getRequest->getParam("mime-type")) {
            self::getHelp()->getRequest->setParam("mime-type", 'xml');
        }

        $params = self::getHelp()->getRequest->getParams();

        if (isset($params['start_date']))
        {
            $script = base64_encode($params['start_date'].'-'.self::getStoreId());
        } else {
            $script = self::getStoreId();
        }

        $fileName = $action->getName().".".$script.".".$params["mime-type"];

        $module = self::getHelp()->getFileSystem->setWorkDirectory("Storage");

        $out = $action->freshData();

        $result = self::Output($action->getName(), [$action->getSecondName() => $out]);

        $module->writeFile($fileName, self::getOutPut());

        return $result;
    }

    public static function readOrWrite($fName, $secondName, $action)
    {
        if (!self::getHelp()->getRequest->getParam("mime-type")) {
            self::getHelp()->getRequest->setParam("mime-type", 'xml');
        }
        $module = self::getHelp()->getFileSystem->setWorkDirectory("Storage");
        $params = self::getHelp()->getRequest->getParams();

        if (isset($params['start_date']))
        {
            $script = base64_encode($params['start_date'].'-'.self::getStoreId());
        } else {
            $script = self::getStoreId();
        }

        $fileName = $fName.".".$script.".".$params["mime-type"];

        if (isset($params['read']) && $module->isExists($fileName)) {
            $out = $module->readFile($fileName);

            if ($out !== false) {
                return self::justOutput($out);
            }
        }
        $out = $action->freshData();
        $result = self::Output($fName, [$secondName => $out]);

        $module->writeFile($fileName, self::getOutPut());

        return $result ;
    }

    public static function Output($data, $data1=null, $type = null, $convert = true)
    {

        $type = $type ?? self::getHelp()->getRequest->getParam('mime-type') ?? "xml";

        $result = self::getHelp()->getPageRaw;

        self::$getOut = "";

        if ($type === 'json')
        {
            $result->setHeader('Content-type', 'application/json; charset=utf-8;', 1);

            if($convert) {
                if ($data1 !== null) {
                    $data = array($data => $data1);
                }

                self::$getOut = self::toJson($data);
            }
        } else {
            $result->setHeader('Content-type', 'application/xhtml+xml; charset=utf-8;', 1);

            if($convert) {
                self::$getOut = self::getHelp()->getArray2XML->cXML($data, $data1)->saveXML();
            }
        }

        if(!$convert)
        {
            self::$getOut = $data;
        }

        return $result->setContents(self::$getOut);
    }

    public static function isParamValid($checkParam = null)
    {
        self::$params = self::getHelp()->getRequest->getParams();

        if (self::$params === null)
        {
            return "oops";
        }

        if ($checkParam === null)
        {
            return null;
        }

        $error = null;

        foreach ($checkParam as $k=>$v)
        {
            if ($v !== null)
            {
                $check = explode("|", $v);
                foreach ($check as $do)
                {
                    if ($error === null) {
                        switch ($do)
                        {
                            case "Required":
                                if (!isset(self::$params[$k]))
                                {
                                    $error = "Missing Parameter ". $k;
                                }
                                break;
                            case "DateCheck":
                                if (isset(self::$params[$k]) && !self::validateDate(self::$params[$k]))
                                {
                                    $error = "Incorrect Date ".
                                        $k." - ".
                                        self::$params[$k] . " - ".
                                        self::$dateFormat;
                                }
                                break;
                            case "StartDate":
                                if (isset(self::$params[$k]) && strtotime(self::$params[$k]) > \time())
                                {
                                    $error = "Incorrect Start Date ".
                                        $k." - ".
                                        self::$params[$k] . " - Today is ".
                                        date(self::$dateFormat, \time());
                                }
                                break;
                            case "Key":
                                if (isset(self::$params[$k]) && self::$params[$k] !== self::getConfig()->getRestKey())
                                {
                                    $error = "Incorrect REST API Key ". self::$params[$k];
                                }
                                break;
                            case "RuleCheck":
                                if (isset(self::$params[$k]) && !isset(self::getConfig()->getDiscountRules()[self::$params[$k]]))
                                {
                                    $error = "Incorrect Rule Type ". self::$params[$k];
                                }
                                break;
                            case "Int":
                                if (isset(self::$params[$k]) && !is_numeric(self::$params[$k]))
                                {
                                    $error = "Incorrect Value ". self::$params[$k];
                                }
                                break;
                            case "allow_export":
                                if (self::getConfig()->getAllowExport() === 0) {
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
