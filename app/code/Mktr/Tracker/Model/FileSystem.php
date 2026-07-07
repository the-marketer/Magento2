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

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem as MagentoFilesystem;

class FileSystem
{
    /**
     * @var MagentoFilesystem
     */
    private $filesystem;

    /**
     * @var string|null
     */
    private $path = null;

    /**
     * @var string|null
     */
    private $lastPath = null;

    /**
     * @var string|null
     */
    private $modulePath = null;

    /**
     * @var array
     */
    private $status = [];

    public function __construct(MagentoFilesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    private function getModulePath()
    {
        if ($this->modulePath === null) {
            $this->modulePath = dirname(__DIR__) . "/";
        }
        return $this->modulePath;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function setWorkDirectory($name = 'base')
    {
        if ($name == 'base') {
            $this->path = $this->filesystem->getDirectoryWrite(DirectoryList::PUB)->getAbsolutePath();
        } else {
            $this->path = $this->getModulePath() . $name . "/";
        }
        return $this;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function writeFile($fName, $content, $mode = 'w+')
    {
        $file = fopen($this->path . $fName, $mode);
        fwrite($file, $content);
        fclose($file);

        $this->status[] = [
            'path' => $this->path,
            'fileName' => $fName,
            'fullPath' => $this->path . $fName,
            'status' => true
        ];

        return $this;
    }

    public function rFile($fName, $mode = "rb")
    {
        $this->lastPath = $this->path . $fName;
        if (file_exists($this->lastPath)) {
            $file = fopen($this->lastPath, $mode);

            $contents = fread($file, filesize($this->lastPath));

            fclose($file);
        } else {
            $contents = '';
        }

        return $contents;
    }

    public function readFile($fName, $mode = "rb")
    {
        $this->lastPath = $this->path . $fName;
        $file = fopen($this->lastPath, $mode);

        $contents = fread($file, filesize($this->lastPath));

        fclose($file);

        return $contents;
    }

    public function isExists($fName)
    {
        return file_exists($this->path . $fName);
    }

    public function deleteFile($fName)
    {
        if (file_exists($this->path . $fName)) {
            unlink($this->path . $fName);
        }
        return true;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getLastPath()
    {
        return $this->lastPath;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
