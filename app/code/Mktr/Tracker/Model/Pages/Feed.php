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

use Magento\Framework\UrlInterface;
use Mktr\Tracker\Api\SourceItemsBySkuResolverInterface;
use Mktr\Tracker\Helper\Data;

class Feed
{
    private $fileName = "products";
    private $secondName = "product";
    private $error = null;
    private $params = null;
    private $sourceMsi = null;
    private $data;
    private $attr;
    private $imageLink = null;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var SourceItemsBySkuResolverInterface
     */
    private $sourceItemsBySkuResolver;

    public function __construct(
        Data $helper,
        SourceItemsBySkuResolverInterface $sourceItemsBySkuResolver
    ) {
        $this->helper = $helper;
        $this->sourceItemsBySkuResolver = $sourceItemsBySkuResolver;
    }

    public function getName()
    {
        return $this->fileName;
    }

    public function getSecondName()
    {
        return $this->secondName;
    }

    private function status()
    {
        return $this->error == null;
    }

    private function buildImageUrl($img): string
    {
        if ($img === null) { $img = ''; }
        if ($this->imageLink === null) {
            $this->imageLink = $this->helper->getStore->getBaseUrl(UrlInterface::URL_TYPE_MEDIA).'catalog/product' ;
        }
        return $this->imageLink . (substr($img, 0, 1) === '/' ? '' : '/') . $img;
    }

    private function getProductImage($product): string
    {
        $img = $product->getImage();
        if ($img === null) { $img = ''; }
        if ($this->imageLink === null) {
            $this->imageLink = $this->helper->getStore->getBaseUrl(UrlInterface::URL_TYPE_MEDIA).'catalog/product';
        }
        return $this->imageLink . (substr($img, 0, 1) === '/' ? '' : '/') . $img;
    }

    public function getProductById($id)
    {
        try {
            $product = $this->helper->getProduct->getById($id, false, $this->helper->getFunc->getStoreId(), true);
            return $this->buildProduct($product);
        } catch (Exception $e) {
            return false;
        }
    }

    /** @noinspection PhpUnused */
    public function getProductBySku($sku)
    {
        try {
            $product = $this->helper->getProduct->get($sku, false, $this->helper->getFunc->getStoreId(), true);
            return $this->buildProduct($product);
        } catch (Exception $e) {
            return false;
        }
    }

    public function freshData(): array
    {
        $or = [];
        $stop = false;

        $this->params = $this->helper->getRequest->getParams();

        $this->attr['brand'] = $this->helper->getConfig->getBrandAttribute();
        $this->attr['color'] = $this->helper->getConfig->getColorAttribute();
        $this->attr['size'] = $this->helper->getConfig->getSizeAttribute();

        $stop = isset($this->params['page']);
        $this->params['page'] = $this->helper->getFunc->getPageParam();
        $this->params['limit'] = $this->helper->getFunc->getLimitParam();

        $storeId = $this->helper->getFunc->getStoreId();

        $this->data['products'] = $this->helper->getProductCol->create()
            ->setPageSize($this->params['limit'])
            ->setOrder('created_at', 'ASC')
            ->addAttributeToSelect(['id'])
            ->addStoreFilter($storeId)
            ->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter( ['attribute' => 'price', 'gt' => 0]);


        $lastPage = $this->data['products']->getLastPageNumber();

        if ($lastPage >= $this->params['page']) {
            $pages = $stop ? $this->params['page'] : $this->data['products']->getLastPageNumber();
            do {
                $this->data['products']->setCurPage($this->params['page'])->load();
                foreach ($this->data['products'] as $product) {
                    $oo = $this->getProductById($product->getId());
                    if ($oo !== false) {
                        $or[] = $oo;
                    }
                }
                $this->params['page']++;
                $this->data['products']->clear();
            } while ($this->params['page'] <= $pages);
        }

        return $or;
    }

    public function checkMSI()
    {
        return $this->sourceItemsBySkuResolver->isEnabled();
    }

    public function AddQTY($item = null, $MasterQty = 0) {
        if ($this->sourceMsi === null) {
           $this->sourceMsi = $this->helper->getConfig->getStockSource();
        }
        if ($item !== null) {
            if ($this->sourceMsi === 'all' || $item->getSourceCode() === $this->sourceMsi) {
                if ($item->getQuantity() > 0) {
                    $MasterQty = $MasterQty + $item->getQuantity();
                }
            }
        }
        return $MasterQty;
    }

