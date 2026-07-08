<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

class Data
{
    /**
     * @var FileSystem
     */
    private $fileSystem;

    /**
     * @var Func
     */
    private $func;

    /**
     * @var FileSystem
     */
    private $storage;

    /**
     * @var array
     */
    private $data = [];

    public function __construct(FileSystem $fileSystem, Func $func)
    {
        $this->fileSystem = $fileSystem;
        $this->func = $func;
        $this->storage = $this->fileSystem->setWorkDirectory("Storage");
        $rawData = $this->storage->rFile("data.json");
        if ($rawData !== null) {
            $this->data = json_decode($rawData, true);
        } else {
            $this->data = [];
        }
    }

    public function __get($name)
    {
        if (!isset($this->data[$name])) {
            if ($name == 'update_feed' || $name == 'update_review' || $name == 'update_subscribe') {
                $this->data[$name] = 0;
            } else {
                $this->data[$name] = null;
            }
        }

        return $this->data[$name];
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function getData()
    {
        return $this->data;
    }

    public function addTo($name, $value, $key = null)
    {
        if ($key === null) {
            $this->data[$name][] = $value;
        } else {
            $this->data[$name][$key] = $value;
        }
    }

    public function del($name)
    {
        unset($this->data[$name]);
    }

    public function save()
    {
        $this->storage->writeFile("data.json", $this->func->toJson($this->data));
    }
}
