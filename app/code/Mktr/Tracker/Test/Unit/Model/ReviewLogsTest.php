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

use Mktr\Tracker\Model\FileSystem;
use Mktr\Tracker\Model\ReviewLogs;
use PHPUnit\Framework\TestCase;

class ReviewLogsTest extends TestCase
{
    public function testRefreshReadsExistingJsonFromStorage(): void
    {
        $fileSystem = $this->createMock(FileSystem::class);
        $fileSystem->expects($this->once())->method('setWorkDirectory')->with('Storage')->willReturnSelf();
        $fileSystem->expects($this->once())->method('readFile')->with('reviews.json')->willReturn('{"reviews":{"1":{"id":"abc"}}}');

        $reviewLogs = new ReviewLogs($fileSystem);

        $this->assertSame(['reviews' => ['1' => ['id' => 'abc']]], $reviewLogs->getData());
    }

    public function testSaveWritesJsonOnlyWhenDirty(): void
    {
        $fileSystem = $this->createMock(FileSystem::class);
        $fileSystem->method('setWorkDirectory')->willReturnSelf();
        $fileSystem->method('readFile')->willReturn(false);
        $fileSystem->expects($this->once())
            ->method('writeFile')
            ->with('reviews.json', '{"reviews":{"1":{"id":"abc"}}}');

        $reviewLogs = new ReviewLogs($fileSystem);
        $reviewLogs->addTo('reviews', ['id' => 'abc'], '1');
        $reviewLogs->save();
        $reviewLogs->save();
    }
}
