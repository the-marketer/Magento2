<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mktr\Tracker\Helper\Data;

class Orders extends Action
{
    /**
     * @var Data
     */
    private $helper;



    private $fileName = "orders";
    private $secondName = "order";

    /**
     * @var array|null
     */
    private $brandAttribute;

    /**
     * @var string|null
     */
    private $imageLink;

    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    private function getProductImage($product)
    {
        if ($this->imageLink === null) {
            $this->imageLink = $this->helper->getStore
                    ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
        }
        return $this->imageLink . $product->getImage();
    }

    public function getOrderInfo($saveOrder)
    {
        $billingAddress = $saveOrder->getBillingAddress();

        $products = [];

        foreach ($saveOrder->getAllVisibleItems() as $item) {
            // getProductById();
            // $pro = $this->helper->getProductRepo->load($item->getProductId());
            try{
                $pro = $this->helper->getProduct->getById($item->getProductId(), false, $this->helper->getFunc->getStoreId(), true);
            } catch (\Exception $e){
                continue;
            }

            $pro->setStoreId($this->helper->getFunc->getStoreId());
/*
            $price = $this->helper->getFunc->digit2(
                $this->helper->getTax->getTaxPrice($item, $item->getPrice(), true)
            );
            $sale_price = $item->getFinalPrice() > 0 ? $this->helper->getFunc->digit2(
                $this->helper->getTax->getTaxPrice($item, $item->getFinalPrice(), true)
            ) : $price;
*/          
            $price = $this->helper->getFunc->digit2(
                $item->getPriceInclTax()
            );
            
            $sale_price = $item->getFinalPriceInclTax() > 0 ? $this->helper->getFunc->digit2(
                $item->getFinalPriceInclTax()
            ) : $price;

            $ct = $this->helper->getManager->buildMultiCategory($pro->getCategoryIds());

            $brand = '';
            foreach ($this->brandAttribute as $v) {
                $brand = $pro->getAttributeText($v);
                if (!empty($brand)) {
                    break;
                }
            }

            if (empty($brand)) {
                $brand = "N/A";
            }

            $products[] = [
                'product_id' => $item->getProductId(),
                'name' => $item->getName(),
                'url' => $pro->getProductUrl(),
                'main_image' => $this->getProductImage($pro),
                'category' => $ct,
                'brand' => $brand,
                'price' => $price,
                'sale_price' => $sale_price,
                'quantity' => (int) $item->getQtyOrdered(),
                'variation_id' => $pro->getId(),
                'variation_sku' => $item->getSku()
            ];
        }

        return empty($products) ? null : [
            "order_no" => $saveOrder->getIncrementId(),
            "order_status" => $saveOrder->getState(),
            "refund_value" => $this->helper->getFunc->digit2($saveOrder->getTotalRefunded()) ?? 0,
            "created_at" => $this->helper->getFunc->correctDate($saveOrder->getCreatedAt()),
            "email_address" => $billingAddress->getEmail(),
            "phone" => $this->helper->getFunc->validateTelephone($billingAddress->getTelephone()),
            "firstname" => $billingAddress->getFirstname(),
            "lastname" => $billingAddress->getLastname(),
            "city" => $billingAddress->getCity(),
            "county" => $billingAddress->getRegion(),
            "address" => implode(" ", $billingAddress->getStreet()),
            "discount_value" => $this->helper->getFunc->digit2($saveOrder->getDiscountAmount()),
            "discount_code" => $saveOrder->getCouponCode() ?? "",
            "shipping" => $this->helper->getFunc->digit2($saveOrder->getShippingInclTax()),
            "tax" => $this->helper->getFunc->digit2($saveOrder->getTaxAmount()),// ->getFullTaxInfo()
            "total_value" => $this->helper->getFunc->digit2($saveOrder->getGrandTotal()),
            "products" => $products
        ];
    }

    /** @noinspection PhpUnused */
    public function execute()
    {
        if (!$this->helper->getRequest->getParam("mime-type")) {
            $this->helper->getRequest->setParam("mime-type", 'json');
        }
        $error =  $this->helper->getFunc->isParamValid([
            'key' => 'KeyAuth|allow_export',
            'start_date' => 'Required|DateCheck|StartDate',
            'page' => null,
            'customerId' => null
        ]);

        if ($error === null) {
            return $this->helper->getFunc->readOrWrite($this->fileName, $this->secondName, $this);
        }

        return $this->helper->getFunc->Output('status', $error);
    }

    public function freshData(): array
    {
        $or = [];
        $stop = false;
        $params = $this->helper->getRequest->getParams();

        if (isset($params['page'])) {
            $stop = true;
        }

        $brandAttribute = $this->helper->getConfig->getBrandAttribute();
        $this->brandAttribute = $brandAttribute;
        $params['page'] = (int) (isset($params['page']) ? $params['page'] : 1);
        $params['limit'] = (int) (isset($params['limit']) ? $params['limit'] : 50);

        $data['startDate'] = date(
            $this->helper->getConfig->getDateStart(),
            strtotime($params['start_date'])
        );

        $data['endDate'] = date(
            $this->helper->getConfig->getDateEnd(),
            !isset($params['end_date']) ? time() : strtotime($params['end_date'])
        );

        $data['Orders'] = $this->helper->getOrderRepo->getCollection()
            ->addFieldToFilter('store_id', ['in', $this->helper->getFunc->getStoreId()])
            ->addAttributeToFilter('created_at', ['from' => $data['startDate'], 'to' => $data['endDate']])
            ->setPageSize($params['limit'])
            ->setOrder('created_at', 'ASC');
        //->addStoreFilter($this->helper->getFunc->getStoreId());

        if ($stop) {
            $pages = $params['page'];
        } else {
            $pages = $data['Orders']->getLastPageNumber();
        }

        do {
            $data['Orders']->setCurPage($params['page'])->load();

            if ($params['page'] == $data['Orders']->getCurPage()) {
                foreach ($data['Orders'] as $orders) {
                    $o = $this->getOrderInfo($orders);
                    if ($o !== null) {
                        $or[] = $o;
                    }
                }
            }

            $params['page']++;
            $data['Orders']->clear();
        } while ($params['page'] <= $pages);

        return $or;
    }
}
