<?php
/** @noinspection SpellCheckingInspection */
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Model;

use Mktr\Tracker\Helper\Data;

class Manager
{
    private static $data = [];
    private static $assets = [];
    private static $bMultiCat = [];
    private static $cons = null;

    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $shName = null;

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
        "__sm__set_email" => "setEmail",
        "__sm__set_phone" => "setPhone"
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

        "setPhone" => [
            "phone" => "phone"
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
            "lastname" => "lastname"
        ]
    ];

    public function __construct(Data $help)
    {
        self::$ins['Help'] = $help;
        self::$cons = $this;
    }


    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    public static function getEvent($Name, $eventData = [])
    {
        if (empty(self::eventsName[$Name]))
        {
            return false;
        }

        self::$shName = self::eventsName[$Name];

        self::$data = [
            "event" => $Name
        ];

        self::$assets = [];

        switch (self::$shName){
            case "Category":
                self::$assets['category'] = self::buildCategory(self::getHelp()->getRegistry('current_category'));
                break;
            case "Product":
                self::$assets['product_id'] = self::getHelp()->getRegistry('current_product')->getId();
                break;
            case "Search":
                self::$assets['search_term'] = self::getHelp()->getRequest->getParam('q');
                break;
            default:
                self::$assets = $eventData;
        }

        self::$assets = self::schemaValidate(self::$assets, self::eventsSchema[self::$shName]);

        self::build();

        if (self::$cons == null)
        {
            return new self(self::getHelp());
        } else {
            return self::$cons;
        }
    }

    public static function getEventsSchema($sName = null)
    {
        return $sName === null ? self::eventsSchema : self::eventsSchema[$sName];
    }

    public static function schemaValidate($array, $schema): ?array
    {
        $newOut = [];

        foreach ($array as $key=>$val) {
            if (isset($schema[$key])){
                if (is_array($val)) {
                    $newOut[$schema[$key]["@key"]] = self::schemaValidate($val, $schema[$key]["@schema"]);
                } else {
                    $newOut[$schema[$key]] = $val;
                }
            } else if (is_array($val)){
                $newOut[] = self::schemaValidate($val, $schema);
            }
        }

        return $newOut;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public static function buildMultiCategory($List)
    {
        self::$bMultiCat = [];
        foreach ($List as $key=>$value) {
            $categoryRegistry = self::getHelp()->getCategoryRepo->load($value);
            self::buildSingleCategory($categoryRegistry);
        }

        if (empty(self::$bMultiCat))
        {
            self::$bMultiCat[] = "Default Category";
        }
        return implode("|", array_reverse(self::$bMultiCat));
    }

    public static function buildSingleCategory($categoryRegistry)
    {
        if ($categoryRegistry->getId() != 2)
        {
            self::$bMultiCat[] = $categoryRegistry->getName();

            while ($categoryRegistry->getLevel() > 2) {

                $categoryRegistry = self::getHelp()->getCategoryRepo->load($categoryRegistry->getParentId());

                self::$bMultiCat[] = $categoryRegistry->getName();
            }
        }
    }
    public static function buildCategory($categoryRegistry)
    {
        if ($categoryRegistry->getId() != 2)
        {
            $build = [ $categoryRegistry->getName() ];
            while ($categoryRegistry->getLevel() > 2) {

                $categoryRegistry = self::getHelp()->getCategoryRepo->load($categoryRegistry->getParentId());

                $build[] = $categoryRegistry->getName();
            }

            return implode("|", array_reverse($build));
        }
    }

    public static function build()
    {
        foreach (self::$assets as $key=>$val) {
            self::$data[$key] = $val;
        }
    }

    public function toJson(){
        return self::getHelp()->getFunc->toJson(self::$data);
    }
}
