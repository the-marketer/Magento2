<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Mktr\Tracker\Test\Unit\Controller\Adminhtml\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Mktr\Tracker\Controller\Adminhtml\Config\TestConnection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
class TestConnectionTest extends TestCase
{
    public function testTrackingScriptReferersIncludeHostWithoutScheme(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap(
            [
                ['web/secure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, 'https://magento2.dev.mktr.me/'],
                ['web/unsecure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, 'http://magento2.dev.mktr.me/store/'],
            ]
        );

        $controller = $this->buildController($scopeConfig);
        $referers = $this->invokePrivateMethod($controller, 'getTrackingScriptReferers', [
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        ]);

        $this->assertSame(
            [
                'https://magento2.dev.mktr.me/',
                'magento2.dev.mktr.me/',
                'magento2.dev.mktr.me',
                'http://magento2.dev.mktr.me/store/',
                'magento2.dev.mktr.me/store/',
            ],
            $referers
        );
    }

    public function testTrackingScriptReferersPreserveHostPorts(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap(
            [
                ['web/secure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, 'https://shop.test:8443/'],
                ['web/unsecure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, 'shop.test:8080/store/'],
            ]
        );

        $controller = $this->buildController($scopeConfig);
        $referers = $this->invokePrivateMethod($controller, 'getTrackingScriptReferers', [
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        ]);

        $this->assertSame(
            [
                'https://shop.test:8443/',
                'shop.test:8443/',
                'shop.test:8443',
                'shop.test:8080/store/',
                'shop.test:8080',
            ],
            $referers
        );
    }

    private function buildController(ScopeConfigInterface $scopeConfig): TestConnection
    {
        $reflection = new ReflectionClass(TestConnection::class);
        /** @var TestConnection $controller */
        $controller = $reflection->newInstanceWithoutConstructor();

        $property = $reflection->getProperty('scopeConfig');
        $property->setValue($controller, $scopeConfig);

        return $controller;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMethod(TestConnection $controller, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($controller);

        return $reflection->getMethod($method)->invokeArgs($controller, $arguments);
    }
}
