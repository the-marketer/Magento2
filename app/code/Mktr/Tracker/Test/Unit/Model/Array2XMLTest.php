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

use DOMDocument;
use DOMXPath;
use Mktr\Tracker\Model\Array2XML;
use PHPUnit\Framework\TestCase;

class Array2XMLTest extends TestCase
{
    protected function setUp(): void
    {
        Array2XML::init();
    }

    public function testConvertsNestedArraysAttributesAndRepeatedNodes(): void
    {
        $xml = Array2XML::cXML(
            'products',
            [
                'product' => [
                    [
                        '@attributes' => [
                            'sku' => 'shoe-1',
                            'enabled' => true,
                        ],
                        'name' => 'Running Shoe',
                        'price' => '99.90',
                    ],
                    [
                        '@attributes' => [
                            'sku' => 'bag-1',
                            'enabled' => false,
                        ],
                        'name' => 'Gym Bag',
                        'price' => '39.50',
                    ],
                ],
            ]
        );

        $document = $this->loadXml($xml->saveXML());
        $xpath = new DOMXPath($document);

        $this->assertSame('2', (string) $xpath->evaluate('count(/products/product)'));
        $this->assertSame('shoe-1', $xpath->evaluate('string(/products/product[1]/@sku)'));
        $this->assertSame('true', $xpath->evaluate('string(/products/product[1]/@enabled)'));
        $this->assertSame('Running Shoe', $xpath->evaluate('string(/products/product[1]/name)'));
        $this->assertSame('bag-1', $xpath->evaluate('string(/products/product[2]/@sku)'));
        $this->assertSame('false', $xpath->evaluate('string(/products/product[2]/@enabled)'));
        $this->assertSame('39.50', $xpath->evaluate('string(/products/product[2]/price)'));
    }

    public function testConvertsValueAndCdataNodes(): void
    {
        $xml = Array2XML::cXML(
            'item',
            [
                'title' => [
                    '@value' => 'Plain title',
                ],
                'description' => [
                    '@cdata' => 'Sizes <S> & <M>',
                ],
            ]
        );

        $document = $this->loadXml($xml->saveXML());
        $xpath = new DOMXPath($document);

        $this->assertSame('Plain title', $xpath->evaluate('string(/item/title)'));
        $this->assertSame('Sizes <S> & <M>', $xpath->evaluate('string(/item/description)'));
        $this->assertStringContainsString('<![CDATA[Sizes <S> & <M>]]>', $xml->saveXML());
    }

    public function testAddsDocumentTypeWhenProvided(): void
    {
        $xml = Array2XML::cXML(
            'products',
            ['product' => ['name' => 'Running Shoe']],
            [
                'name' => 'products',
                'publicId' => '-//TheMarketer//Products Feed//EN',
                'systemId' => 'products.dtd',
            ]
        );

        $document = $this->loadXml($xml->saveXML());

        $this->assertNotNull($document->doctype);
        $this->assertSame('products', $document->doctype->name);
        $this->assertSame('-//TheMarketer//Products Feed//EN', $document->doctype->publicId);
        $this->assertSame('products.dtd', $document->doctype->systemId);
    }

    public function testRejectsInvalidElementNames(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Illegal character in tag name|Invalid Character Error/');

        Array2XML::cXML('products', ['bad tag' => 'value']);
    }

    public function testRejectsInvalidAttributeNames(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Illegal character in attribute name|Invalid Character Error/');

        Array2XML::cXML(
            'product',
            [
                '@attributes' => [
                    'bad attribute' => 'value',
                ],
            ]
        );
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));

        return $document;
    }
}
