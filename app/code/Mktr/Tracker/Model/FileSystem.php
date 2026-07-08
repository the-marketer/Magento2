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
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem as MagentoFilesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class FileSystem
{
    private const ENCRYPTED_PREFIX = 'mktrenc:';

    /**
     * @var MagentoFilesystem
     */
    private $filesystem;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var string|null
     */
    private $path = null;

    /**
     * @var WriteInterface|null
     */
    private $directory = null;

    /**
     * @var array
     */
    private $status = [];

    /**
     * @var string|null
     */
    private $lastPath = null;

    public function __construct(MagentoFilesystem $filesystem, EncryptorInterface $encryptor)
    {
        $this->filesystem = $filesystem;
        $this->encryptor = $encryptor;
    }

    private function getFilePath($fileName): string
    {
        return $this->path . ltrim($fileName, '/');
    }

    private function shouldEncryptStorageContent(): bool
    {
        return $this->path !== null
            && strpos($this->path, 'mktr_tracker/') === 0;
    }

    private function encodeContent($content): string
    {
        $content = (string) $content;

        if (!$this->shouldEncryptStorageContent()) {
            return $content;
        }

        return self::ENCRYPTED_PREFIX . $this->encryptor->encrypt($content);
    }

    private function decodeContent(string $content): string
    {
        if (!$this->shouldEncryptStorageContent() || strpos($content, self::ENCRYPTED_PREFIX) !== 0) {
            return $content;
        }

        try {
            return (string) $this->encryptor->decrypt(substr($content, strlen(self::ENCRYPTED_PREFIX)));
        } catch (\Exception $e) {
            return '';
        }
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function setWorkDirectory($name = 'base')
    {
        if ($name == 'base') {
            $this->directory = $this->filesystem->getDirectoryWrite(DirectoryList::PUB);
            $this->path = '';
        } else {
            $this->directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $this->path = 'mktr_tracker/' . trim($name, '/') . '/';
            $this->directory->create($this->path);
        }
        return $this;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function writeFile($fName, $content, $mode = 'w+')
    {
        $filePath = $this->getFilePath($fName);
        $this->directory->create(dirname($filePath));
        $this->directory->writeFile($filePath, $this->encodeContent($content), $mode);

        $this->status[] = [
            'path' => $this->getPath(),
            'fileName' => $fName,
            'fullPath' => $this->directory->getAbsolutePath($filePath),
            'status' => true
        ];

        return $this;
    }

    public function rFile($fName, $mode = "rb")
    {
        $contents = $this->readFile($fName, $mode);

        return $contents === false ? '' : $contents;
    }

    public function readFile($fName, $mode = "rb")
    {
        $filePath = $this->getFilePath($fName);
        $this->lastPath = $this->directory->getAbsolutePath($filePath);

        if (!$this->directory->isExist($filePath)) {
            return false;
        }

        return $this->decodeContent((string) $this->directory->readFile($filePath));
    }

    public function isExists($fName)
    {
        return $this->directory->isExist($this->getFilePath($fName));
    }

    public function deleteFile($fName)
    {
        $filePath = $this->getFilePath($fName);
        if ($this->directory->isExist($filePath)) {
            $this->directory->delete($filePath);
        }
        return true;
    }

    public function isExpired($fName, int $ttl): bool
    {
        $filePath = $this->getFilePath($fName);

        if ($ttl <= 0 || !$this->directory->isExist($filePath)) {
            return false;
        }

        $stat = $this->directory->stat($filePath);

        return isset($stat['mtime']) && (int) $stat['mtime'] < time() - $ttl;
    }

    public function deleteExpiredFiles(int $ttl): void
    {
        if ($ttl <= 0 || !$this->directory->isExist($this->path)) {
            return;
        }

        foreach ($this->directory->read($this->path) as $filePath) {
            if (!$this->directory->isFile($filePath)) {
                continue;
            }

            $stat = $this->directory->stat($filePath);
            if (isset($stat['mtime']) && (int) $stat['mtime'] < time() - $ttl) {
                $this->directory->delete($filePath);
            }
        }
    }

    public function getPath()
    {
        return $this->directory->getAbsolutePath($this->path);
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
