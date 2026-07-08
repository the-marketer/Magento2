<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

/**
 * Usage:
 *  echo Array2XML::cXML("root_node_name",$array)->saveXML();
 */

namespace Mktr\Tracker\Model;

use DOMDocument;
use DOMImplementation;
use Exception;

class Array2XML
{
    const DEFAULT_DOM_VERSION = '1.0';
    const DEFAULT_ENCODING = 'UTF-8';
    const DEFAULT_STANDALONE = false;
    const DEFAULT_FORMAT_OUTPUT = true;

    const LABEL_ATTRIBUTES = '@attributes';
    const LABEL_CDATA = '@cdata';
    const LABEL_DOCTYPE = '@docType';
    const LABEL_VALUE = '@value';

    /**
     * @var DOMDocument|null
     */
    private $xml = null;

    /**
     * @var string
     */
    private $domVersion;

    /**
     * @var string
     */
    private $encoding;

    /**
     * @var bool
     */
    private $standalone;

    /**
     * @var bool
     */
    private $formatOutput;

    /**
     * @var string
     */
    private $labelAttributes;

    /**
     * @var string
     */
    private $labelCData;

    /**
     * @var string
     */
    private $labelDocType;

    /**
     * @var string
     */
    private $labelValue;

    public function __construct(
        ?string $version = null,
        ?string $encoding = null,
        ?bool $standalone = null,
        ?bool $formatOutput = null,
        ?string $labelAttributes = null,
        ?string $labelCData = null,
        ?string $labelDocType = null,
        ?string $labelValue = null
    ) {
        $this->domVersion = $version ?? self::DEFAULT_DOM_VERSION;
        $this->encoding = $encoding ?? self::DEFAULT_ENCODING;
        $this->standalone = $standalone ?? self::DEFAULT_STANDALONE;
        $this->formatOutput = $formatOutput ?? self::DEFAULT_FORMAT_OUTPUT;
        $this->labelAttributes = $labelAttributes ?? self::LABEL_ATTRIBUTES;
        $this->labelCData = $labelCData ?? self::LABEL_CDATA;
        $this->labelDocType = $labelDocType ?? self::LABEL_DOCTYPE;
        $this->labelValue = $labelValue ?? self::LABEL_VALUE;
    }

    public static function init(
        ?string $version = null,
        ?string $encoding = null,
        ?bool $standalone = null,
        ?bool $format_output = null,
        ?string $labelAttributes = null,
        ?string $labelCData = null,
        ?string $labelDocType = null,
        ?string $labelValue = null
    ) {
        return new self(
            $version,
            $encoding,
            $standalone,
            $format_output,
            $labelAttributes,
            $labelCData,
            $labelDocType,
            $labelValue
        );
    }

    public static function getDomVersion(): string
    {
        return self::DEFAULT_DOM_VERSION;
    }

    public static function getEncoding(): string
    {
        return self::DEFAULT_ENCODING;
    }

    /** @noinspection PhpUnused */
    public static function isStandalone(): bool
    {
        return self::DEFAULT_STANDALONE;
    }

    public static function isFormatOutput(): bool
    {
        return self::DEFAULT_FORMAT_OUTPUT;
    }

    /** @noinspection PhpUnused */
    public static function getLabelAttributes(): string
    {
        return self::LABEL_ATTRIBUTES;
    }

    /** @noinspection PhpUnused */
    public static function getLabelCData(): string
    {
        return self::LABEL_CDATA;
    }

    /** @noinspection PhpUnused */
    public static function getLabelDocType(): string
    {
        return self::LABEL_DOCTYPE;
    }

    /** @noinspection PhpUnused */
    public static function getLabelValue(): string
    {
        return self::LABEL_VALUE;
    }

    /** @noinspection PhpUnused */
    public function createXML($node_name, $arr = null, $docType = [])
    {
        $this->xml = new DomDocument($this->domVersion, $this->encoding);
        // $this->xml->xmlStandalone = $this->standalone;
        $this->xml->formatOutput = $this->formatOutput;

        if ($docType) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->xml->appendChild(
                (new DOMImplementation())
                    ->createDocumentType(
                        $docType['name'] ?? '',
                        $docType['publicId'] ?? '',
                        $docType['systemId'] ?? ''
                    )
            );
        }

        if ($arr == null) {
            foreach ($node_name as $key => $value) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->xml->appendChild($this->convert($key, $value));
            }
        } else {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->xml->appendChild($this->convert($node_name, $arr));
        }

        $xml = $this->xml;
        $this->xml = null;

        return $xml;
    }

    public static function cXML($node_name, $arr = null, $docType = [])
    {
        return (new self())->createXML($node_name, $arr, $docType);
    }

    private static function bool2str($v)
    {
        return $v === true ? 'true' : ($v === false ? 'false' : ($v === null ? '' : $v));
    }

    /** @noinspection PhpUnused */
    public function getConvert($node_name, $arr = [])
    {
        $this->ensureXmlRoot();

        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->convert($node_name, $arr);
    }

    private function convert($node_name, $arr = [])
    {
        $xml = $this->ensureXmlRoot();
        /** @noinspection PhpExpressionAlwaysNullInspection */
        $node = $xml->createElement($node_name);
        if (is_array($arr)) {
            if (array_key_exists($this->labelAttributes, $arr) && is_array($arr[$this->labelAttributes])) {
                foreach ($arr[$this->labelAttributes] as $key => $value) {
                    if (!self::isValidTagName($key)) {
                        /** @noinspection PhpUnhandledExceptionInspection */
                        throw new Exception('[Array2XML] Illegal character in attribute name. attribute: ' . $key . ' in node: ' . $node_name);
                    }
                    $node->setAttribute($key, self::bool2str($value));
                }
                unset($arr[$this->labelAttributes]);
            }

            if (array_key_exists($this->labelValue, $arr)) {
                /** @noinspection PhpExpressionAlwaysNullInspection */
                $node->appendChild($xml->createTextNode(self::bool2str($arr[$this->labelValue])));
                unset($arr[$this->labelValue]);
                return $node;
            } elseif (array_key_exists($this->labelCData, $arr)) {
                /** @noinspection PhpExpressionAlwaysNullInspection */
                $node->appendChild($xml->createCDATASection(self::bool2str($arr[$this->labelCData])));
                unset($arr[$this->labelCData]);
                return $node;
            }
        }

        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                if (!self::isValidTagName($key)) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    throw new Exception('[Array2XML] Illegal character in tag name. tag: ' . $key . ' in node: ' . $node_name);
                }
                if (is_array($value) && is_numeric(key($value))) {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    foreach ($value as $k => $v) {
                        $node->appendChild($this->convert($key, $v));
                    }
                } else {
                    $node->appendChild($this->convert($key, $value));
                }
                unset($arr[$key]);
            }
        }

        if (!is_array($arr)) {
            /** @noinspection PhpExpressionAlwaysNullInspection */
            $node->appendChild($xml->createTextNode(self::bool2str($arr)));
        }

        return $node;
    }

    private function ensureXmlRoot()
    {
        if ($this->xml === null) {
            $this->xml = new DomDocument($this->domVersion, $this->encoding);
            $this->xml->formatOutput = $this->formatOutput;
        }

        return $this->xml;
    }

    private static function isValidTagName($tag): bool
    {
        /** @noinspection RegExpRedundantEscape */
        /** @noinspection RegExpSimplifiable */
        $pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';

        return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
    }
}
