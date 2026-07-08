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

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mktr\Tracker\Model\Config;
use Mktr\Tracker\Model\FileSystem;
use Mktr\Tracker\Model\Func;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FuncTest extends TestCase
{
    /**
     * @var Func
     */
    private $func;

    protected function setUp(): void
    {
        $this->configureValidationDependencies([]);
    }

    #[DataProvider('digit2DataProvider')]
    public function testDigit2FormatsNumbersWithTwoDecimals($value, string $expected): void
    {
        $this->assertSame($expected, $this->func->digit2($value));
    }

    public static function digit2DataProvider(): array
    {
        return [
            'integer' => [10, '10.00'],
            'decimal string' => ['10.235', '10.24'],
            'thousands separator stripped' => [1234.5, '1234.50'],
            'non numeric fallback' => ['not-a-number', '0.00'],
        ];
    }

    #[DataProvider('telephoneDataProvider')]
    public function testValidateTelephoneKeepsOnlyDigits(string $phone, string $expected): void
    {
        $this->assertSame($expected, $this->func->validateTelephone($phone));
    }

    public static function telephoneDataProvider(): array
    {
        return [
            'spaced local number' => ['+40 721 123 456', '40721123456'],
            'punctuation' => ['(555) 010-9000 ext. 2', '55501090002'],
            'empty' => ['', ''],
        ];
    }

    public function testToJsonEncodesNullAsEmptyArray(): void
    {
        $this->assertSame('[]', $this->func->toJson());
    }

    public function testToJsonDoesNotEscapeSlashes(): void
    {
        $this->assertSame(
            '{"url":"https://example.test/feed"}',
            $this->func->toJson(['url' => 'https://example.test/feed'])
        );
    }

    #[DataProvider('dateValidationDataProvider')]
    public function testValidateDateChecksExactFormat(string $date, string $format, bool $expected): void
    {
        $this->assertSame($expected, $this->func->validateDate($date, $format));
    }

    public static function dateValidationDataProvider(): array
    {
        return [
            'valid default date' => ['2026-07-08', 'Y-m-d', true],
            'invalid day' => ['2026-02-30', 'Y-m-d', false],
            'wrong separator' => ['2026/07/08', 'Y-m-d', false],
            'valid date time' => ['2026-07-08 14:25', 'Y-m-d H:i', true],
        ];
    }

    public function testCorrectDateFormatsValidDates(): void
    {
        $this->assertSame('2026-07-08 14:25', $this->func->correctDate('2026-07-08 14:25:50'));
        $this->assertSame('08.07.2026', $this->func->correctDate('2026-07-08 14:25:50', 'd.m.Y'));
    }

    public function testCorrectDateReturnsNullForEmptyMagentoDateValues(): void
    {
        $this->assertNull($this->func->correctDate(null));
        $this->assertNull($this->func->correctDate('0000-00-00 00:00:00'));
    }

    public function testIsParamValidAcceptsValidApiParameters(): void
    {
        $this->configureValidationDependencies(
            [
                'expiration_date' => '2026-07-08',
                'value' => '15',
                'type' => '1',
            ],
            'Bearer rest-secret'
        );

        $this->assertNull($this->func->isParamValid([
            'key' => 'KeyAuth',
            'expiration_date' => 'DateCheck',
            'value' => 'Required|Int',
            'type' => 'Required|RuleCheck',
        ]));
    }

    public function testIsParamValidRejectsMissingRequiredParameters(): void
    {
        $this->configureValidationDependencies(['type' => '1']);

        $this->assertSame(
            'Missing Parameter value',
            $this->func->isParamValid(['value' => 'Required|Int'])
        );
    }

    public function testIsParamValidRejectsInvalidBearerToken(): void
    {
        $this->configureValidationDependencies(['key' => 'legacy-key'], 'Bearer wrong-secret');

        $this->assertSame(
            'Incorrect Authorization',
            $this->func->isParamValid(['key' => 'KeyAuth'])
        );
    }

    public function testIsParamValidRejectsInvalidDiscountRuleType(): void
    {
        $this->configureValidationDependencies(['type' => 'invalid']);

        $this->assertSame(
            'Incorrect Rule Type',
            $this->func->isParamValid(['type' => 'RuleCheck'])
        );
    }

    public function testIsParamValidDoesNotReflectInvalidInputValues(): void
    {
        $this->configureValidationDependencies([
            'start_date' => '2099-01-01',
            'expiration_date' => 'not-a-date',
            'value' => 'not-a-number',
            'type' => 'invalid-rule',
        ]);

        $this->assertSame('Incorrect Start Date', $this->func->isParamValid(['start_date' => 'StartDate']));
        $this->assertSame('Incorrect Date', $this->func->isParamValid(['expiration_date' => 'DateCheck']));
        $this->assertSame('Incorrect Value', $this->func->isParamValid(['value' => 'Int']));
        $this->assertSame('Incorrect Rule Type', $this->func->isParamValid(['type' => 'RuleCheck']));
    }

    public function testLegacyKeyRuleRequiresBearerHeader(): void
    {
        $this->configureValidationDependencies(['key' => 'rest-secret']);

        $this->assertSame(
            'Incorrect Authorization',
            $this->func->isParamValid(['key' => 'Key'])
        );
    }

    public function testQueryStringKeyDoesNotAuthenticateKeyAuthRule(): void
    {
        $this->configureValidationDependencies(['key' => 'rest-secret']);

        $this->assertSame(
            'Incorrect Authorization',
            $this->func->isParamValid(['key' => 'KeyAuth'])
        );
    }

    public function testAuthorizationErrorsUseHttpUnauthorizedStatus(): void
    {
        $result = $this->createMock(Raw::class);
        $result->expects($this->once())->method('setHttpResponseCode')->with(401)->willReturnSelf();
        $result->method('setHeader')->willReturnSelf();
        $result->expects($this->once())
            ->method('setContents')
            ->with('{"status":"Incorrect Authorization"}')
            ->willReturnSelf();

        $func = $this->buildOutputFunc($result);

        $this->assertSame($result, $func->Output('status', 'Incorrect Authorization', 'json'));
    }

    public function testArrayAuthorizationErrorsUseHttpUnauthorizedStatus(): void
    {
        $result = $this->createMock(Raw::class);
        $result->expects($this->once())->method('setHttpResponseCode')->with(401)->willReturnSelf();
        $result->method('setHeader')->willReturnSelf();
        $result->expects($this->once())
            ->method('setContents')
            ->with('{"status":"Incorrect Authorization"}')
            ->willReturnSelf();

        $func = $this->buildOutputFunc($result);

        $this->assertSame($result, $func->Output(['status' => 'Incorrect Authorization'], null, 'json'));
    }

    public function testNonAuthorizationErrorsKeepDefaultHttpStatus(): void
    {
        $result = $this->createMock(Raw::class);
        $result->expects($this->never())->method('setHttpResponseCode');
        $result->method('setHeader')->willReturnSelf();
        $result->expects($this->once())
            ->method('setContents')
            ->with('{"status":"Incorrect Date"}')
            ->willReturnSelf();

        $func = $this->buildOutputFunc($result);

        $this->assertSame($result, $func->Output('status', 'Incorrect Date', 'json'));
    }

    #[DataProvider('paginationDataProvider')]
    public function testPaginationIsClamped(array $params, int $expectedPage, int $expectedLimit): void
    {
        $this->configureValidationDependencies($params);

        $this->assertSame($expectedPage, $this->func->getPageParam());
        $this->assertSame($expectedLimit, $this->func->getLimitParam());
    }

    public static function paginationDataProvider(): array
    {
        return [
            'defaults' => [[], 1, 50],
            'below minimum' => [['page' => '-4', 'limit' => '0'], 1, 1],
            'above maximum' => [['page' => '3', 'limit' => '9999'], 3, 250],
        ];
    }

    public function testReadOrWriteUsesOpaqueCacheFileName(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParams')->willReturn([
            'start_date' => '2026-07-08',
            'page' => '2',
            'limit' => '9999',
            'mime-type' => 'json',
        ]);
        $request->method('getParam')->willReturnCallback(
            static function (string $name, $default = null) {
                $params = [
                    'start_date' => '2026-07-08',
                    'page' => '2',
                    'limit' => '9999',
                    'mime-type' => 'json',
                ];

                return $params[$name] ?? $default;
            }
        );

        $config = $this->createMock(Config::class);
        $config->method('getRestKey')->willReturn('rest-secret');
        $config->method('getCustomerId')->willReturn('customer-id');

        $fileSystem = $this->createMock(FileSystem::class);
        $fileSystem->method('setWorkDirectory')->with('Storage')->willReturnSelf();
        $fileSystem->expects($this->once())->method('deleteExpiredFiles')->with(86400);
        $fileSystem->expects($this->once())
            ->method('writeFile')
            ->with(
                $this->callback(static function (string $fileName): bool {
                    return (bool) preg_match('/^orders\\.[a-f0-9]{64}\\.json$/', $fileName)
                        && strpos($fileName, '2026-07-08') === false;
                }),
                $this->anything()
            );

        $result = $this->createMock(Raw::class);
        $result->method('setHeader')->willReturnSelf();
        $result->method('setContents')->willReturnSelf();
        $rawFactory = $this->createMock(RawFactory::class);
        $rawFactory->method('create')->willReturn($result);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $func = new Func(
            $config,
            $request,
            $storeManager,
            $fileSystem,
            $rawFactory
        );

        $action = new class {
            public function freshData(): array
            {
                return [['id' => 1]];
            }
        };

        $func->readOrWrite('orders', 'order', $action);
    }

    public function testIsParamValidRejectsDisabledExport(): void
    {
        $this->configureValidationDependencies([], null, 'rest-secret', 0);

        $this->assertSame(
            'Export not Allow',
            $this->func->isParamValid(['export' => 'allow_export'])
        );
    }

    private function buildOutputFunc(Raw $result): Func
    {
        $rawFactory = $this->createMock(RawFactory::class);
        $rawFactory->method('create')->willReturn($result);

        return new Func(
            $this->createMock(Config::class),
            $this->createMock(HttpRequest::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(FileSystem::class),
            $rawFactory
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function configureValidationDependencies(
        array $params,
        ?string $authorization = null,
        string $restKey = 'rest-secret',
        int $allowExport = 1
    ): void {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParams')->willReturn($params);
        $request->method('getParam')->willReturnCallback(
            static function (string $name, $default = null) use ($params) {
                return $params[$name] ?? $default;
            }
        );
        $request->method('getHeader')->willReturnCallback(
            static function (string $name) use ($authorization): ?string {
                return $name === 'Authorization' ? $authorization : null;
            }
        );

        $config = $this->createMock(Config::class);
        $config->method('getRestKey')->willReturn($restKey);
        $config->method('getDiscountRules')->willReturn([
            0 => 'fixedValue',
            1 => 'percentage',
            2 => 'freeShipping',
        ]);
        $config->method('getAllowExport')->willReturn($allowExport);

        $this->func = new Func(
            $config,
            $request,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(FileSystem::class),
            $this->createMock(RawFactory::class)
        );
    }
}
