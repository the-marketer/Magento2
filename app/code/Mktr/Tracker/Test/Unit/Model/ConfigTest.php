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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mktr\Tracker\Model\Config;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ConfigTest extends TestCase
{
    public function testReturnsPlainLegacySensitiveValues(): void
    {
        $config = $this->buildConfig(['mktr_tracker/tracker/rest_key' => 'plain-rest-key']);
        $config->setScopeCode(1);

        $this->assertSame('plain-rest-key', $config->getRestKey());
    }

    public function testDecryptsEncryptedSensitiveValues(): void
    {
        $config = $this->buildConfig(['mktr_tracker/tracker/rest_key' => '0:3:encrypted-value'], [
            '0:3:encrypted-value' => 'rest-secret',
        ]);
        $config->setScopeCode(1);

        $this->assertSame('rest-secret', $config->getRestKey());
    }

    public function testSplitsAttributeConfigurationByStoreScope(): void
    {
        $config = $this->buildConfig([
            'mktr_tracker/attribute/brand' => 'manufacturer|brand',
        ]);
        $config->setScopeCode(1);

        $this->assertSame(['manufacturer', 'brand'], $config->getBrandAttribute());
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $decryptedValues
     */
    private function buildConfig(array $values, array $decryptedValues = []): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($values) {
                return $values[$path] ?? null;
            }
        );

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(
            static function (string $value) use ($decryptedValues): string {
                return $decryptedValues[$value] ?? '';
            }
        );

        return new Config(
            $scopeConfig,
            $this->createMock(StoreManagerInterface::class),
            $encryptor
        );
    }
}