    public function buildProduct($product)
    {
        $listCategory = $this->helper->getManager->buildMultiCategory($product->getCategoryIds());

        $price = $product->getPriceInfo()->getPrice('regular_price')->getValue();
        $finalPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();

        if (empty((float) $finalPrice) && empty((float) $price)) {
            return false;
        }
        $salePrice = empty((float) $finalPrice) ? $price : $finalPrice;

        $price = empty((float) $price) ? $finalPrice : $price;
        $taxID = $product->getTaxClassId();
        if ($taxID) {
            $price = $this->helper->getTax->getTaxPrice($product, $price, true);
            $salePrice = $salePrice > 0 ? $this->helper->getTax->getTaxPrice($product, $salePrice, true) : $price;
        }

        if ($salePrice > $price) {
            $salePrice = $price;
        }

        $media_gallery = [
            'image'=>[]
        ];

        $gal = $this->helper->getProductMedia->getList($product->getSku());
        if ($gal !== null) {
            foreach ($gal as $img) {
                if ($img['disabled'] != '0' || $img['file'] === $product->getImage()) {
                    continue;
                }
                $media_gallery['image'][] = $this->buildImageUrl($img['file']);
            }
        }

        $variations = [
            'variation' => []
        ];
        if ($this->checkMSI()) {
            $MasterQty = 0;
            foreach ($this->sourceItemsBySkuResolver->execute($product->getSku()) as $item) {
                $MasterQty = $this->AddQTY($item, $MasterQty);
            }
        } else {
            $MasterQty = (int) ($this->helper->getStockRepo->getStockItem($product->getId())->getQty() ?? 0);
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

                    foreach ($this->attr['color'] as $v) {
                        if ($p->getData($v) !== null) {
                            $attribute['color'] = $p->getAttributeText($v);
                            if (!empty($attribute['color'])) {
                                break;
                            }
                        }
                    }

                    foreach ($this->attr['size'] as $v) {
                        if ($p->getData($v) !== null) {
                            $attribute['size'] = $p->getAttributeText($v);
                            if (!empty($attribute['size'])) {
                                break;
                            }
                        }
                    }
                    if ($this->checkMSI()) {
                        $qty = 0;
                        foreach ($this->sourceItemsBySkuResolver->execute($p->getSku()) as $item) {
                            $qty = $this->AddQTY($item, $qty);
                        }
                    } else {
                        $qty = (int) ($this->helper->getStockRepo->getStockItem($p->getId())->getQty() ?? 0);
                    }
                    
                    $MasterQty = $MasterQty + (int) $qty;

                    /** @noinspection DuplicatedCode */
                    if ($qty < 0) { $stock = $this->helper->getConfig->getDefaultStock();
                    } elseif ($p->isInStock() && $qty == 0) { $stock = 2;
                    } elseif ($p->isInStock()) { $stock = 1;
                    } else { $stock = 0; }

                    if ($taxID) {
                        $vPrice = $this->helper->getTax->getTaxPrice($p, $vPrice, true);
                        $vSalePrice = $vSalePrice > 0 ? $this->helper->getTax->getTaxPrice($p, $vSalePrice, true) : $vPrice;
                    }

                    if ($vSalePrice > $vPrice) {
                        $vSalePrice = $vPrice;
                    }

                    $v = [
                        'id' => $p->getId(),
                        'sku' => $p->getSku(),
                        'acquisition_price' => 0,
                        'price' => $this->helper->getFunc->digit2($vPrice),
                        'sale_price' => $this->helper->getFunc->digit2($vSalePrice),
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
            $stock = $this->helper->getConfig->getDefaultStock();
        } elseif ($product->isInStock() && $MasterQty == 0) {
            $stock = 2;
        } elseif ($product->isInStock()) {
            $stock = 1;
        } else {
            $stock = 0;
        }

        if ($MasterQty < 0) {
            $defStock = $this->helper->getConfig->getDefaultStock();
            $MasterQty = $defStock == 2 ? 1 : $defStock;
        }

        $brand = null;

        foreach ($this->attr['brand'] as $v) {
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
            'main_image' => $this->getProductImage($product),
            'category' => [ '@cdata' => $listCategory ],
            'brand' => [ '@cdata' => $brand ],
            'acquisition_price' => 0,
            'price' => $this->helper->getFunc->digit2($price),
            'sale_price' => $this->helper->getFunc->digit2($salePrice),
            'sale_price_start_date' => $this->helper->getFunc->correctDate($product->getSpecialFromDate()),
            'sale_price_end_date' => $this->helper->getFunc->correctDate($product->getSpecialToDate()),
            'availability' => $stock,
            'stock' => $MasterQty,
            'media_gallery' => $media_gallery,
            'variations' => $variations,
            'created_at' => $this->helper->getFunc->correctDate($CreatedAt),
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
