<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Pages;

use Exception;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;

class Feed
{
    // private static $cons = null;
    private static $ins = [
        "Help" => null,
        "MSI" => null
    ];

    private static $error = null;
    private static $params = null;
    private static $fileName = "products";
    private static $secondName = "product";

    private static $MSI = null;

    private static $SourceMSI = null;

    private static $data;
    private static $attr;
    private static $imageLink = null;

    public static function getName()
    {
        return self::$fileName;
    }

    public static function getSecondName()
    {
        return self::$secondName;
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }
    
    /** TODO: Magento 2 */
    public static function getMSI()
    {
        if (self::$ins["MSI"] == null) {
            self::$ins["MSI"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface');
        }
        return self::$ins["MSI"];
    }

    private static function status()
    {
        return self::$error == null;
    }

    private static function buildImageUrl($img): string
    {
        if ($img === null) { $img = ''; }
        if (self::$imageLink === null) {
            /** TODO: Magento 2 */
            self::$imageLink = self::getHelp()->getStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product' ;
        }
        return self::$imageLink . (substr($img, 0, 1) === '/' ? '' : '/') . $img;
    }

    private static function getProductImage($product): string
    {
        $img = $product->getImage();
        if ($img === null) { $img = ''; }
        if (self::$imageLink === null) {
            /** TODO: Magento 2 */
            self::$imageLink = self::getHelp()->getStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
        }
        return self::$imageLink . (substr($img, 0, 1) === '/' ? '' : '/') . $img;
    }

    public static function getProductById($id)
    {
        try {
            $product = self::getHelp()->getProduct->getById($id, false, self::getHelp()->getFunc->getStoreId(), true);
            return self::buildProduct($product);
        } catch (Exception $e) {
            return false;
        }
    }

    /** @noinspection PhpUnused */
    public static function getProductBySku($sku)
    {
        try {
            $product = self::getHelp()->getProduct->get($sku, false, self::getHelp()->getFunc->getStoreId(), true);
            return self::buildProduct($product);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function freshData(): array
    {
        $or = [];
        $stop = false;

        self::$params = self::getHelp()->getRequest->getParams();

        self::$attr['brand'] = self::getHelp()->getConfig->getBrandAttribute();
        self::$attr['color'] = self::getHelp()->getConfig->getColorAttribute();
        self::$attr['size'] = self::getHelp()->getConfig->getSizeAttribute();

        if (isset(self::$params['page'])) {
            $stop = true;
            self::$params['page'] = (int) self::$params['page'];
        } else {
            self::$params['page'] = 1;
        }

        self::$params['page'] = max(1, (int) (self::$params['page'] ?? 1));
        self::$params['limit'] = (int) (self::$params['limit'] ?? 50);

        $storeId = self::getHelp()->getFunc->getStoreId();

        self::$data['products'] = self::getHelp()->getProductCol->create()
            ->setPageSize(self::$params['limit'])
            ->setOrder('created_at', 'ASC')
            ->addAttributeToSelect(['id'])
            ->addStoreFilter($storeId)
            ->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter( ['attribute' => 'price', 'gt' => 0]);


        $lastPage = self::$data['products']->getLastPageNumber();

        if ($lastPage >= self::$params['page']) {
            $pages = $stop ? self::$params['page'] : self::$data['products']->getLastPageNumber();
            do {
                self::$data['products']->setCurPage(self::$params['page'])->load();
                foreach (self::$data['products'] as $product) {
                    $oo = self::getProductById($product->getId());
                    if ($oo !== false) {
                        $or[] = $oo;
                    }
                }
                self::$params['page']++;
                self::$data['products']->clear();
            } while (self::$params['page'] <= $pages);
        }

        return $or;
    }

    public static function checkMSI () {
        if (self::$MSI === null) {
            if (\Magento\Framework\App\ObjectManager::getInstance()->get("\Magento\Framework\Module\Manager")->isEnabled('Magento_Inventory')) {
                self::$MSI = true;
            } else {
                self::$MSI = false;
            }
        }
        return self::$MSI;
    }

    public static function AddQTY($item = null, $MasterQty = 0) {
        if (self::$SourceMSI === null) {
           self::$SourceMSI = self::getHelp()->getConfig->getStockSource();
        }
        if ($item !== null) {
            if (self::$SourceMSI === 'all' || $item->getSourceCode() === self::$SourceMSI) {
                if ($item->getQuantity() > 0) {
                    $MasterQty = $MasterQty + $item->getQuantity();
                }
            }
        }
        return $MasterQty;
    }

    public static function buildProduct($product)
    {
        $listCategory = self::getHelp()->getManager->buildMultiCategory($product->getCategoryIds());

        $price = $product->getPriceInfo()->getPrice('regular_price')->getValue();
        $finalPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();

        if (empty((float) $finalPrice) && empty((float) $price)) {
            return false;
        }
        $salePrice = empty((float) $finalPrice) ? $price : $finalPrice;

        $price = empty((float) $price) ? $finalPrice : $price;
        $taxID = $product->getTaxClassId();
        if ($taxID) {
            $price = self::getHelp()->getTax->getTaxPrice($product, $price, true);
            $salePrice = $salePrice > 0 ? self::getHelp()->getTax->getTaxPrice($product, $salePrice, true) : $price;
        }

        $media_gallery = [
            'image'=>[]
        ];

        /** TODO: Magento 2 */
        $gal = self::getHelp()->getProductMedia->getList($product->getSku());
        if ($gal !== null) {
            foreach ($gal as $img) {
                if ($img['disabled'] != '0' || $img['file'] === $product->getImage()) {
                    continue;
                }
                $media_gallery['image'][] = self::buildImageUrl($img['file']);
            }
        }

        $variations = [
            'variation' => []
        ];
        /** TODO: Magento 2 */
        if (self::checkMSI()) {
            $MasterQty = 0;
            foreach (self::getMSI()->execute($product->getSku()) as $item) {
                $MasterQty = self::AddQTY($item, $MasterQty);
            }
        } else {
            $MasterQty = (int) (self::getHelp()->getStockRepo->getStockItem($product->getId())->getQty() ?? 0);
        }

        if ($product->getTypeId() == 'configurable') {
            $variants = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($variants as $p) {

                $vPrice = $p->getPriceInfo()->getPrice('regular_price')->getValue();
                if (!empty((float)$vPrice)) {

                    $vFinalPrice = $p->getPriceInfo()->getPrice('final_price')->getValue();
                    $vSalePrice = empty((float)$vFinalPrice) ? $vPrice : $vFinalPrice;
                    $attribute = [
                        'color' => null,
                        'size' => null
                    ];

                    foreach (self::$attr['color'] as $v) {
                        if ($p->getData($v) !== null) {
                            $attribute['color'] = $p->getAttributeText($v);
                            if (!empty($attribute['color'])) {
                                break;
                            }
                        }
                    }

                    foreach (self::$attr['size'] as $v) {
                        if ($p->getData($v) !== null) {
                            $attribute['size'] = $p->getAttributeText($v);
                            if (!empty($attribute['size'])) {
                                break;
                            }
                        }
                    }
                    if (self::checkMSI()) {
                        $qty = 0;
                        foreach (self::getMSI()->execute($p->getSku()) as $item) {
                            $qty = self::AddQTY($item, $qty);
                        }
                    } else {
                        $qty = (int) (self::getHelp()->getStockRepo->getStockItem($p->getId())->getQty() ?? 0);
                    }
                    
                    $MasterQty = $MasterQty + (int) $qty;

                    /** @noinspection DuplicatedCode */
                    if ($qty < 0) { $stock = self::getHelp()->getConfig->getDefaultStock();
                    } elseif ($p->isInStock() && $qty == 0) { $stock = 2;
                    } elseif ($p->isInStock()) { $stock = 1;
                    } else { $stock = 0; }

                    if ($taxID) {
                        $vPrice = self::getHelp()->getTax->getTaxPrice($p, $vPrice, true);
                        $vSalePrice = $vSalePrice > 0 ? self::getHelp()->getTax->getTaxPrice($p, $vSalePrice, true) : $vPrice;
                    }
                    
                    $v = [
                        'id' => $p->getId(),
                        'sku' => $p->getSku(),
                        'acquisition_price' => 0,
                        'price' => self::getHelp()->getFunc->digit2($vPrice),
                        'sale_price' => self::getHelp()->getFunc->digit2($vSalePrice),
                        'size' => empty($attribute['size']) ? null : ['@cdata' => $attribute['size']],
                        'color' => empty($attribute['color']) ? null : ['@cdata' => $attribute['color']],
                        'availability' => $stock,
                        'stock' => $qty
                    ];

                    if (empty($v['size'])) {
                        unset($v['size']);
                    }

                    if (empty($v['color'])) {
                        unset($v['color']);
                    }

                    $variations['variation'][] = $v;
                }
            }
        }

        /** @noinspection DuplicatedCode */
        if ($MasterQty < 0) {
            $stock = self::getHelp()->getConfig->getDefaultStock();
        } elseif ($product->isInStock() && $MasterQty == 0) {
            $stock = 2;
        } elseif ($product->isInStock()) {
            $stock = 1;
        } else {
            $stock = 0;
        }

        if ($MasterQty < 0) {
            $defStock = self::getHelp()->getConfig->getDefaultStock();
            $MasterQty = $defStock == 2 ? 1 : $defStock;
        }

        $brand = null;

        foreach (self::$attr['brand'] as $v) {
            $brand = $product->getAttributeText($v);
            if (!empty($brand) && $brand != "false") {
                break;
            }
        }

        $brand = empty($brand) || $brand == 'false' ? 'N\A' : $brand;
        $desk = $product->getDescription();
        
        if ($desk !== null) {
            $desk = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $product->getDescription());
        } else {
            $desk = '';
        }
        
        $CreatedAt = $product->getCreatedAt();
        $CreatedAt = ($CreatedAt === null || $CreatedAt === '0000-00-00 00:00:00') ? '2000-01-01 00:00:00' : $CreatedAt;
       
        $oo = [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => ['@cdata'=>$product->getName()],
            'description' => [
                '@cdata' => $desk
            ],
            'url' => $product->getProductUrl(),
            'main_image' => self::getProductImage($product),
            'category' => [ '@cdata' => $listCategory ],
            'brand' => [ '@cdata' => $brand ],
            'acquisition_price' => 0,
            'price' => self::getHelp()->getFunc->digit2($price),
            'sale_price' => self::getHelp()->getFunc->digit2($salePrice),
            'sale_price_start_date' => self::getHelp()->getFunc->correctDate($product->getSpecialFromDate()),
            'sale_price_end_date' => self::getHelp()->getFunc->correctDate($product->getSpecialToDate()),
            'availability' => $stock,
            'stock' => $MasterQty,
            'media_gallery' => $media_gallery,
            'variations' => $variations,
            'created_at' => self::getHelp()->getFunc->correctDate($CreatedAt),
        ];

        foreach ($oo as $key => $val) {
            if ($key == 'variations') {
                if (empty($val['variation'])) {
                    unset($oo[$key]);
                }
            } else if ($key == 'media_gallery') {
                if (empty($val['image']) && array_key_exists('main_image', $oo)) {
                    $oo[$key]['image'] = $oo['main_image'];
                }
            } else {
                if (empty($val) && $val != 0 || $val === null) {
                    unset($oo[$key]);
                }
            }
        }

        return $oo;
    }
}
