<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Model\Pages;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;

class Feed
{
    // private static $cons = null;
    private static $ins = [
        "Help" => null
    ];

    private static $error = null;
    private static $params = null;
    private static $fileName = "products";
    private static $secondName = "product";

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

    private static function status()
    {
        return self::$error == null;
    }

    private static function buildImageUrl($img): string
    {
        if (self::$imageLink === null)
        {
            /** TODO: Magento 2 */
            self::$imageLink = self::getHelp()->getStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
        }
        return self::$imageLink . $img;
    }

    private static function getProductImage($product): string
    {
        if (self::$imageLink === null)
        {
            /** TODO: Magento 2 */
            self::$imageLink = self::getHelp()->getStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
        }
        return self::$imageLink . $product->getImage();
    }

    public static function getProductById($id)
    {
        return self::buildProduct(self::getHelp()->getProduct->getById($id));
    }

    /** @noinspection PhpUnused */
    public static function getProductBySku($sku)
    {
        return self::buildProduct(self::getHelp()->getProduct->get($sku));
    }

    public static function freshData(): array
    {
        $or = [];

        self::$params = self::getHelp()->getRequest->getParams();

        self::$attr['brand'] = self::getHelp()->getConfig->getBrandAttribute();
        self::$attr['color'] = self::getHelp()->getConfig->getColorAttribute();
        self::$attr['size'] = self::getHelp()->getConfig->getSizeAttribute();

        self::$params['page'] = (int) (self::$params['page'] ?? 1);
        self::$params['limit'] = (int) (self::$params['limit'] ?? 50);

        self::$data['products'] = self::getHelp()->getProductCol
            // ->getCollection()
            ->setPageSize(self::$params['limit'])
            ->setOrder('created_at', 'ASC')
            ->addAttributeToSelect(array('id'))
            ->addStoreFilter(self::getHelp()->getFunc->getStoreId())
            // ->addFieldToFilter('store_id',array('in', self::getHelp()->getFunc->getStoreId()))
            ->addAttributeToFilter('visibility', array('neq' => Visibility::VISIBILITY_NOT_VISIBLE))
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);

        $pages = self::$data['products']->getLastPageNumber();

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

        return $or;
    }

    public static function buildProduct($product)
    {
        $listCategory = self::getHelp()->getManager->buildMultiCategory($product->getCategoryIds());

        $price = $product->getPrice();

        $finalPrice = $product->getFinalPrice();

        if (empty((float) $finalPrice) && empty((float) $price)) {
            return false;
        }

        $salePrice = empty((float) $finalPrice) ? $price : $finalPrice;

        $price = empty((float) $price) ? $finalPrice : $price;

        $media_gallery = [
            'image'=>[]
        ];

        /** TODO: Magento 2 */
        $gal = self::getHelp()->getProductMedia->getList($product->getSku());
        if($gal !== null) {
            foreach ($gal as $img) {
                if($img['disabled'] != '0' || $img['file'] === $product->getImage()) {
                    continue;
                }
                $media_gallery['image'][] = self::buildImageUrl($img['file']);
            }
        }

        $variations = [
            'variation' => []
        ];

        /** TODO: Magento 2 */
        $MasterQty = (int) (self::getHelp()->getStockRepo->getStockItem($product->getId())->getQty() ?? 0);

        if($product->getTypeId() == 'configurable') {
            $product->getTypeInstance()->getUsedProducts($product);

            $variants = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($variants as $p) {

                $vPrice = $p->getPrice();
                if (!empty((float)$vPrice)) {

                    $vFinalPrice = $product->getFinalPrice();
                    $vSalePrice = empty((float)$vFinalPrice) ? $vPrice : $vFinalPrice;
                    $attribute = [
                        'color' => null,
                        'size' => null
                    ];

                    foreach (self::$attr['color'] as $v)
                    {
                        $attribute['color'] = $p->getAttributeText($v);
                        if (!empty($attribute['color'])) {
                            break;
                        }
                    }

                    foreach (self::$attr['size'] as $v)
                    {
                        $attribute['size'] = $p->getAttributeText($v);
                        if (!empty($attribute['size'])) {
                            break;
                        }
                    }

                    /** TODO: Magento 2 */
                    $qty = self::getHelp()->getStockRepo->getStockItem($p->getId())->getQty();

                    $MasterQty += (int) $qty;
                    /** @noinspection DuplicatedCode */
                    if ($qty < 0) {
                        $stock = self::getHelp()->getConfig->getDefaultStock();
                    } else if ($p->isInStock() && $qty == 0) {
                        $stock = 2;
                    } else if ($p->isInStock()){
                        $stock = 1;
                    } else {
                        $stock = 0;
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
        } else if ($product->isInStock() && $MasterQty == 0) {
            $stock = 2;
        } else if ($product->isInStock()){
            $stock = 1;
        } else {
            $stock = 0;
        }

        if ($MasterQty < 0) {
            $defStock = self::getHelp()->getConfig->getDefaultStock();
            $MasterQty = $defStock == 2 ? 1 : $defStock;
        }

        $brand = null;

        foreach (self::$attr['brand'] as $v)
        {
            $brand = $product->getAttributeText($v);
            if (!empty($brand) && $brand != "false") {
                break;
            }
        }

        $brand = $brand === false || $brand == 'false' ? 'N\A' : $brand;

        $oo = [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => ['@cdata'=>$product->getName()],
            'description' => ['@cdata'=>$product->getDescription()],
            'url' => $product->getProductUrl(),
            'main_image' => self::getProductImage($product),
            'category' => [ '@cdata' => $listCategory ],
            'brand' => ['@cdata'=>$brand],
            'acquisition_price' => 0,
            'price' => self::getHelp()->getFunc->digit2($price),
            'sale_price' => self::getHelp()->getFunc->digit2($salePrice),
            'sale_price_start_date' => self::getHelp()->getFunc->correctDate($product->getSpecialFromDate()),
            'sale_price_end_date' => self::getHelp()->getFunc->correctDate($product->getSpecialToDate()),
            'availability' => $stock,
            'stock' => $MasterQty,
            'media_gallery' => $media_gallery,
            'variations' => $variations,
            'created_at' => self::getHelp()->getFunc->correctDate($product->getCreatedAt()),
        ];

        foreach ($oo as $key =>$val) {
            if ($key == 'variations') {
                if (empty($val['variation'])) {
                    unset($oo[$key]);
                }
            } elseif ($key == 'media_gallery') {
                if (empty($val['image'])) {
                    unset($oo[$key]);
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
