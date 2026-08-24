<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Helper;

use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\SessionFactory as CatalogSessionFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\GroupFactory;
use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Model\ItemFactory as WishlistItemFactory;
use Mktr\Tracker\Model\Api;
use Mktr\Tracker\Model\Array2XML;
use Mktr\Tracker\Model\Config;
use Mktr\Tracker\Model\Data as TrackerData;
use Mktr\Tracker\Model\FileSystem;
use Mktr\Tracker\Model\Func;
use Mktr\Tracker\Model\Manager;
use Mktr\Tracker\Model\Pages\FeedFactory;
use Mktr\Tracker\Model\Pages\ReviewsFactory;
use Mktr\Tracker\Model\Pages\SubscribesFactory;
use Mktr\Tracker\Model\ReviewLogs;

class Data extends AbstractHelper
{
    const sessionName = "Mk";
    const space = PHP_EOL . "        ";

    /**
     * @var Func
     */
    private $func;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var FileSystem
     */
    private $fileSystem;

    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var Array2XML
     */
    private $array2XML;

    /**
     * @var TrackerData
     */
    private $trackerData;

    /**
     * @var ReviewLogs
     */
    private $reviewLogs;

    /**
     * @var ReviewsFactory
     */
    private $pagesReviewsFactory;

    /**
     * @var SubscribesFactory
     */
    private $pagesSubscribesFactory;

    /**
     * @var FeedFactory
     */
    private $pagesFeedFactory;

    /**
     * @var \Mktr\Tracker\Model\Pages\Reviews|null
     */
    private $pagesReviews = null;

    /**
     * @var \Mktr\Tracker\Model\Pages\Subscribes|null
     */
    private $pagesSubscribes = null;

    /**
     * @var \Mktr\Tracker\Model\Pages\Feed|null
     */
    private $pagesFeed = null;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepo;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ProductAttributeMediaGalleryManagementInterface
     */
    private $productMedia;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $brandsRepository;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var CategoryHelper
     */
    private $categoriesData;

    /**
     * @var WishlistItemFactory
     */
    private $wishlistItemFactory;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var CatalogSessionFactory
     */
    private $catalogSessionFactory;

    /**
     * @var GroupFactory
     */
    private $customerGroupFactory;

    /**
     * @var CustomerSessionFactory
     */
    private $customerSessionFactory;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var AddressFactory
     */
    private $customerAddressFactory;

    /**
     * @var RawFactory
     */
    private $rawFactory;

    /**
     * @var \Magento\Catalog\Model\Product|null
     */
    private $productRepoInstance;

    /**
     * @var \Magento\Customer\Model\Group|null
     */
    private $customerGroupInstance;

    /**
     * @var \Magento\Customer\Model\Session|null
     */
    private $customerSessionInstance;

    /**
     * @var \Magento\Catalog\Model\Session|null
     */
    private $catalogSessionInstance;

    /**
     * @var \Magento\Wishlist\Model\Item|null
     */
    private $wishlistItemInstance;

    public function __construct(
        Context $context,
        Func $func,
        Config $config,
        Api $api,
        FileSystem $fileSystem,
        Manager $manager,
        Array2XML $array2XML,
        TrackerData $trackerData,
        ReviewLogs $reviewLogs,
        ReviewsFactory $pagesReviewsFactory,
        SubscribesFactory $pagesSubscribesFactory,
        FeedFactory $pagesFeedFactory,
        HttpRequest $request,
        StoreManagerInterface $storeManager,
        StoreRepositoryInterface $storeRepo,
        Registry $registry,
        ProductMetadataInterface $productMetadata,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        ProductCollectionFactory $productCollectionFactory,
        ProductAttributeMediaGalleryManagementInterface $productMedia,
        StockRegistryInterface $stockRegistry,
        ProductAttributeRepositoryInterface $brandsRepository,
        Order $order,
        CategoryFactory $categoryFactory,
        CategoryHelper $categoriesData,
        WishlistItemFactory $wishlistItemFactory,
        TaxHelper $taxHelper,
        CatalogSessionFactory $catalogSessionFactory,
        GroupFactory $customerGroupFactory,
        CustomerSessionFactory $customerSessionFactory,
        CustomerFactory $customerFactory,
        AddressFactory $customerAddressFactory,
        RawFactory $rawFactory
    ) {
        parent::__construct($context);
        $this->func = $func;
        $this->config = $config;
        $this->api = $api;
        $this->fileSystem = $fileSystem;
        $this->manager = $manager;
        $this->array2XML = $array2XML;
        $this->trackerData = $trackerData;
        $this->reviewLogs = $reviewLogs;
        $this->pagesReviewsFactory = $pagesReviewsFactory;
        $this->pagesSubscribesFactory = $pagesSubscribesFactory;
        $this->pagesFeedFactory = $pagesFeedFactory;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->storeRepo = $storeRepo;
        $this->registry = $registry;
        $this->productMetadata = $productMetadata;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productMedia = $productMedia;
        $this->stockRegistry = $stockRegistry;
        $this->brandsRepository = $brandsRepository;
        $this->order = $order;
        $this->categoryFactory = $categoryFactory;
        $this->categoriesData = $categoriesData;
        $this->wishlistItemFactory = $wishlistItemFactory;
        $this->taxHelper = $taxHelper;
        $this->catalogSessionFactory = $catalogSessionFactory;
        $this->customerGroupFactory = $customerGroupFactory;
        $this->customerSessionFactory = $customerSessionFactory;
        $this->customerFactory = $customerFactory;
        $this->customerAddressFactory = $customerAddressFactory;
        $this->rawFactory = $rawFactory;
    }

