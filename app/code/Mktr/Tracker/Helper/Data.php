<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{

    const sessionName = "Mk%s";
    const space = PHP_EOL . "        ";

    private static $inst = null;

    private static $ins = [
        "getFunc" => [null,'get','\Mktr\Tracker\Model\Func'],
        "getConfig" => [null,'get','\Mktr\Tracker\Model\Config'],
        "getApi" => [null,'get','\Mktr\Tracker\Model\Api'],
        "getFileSystem" => [null,'create','\Mktr\Tracker\Model\FileSystem'],
        "getManager" => [null,'get','\Mktr\Tracker\Model\Manager'],
        "getArray2XML" => [null,'get','\Mktr\Tracker\Model\Array2XML'],
        "getData" => [null,'get','\Mktr\Tracker\Model\Data'],
        "getPagesReviews" => [null,'get','\Mktr\Tracker\Model\Pages\Reviews'],
        "getPagesFeed" => [null, 'get', '\Mktr\Tracker\Model\Pages\Feed'],
        "getRequest" => [null,'get','\Magento\Framework\App\Request\Http'],
        "getWebsite" => [null,'getWebsite','\Magento\Store\Model\StoreManagerInterface'],
        "getBaseUrl" => [null,'getBaseUrl','\Magento\Store\Model\StoreManagerInterface'],
        "getStore" => [null,'getStore','\Magento\Store\Model\StoreManagerInterface'],
        "getStoreRepo" => [null,'get','\Magento\Store\Model\StoreRepository'],
        "getCustomerGroup" => [null,'create','\Magento\Customer\Model\Group'],
        "getCustomerSession" => [null,'create','\Magento\Customer\Model\Session'],
        "getCustomerData" => [null,'get','\Magento\Customer\Model\Customer'],
        "getCustomerAddress" => [null,'get','\Magento\Customer\Model\Address'],
        "getProduct" => [null,'create','Magento\Catalog\Model\ProductRepository'],
        "getProductRepo" => [null,'create','Magento\Catalog\Model\Product'],
        "getProductCol" => [null,'create|create','\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory'],
        "getProductMedia" => [null,'create','\Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface'],
        "getStockRepo" => [null,'create','\Magento\CatalogInventory\Api\StockRegistryInterface'],
        "getBrands" => [null,'create','\Magento\Catalog\Api\ProductAttributeRepositoryInterface'],
        "getOrderRepo" => [null,'get','\Magento\Sales\Model\Order'],
        "getCategoryRepo" => [null,'create|create', '\Magento\Catalog\Model\CategoryFactory'],
        "getCategoriesData" => [null,'create', '\Magento\Catalog\Helper\Category'],
        "getWishItem" => [null,'create','\Magento\Wishlist\Model\Item'],
        "getTax" => [null,'create','\Magento\Catalog\Helper\Data'],
        "getSession" => [null,'create','\Magento\Catalog\Model\Session'],
        "getMageVersion" => [null,'getVersion','\Magento\Framework\App\ProductMetadataInterface'],
        "getSpace" => [null,'self','getSpace'],
        "getSessionName" => [null,'self','getSessionName'],
        "getRegistry" => [null,'get','\Magento\Framework\Registry'],
        "getPageRaw"=> [null, 'create|create', '\Magento\Framework\Controller\Result\RawFactory']
    ];

    /** TODO: Magento 2 */
    public static $helper = null;

    public function __construct(Context $context, \Magento\Framework\ObjectManagerInterface $objectManager)
    {
        self::$inst = $objectManager;
        // self::$ins["getConfig"][0] = $context->getScopeConfig();
        parent::__construct($context);
        self::$helper = $this;

        return $this;
    }

    private static function init()
    {
        if (self::$inst == null) {
            self::$inst = \Magento\Framework\App\ObjectManager::getInstance();
        }
        return self::$inst;
    }

    public function __get($property) {
        if (self::$ins[$property][0] == null) {
            if (self::$ins[$property][1] == 'get')
            {
                self::$ins[$property][0] = self::init()->get(self::$ins[$property][2]);
            } else if (self::$ins[$property][1] == 'getWebsite')
            {
                self::$ins[$property][0] = self::init()->get(self::$ins[$property][2])->getWebsite();
            } else if (self::$ins[$property][1] == 'getBaseUrl')
            {
                self::$ins[$property][0] = self::init()->get(self::$ins[$property][2])->getStore()->getBaseUrl();
            } else if (self::$ins[$property][1] == 'getStore')
            {
                self::$ins[$property][0] = self::init()->get(self::$ins[$property][2])->getStore();
            }else if (self::$ins[$property][1] == 'create')
            {
                self::$ins[$property][0] = self::init()->create(self::$ins[$property][2]);
            } else if (self::$ins[$property][1] == 'create|create')
            {
                self::$ins[$property][0] = self::init()->create(self::$ins[$property][2])->create();
            } else if (self::$ins[$property][1] == 'getVersion')
            {
                self::$ins[$property][0] = self::init()->get(self::$ins[$property][2])->getVersion();
            } else {
                self::$ins[$property][0] = self::{self::$ins[$property][2]}();
            }
        }
        return self::$ins[$property][0];
    }

    public static function getRegistry($registryName)
    {
        if (self::$ins["getRegistry"][0] === null)
        {
            self::$ins["getRegistry"][0] = self::init()->get(self::$ins["getRegistry"][2]);
        }

        return self::$ins["getRegistry"][0]->registry($registryName);
    }

    /** @noinspection PhpUnused */
    public static function getSpace(): string
    {
        return self::space;
    }

    /** @noinspection PhpUnused */
    public static function getSessionName(): string
    {
        return self::sessionName;
    }
}
