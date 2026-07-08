<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

declare(strict_types=1);

namespace Mktr\Tracker\Test\Unit\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Mktr\Tracker\Model\FileSystem as TrackerFileSystem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FileSystemTest extends TestCase
{
    public function testStorageDirectoryUsesMagentoVarDirectory(): void
    {
        $directory = $this->createMock(WriteInterface::class);
        $directory->expects($this->exactly(2))->method('create')->with($this->logicalOr(
            'mktr_tracker/Storage/',
            'mktr_tracker/Storage'
        ));
        $directory->expects($this->once())
            ->method('writeFile')
            ->with('mktr_tracker/Storage/orders.hash.json', 'mktrenc:encrypted-storage-payload', 'w+');
        $directory->method('getAbsolutePath')->willReturnCallback(
            static function (?string $path = null): string {
                return '/magento/var/' . ltrim((string) $path, '/');
            }
        );

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('getDirectoryWrite')
            ->with(DirectoryList::VAR_DIR)
            ->willReturn($directory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with('{"ok":true}')
            ->willReturn('encrypted-storage-payload');

        $trackerFileSystem = new TrackerFileSystem($filesystem, $encryptor);
        $trackerFileSystem->setWorkDirectory('Storage')->writeFile('orders.hash.json', '{"ok":true}');

        $this->assertSame('/magento/var/mktr_tracker/Storage/', $trackerFileSystem->getPath());
    }

    public function testExpiredFilesAreDeleted(): void
    {
        $directory = $this->createMock(WriteInterface::class);
        $directory->method('isExist')->with('mktr_tracker/Storage/')->willReturn(true);
        $directory->method('read')->with('mktr_tracker/Storage/')->willReturn([
            'mktr_tracker/Storage/old.json',
            'mktr_tracker/Storage/fresh.json',
        ]);
        $directory->method('isFile')->willReturn(true);
        $directory->method('stat')->willReturnCallback(
            static function (string $path): array {
                return [
                    'mtime' => $path === 'mktr_tracker/Storage/old.json' ? time() - 90000 : time(),
                ];
            }
        );
        $directory->expects($this->once())->method('delete')->with('mktr_tracker/Storage/old.json');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);

        $trackerFileSystem = new TrackerFileSystem($filesystem, $this->createMock(EncryptorInterface::class));
        $trackerFileSystem->setWorkDirectory('Storage')->deleteExpiredFiles(86400);
    }

    public function testStorageContentIsEncryptedAndDecryptedTransparently(): void
    {
        $directory = $this->createMock(WriteInterface::class);
        $directory->method('getAbsolutePath')->willReturnCallback(
            static function (?string $path = null): string {
                return '/magento/var/' . ltrim((string) $path, '/');
            }
        );
        $directory->method('isExist')->with('mktr_tracker/Storage/orders.hash.json')->willReturn(true);
        $directory->method('readFile')->with('mktr_tracker/Storage/orders.hash.json')->willReturn('mktrenc:ciphertext');
        $directory->expects($this->once())
            ->method('writeFile')
            ->with('mktr_tracker/Storage/orders.hash.json', 'mktrenc:ciphertext', 'w+');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->with('{"email":"customer@example.com"}')->willReturn('ciphertext');
        $encryptor->method('decrypt')->with('ciphertext')->willReturn('{"email":"customer@example.com"}');

        $trackerFileSystem = new TrackerFileSystem($filesystem, $encryptor);
        $trackerFileSystem->setWorkDirectory('Storage')->writeFile(
            'orders.hash.json',
            '{"email":"customer@example.com"}'
        );

        $this->assertSame(
            '{"email":"customer@example.com"}',
            $trackerFileSystem->readFile('orders.hash.json')
        );
    }
}
