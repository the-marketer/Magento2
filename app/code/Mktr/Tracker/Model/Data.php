<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

class Data
{
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $data;
    private static $storage = null;

    public function __construct()
    {
        self::$storage = self::getHelp()->getFileSystem->setWorkDirectory("Storage");
        $data = self::$storage->rFile("data.json");
        if ($data !== null) {
            self::$data = json_decode($data, true);
        } else {
            self::$data = [];
        }
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()
                ->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    public function __get($name)
    {
        if (!isset(self::$data[$name])) {
            if ($name == 'update_feed' || $name == 'update_review' || $name == 'update_subscribe') {
                self::$data[$name] = 0;
            } else {
                self::$data[$name] = null;
            }
        }

        return self::$data[$name];
    }

    public function __set($name, $value)
    {
        self::$data[$name] = $value;
    }

    public static function getData()
    {
        return self::$data;
    }

    public static function addTo($name, $value, $key = null)
    {
        if ($key === null) {
            self::$data[$name][] = $value;
        } else {
            self::$data[$name][$key] = $value;
        }
    }

    public static function del($name)
    {
        unset(self::$data[$name]);
    }

    public static function save()
    {
        self::$storage->writeFile("data.json", self::getHelp()->getFunc->toJson(self::$data));
    }
}
