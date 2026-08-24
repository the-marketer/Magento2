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

class ReviewLogs
{
    protected $file = "reviews.json";
    protected $dir = "Storage";
    protected $isDirty = false;
    protected $content = null;
    protected $data = [];
    protected $original = [];

    /**
     * @var FileSystem
     */
    private $storage;

    public function __construct(FileSystem $fileSystem)
    {
        $this->storage = $fileSystem->setWorkDirectory($this->dir);
        $this->refresh();
    }

    public function __get($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return null;
    }

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
        $this->isDirty = true;
    }

    public function save()
    {
        if ($this->isDirty) {
            $this->isDirty = false;
            $this->storage->writeFile($this->file, json_encode($this->data, JSON_UNESCAPED_SLASHES));
            $this->original = $this->data;
        }
        return $this;
    }

    public function refresh()
    {
        $this->content = $this->storage->readFile($this->file);

        if ($this->content !== false && $this->content !== '') {
            $this->original = json_decode($this->content, true);
            $this->data = is_array($this->original) ? $this->original : [];
        }
        return $this;
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
        $this->isDirty = true;
        return $this;
    }

    public function addToIfNot($name, $value)
    {
        if (!isset($this->data[$name])) {
            $this->data[$name] = [];
        }

        if (!in_array($value, $this->data[$name])) {
            $this->data[$name][] = $value;
            $this->isDirty = true;
        }
        return $this;
    }

    public function del($name)
    {
        unset($this->data[$name]);
        $this->isDirty = true;
        return $this;
    }

}
