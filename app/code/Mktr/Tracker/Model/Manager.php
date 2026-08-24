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

use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;

class Manager
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    private $assets = [];

    /**
     * @var array
     */
    private $bMultiCat = [];

    /**
     * @var Func
     */
    private $func;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    const eventsName = [
        "__sm__view_homepage" =>"HomePage",
        "__sm__view_category" => "Category",
        "__sm__view_brand" => "Brand",
        "__sm__view_product" => "Product",
        "__sm__add_to_cart" => "addToCart",
        "__sm__remove_from_cart" => "removeFromCart",
        "__sm__add_to_wishlist" => "addToWishlist",
        "__sm__remove_from_wishlist" => "removeFromWishlist",
        "__sm__initiate_checkout" => "Checkout",
        "__sm__order" => "saveOrder",
        "__sm__search" => "Search",
        "__sm__set_email" => "setEmail"
    ];

    const eventsSchema = [
        "HomePage" => null,
        "Checkout" => null,
        "Cart" => null,

        "Category" => [
            "category" => "category"
        ],

        "Brand" => [
            "name" => "name"
        ],

        "Product" => [
            "product_id" => "product_id"
        ],

        "Search" => [
            "search_term" => "search_term"
        ],

        "addToWishlist" => [
            "product_id" => "product_id",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "removeFromWishlist" => [
            "product_id" => "product_id",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "addToCart" => [
            "product_id" => "product_id",
            "quantity" => "quantity",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "removeFromCart" => [
            "product_id" => "product_id",
            "quantity" => "quantity",
            "variation" => [
                "@key" => "variation",
                "@schema" => [
                    "id" => "id",
                    "sku" => "sku"
                ]
            ]
        ],

        "saveOrder" => [
            "number" => "number",
            "email_address" => "email_address",
            "phone" => "phone",
            "firstname" => "firstname",
            "lastname" => "lastname",
            "city" => "city",
            "county" => "county",
            "address" => "address",
            "discount_value" => "discount_value",
            "discount_code" => "discount_code",
            "shipping" => "shipping",
            "tax" => "tax",
            "total_value" => "total_value",
            "products" => [
                "@key" => "products",
                "@schema" =>
                    [
                        "product_id" => "product_id",
                        "price" => "price",
                        "quantity" => "quantity",
                        "variation_sku" => "variation_sku"
                    ]
            ]
        ],

        "setEmail" => [
            "email_address" => "email_address",
            "firstname" => "firstname",
            "lastname" => "lastname",
            "phone" => "phone"
        ]
    ];

    public function __construct(
        Func $func,
        Registry $registry,
        HttpRequest $request,
        CategoryFactory $categoryFactory
    ) {
        $this->func = $func;
        $this->registry = $registry;
        $this->request = $request;
        $this->categoryFactory = $categoryFactory;
    }

    public function getEvent($Name, $eventData = [])
    {
        if (empty(self::eventsName[$Name])) {
            return false;
        }

        $shName = self::eventsName[$Name];

        $this->data = [
            "event" => $Name
        ];

        $this->assets = [];

        switch ($shName) {
            case "Category":
                $this->assets['category'] = $this->buildCategory($this->registry->registry('current_category'));
                break;
            case "Product":
                $this->assets['product_id'] = $this->registry->registry('current_product')->getId();
                break;
            case "Search":
                $this->assets['search_term'] = $this->request->getParam('q');
                break;
            default:
                $this->assets = $eventData;
        }

        $this->assets = $this->schemaValidate($this->assets, self::eventsSchema[$shName]);
        $this->build();

        return $this;
    }

    public function getEventsSchema($sName = null)
    {
        return $sName === null ? self::eventsSchema : self::eventsSchema[$sName];
    }

    public function schemaValidate($array, $schema): ?array
    {
        $newOut = [];

        foreach ($array as $key => $val) {
            if (isset($schema[$key])) {
                if (is_array($val)) {
                    $newOut[$schema[$key]["@key"]] = $this->schemaValidate($val, $schema[$key]["@schema"]);
                } else {
                    $newOut[$schema[$key]] = $val;
                }
            } elseif (is_array($val)) {
                $newOut[] = $this->schemaValidate($val, $schema);
            }
        }

        return $newOut;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function buildMultiCategory($List)
    {
        $this->bMultiCat = [];
        foreach ($List as $value) {
            $categoryRegistry = $this->categoryFactory->create()->load($value);

            if ($categoryRegistry->getLevel() == 2) {
                $this->bMultiCat[$categoryRegistry->getPath()][] = $categoryRegistry->getName();
            } else {
                $add = true;
                foreach ($this->bMultiCat as $pathKey => $pathValue) {
                    if (strpos($categoryRegistry->getPath(), $pathKey) !== false) {
                        $this->bMultiCat[$pathKey][] = $categoryRegistry->getName();
                        $add = false;
                    }
                }
                if ($add) {
                    $this->bMultiCat[$categoryRegistry->getPath()][] = $categoryRegistry->getName();
                }
            }
        }
        if (empty($this->bMultiCat)) {
            $this->bMultiCat[] = "Default Category";
        }

        $subTrees = [];
        foreach ($this->bMultiCat as $categoryTree) {
            if (is_array($categoryTree)) {
                $subTrees[] = implode('|', $categoryTree);
            } else {
                $subTrees[] = $categoryTree;
            }
        }

        $categoriesTree = $subTrees;
        if (is_array($categoriesTree)) {
            $categoriesTree = implode('||', $subTrees);
        }

        return $categoriesTree;
    }

    public function buildSingleCategory($categoryRegistry)
    {
        if ($categoryRegistry->getId() != 2) {
            $this->bMultiCat[] = $categoryRegistry->getName();

            while ($categoryRegistry->getLevel() > 2) {
                $categoryRegistry = $this->categoryFactory->create()->load($categoryRegistry->getParentId());
                $this->bMultiCat[] = $categoryRegistry->getName();
            }
        }
    }

    public function buildCategory($categoryRegistry)
    {
        if ($categoryRegistry->getId() != 2) {
            $build = [$categoryRegistry->getName()];
            while ($categoryRegistry->getLevel() > 2) {
                $categoryRegistry = $this->categoryFactory->create()->load($categoryRegistry->getParentId());
                $build[] = $categoryRegistry->getName();
            }

            return implode("|", array_reverse($build));
        }

        return null;
    }

    public function build()
    {
        foreach ($this->assets as $key => $val) {
            $this->data[$key] = $val;
        }
    }

    public function toJson()
    {
        return $this->func->toJson($this->data);
    }
}