    public function __get($property)
    {
        switch ($property) {
            case 'getFunc':
                return $this->func;
            case 'getConfig':
                return $this->config;
            case 'getApi':
                return $this->api;
            case 'getFileSystem':
                return $this->fileSystem;
            case 'getManager':
                return $this->manager;
            case 'getArray2XML':
                return $this->array2XML;
            case 'getData':
                return $this->trackerData;
            case 'getReviewLogs':
                return $this->reviewLogs;
            case 'getPagesReviews':
                if ($this->pagesReviews === null) {
                    $this->pagesReviews = $this->pagesReviewsFactory->create();
                }
                return $this->pagesReviews;
            case 'getPagesSubscribes':
                if ($this->pagesSubscribes === null) {
                    $this->pagesSubscribes = $this->pagesSubscribesFactory->create();
                }
                return $this->pagesSubscribes;
            case 'getPagesFeed':
                if ($this->pagesFeed === null) {
                    $this->pagesFeed = $this->pagesFeedFactory->create();
                }
                return $this->pagesFeed;
            case 'getRequest':
                return $this->request;
            case 'getWebsite':
                return $this->storeManager->getWebsite();
            case 'getBaseUrl':
                return $this->storeManager->getStore()->getBaseUrl();
            case 'getStore':
                return $this->storeManager->getStore();
            case 'getStoreManager':
                return $this->storeManager;
            case 'getStoreRepo':
                return $this->storeRepo;
            case 'getCustomerGroup':
                if ($this->customerGroupInstance === null) {
                    $this->customerGroupInstance = $this->customerGroupFactory->create();
                }
                return $this->customerGroupInstance;
            case 'getCustomerSession':
                if ($this->customerSessionInstance === null) {
                    $this->customerSessionInstance = $this->customerSessionFactory->create();
                }
                return $this->customerSessionInstance;
            case 'getCustomerData':
                return $this->customerFactory->create();
            case 'getCustomerAddress':
                return $this->customerAddressFactory->create();
            case 'getProduct':
                return $this->productRepository;
            case 'getProductRepo':
                if ($this->productRepoInstance === null) {
                    $this->productRepoInstance = $this->productFactory->create();
                }
                return $this->productRepoInstance;
            case 'getProductCol':
                return $this->productCollectionFactory;
            case 'getProductMedia':
                return $this->productMedia;
            case 'getStockRepo':
                return $this->stockRegistry;
            case 'getBrands':
                return $this->brandsRepository;
            case 'getOrderRepo':
                return $this->order;
            case 'getCategoryRepo':
                return $this->categoryFactory->create();
            case 'getCategoriesData':
                return $this->categoriesData;
            case 'getWishItem':
                if ($this->wishlistItemInstance === null) {
                    $this->wishlistItemInstance = $this->wishlistItemFactory->create();
                }
                return $this->wishlistItemInstance;
            case 'getTax':
                return $this->taxHelper;
            case 'getSession':
                if ($this->catalogSessionInstance === null) {
                    $this->catalogSessionInstance = $this->catalogSessionFactory->create();
                }
                return $this->catalogSessionInstance;
            case 'getMageVersion':
                return $this->productMetadata->getVersion();
            case 'getSpace':
                return self::getSpace();
            case 'getSessionName':
                return self::getSessionName();
            case 'getPageRaw':
                return $this->rawFactory->create();
            default:
                return null;
        }
    }

    public function getRegistry($registryName)
    {
        return $this->registry->registry($registryName);
    }

    public static function getSpace(): string
    {
        return self::space;
    }

    public static function getSessionName(): string
    {
        return self::sessionName;
    }
}
