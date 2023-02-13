<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Newsletter\Model\Subscriber;
use Mktr\Tracker\Helper\Data;
use Mktr\Tracker\Model\Array2XML;
use Mktr\Tracker\Model\MktrApi;
use Mktr\Tracker\Model\MktrHelp;
use mysql_xdevapi\Exception;

class Orders extends Action
{
    // private static $cons = null;
    private static $ins = [
        "Help" => null
    ];

    private static $error = null;
    private static $params = null;
    private static $brandAttribute = null;
    private static $data = array();
    private static $imageLink = null;

    private static $fileName = "orders";
    private static $secondName = "order";

    public function __construct(Context $context, Data $help) {
        parent::__construct($context);
        self::$ins['Help'] = $help;
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

    private static function getProductImage($product)
    {
        if (self::$imageLink === null)
        {
            /** TODO: Magento 2 */
            self::$imageLink = self::getHelp()->getStore
                    ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
        }
        return self::$imageLink . $product->getImage();
    }

    public static function getOrderInfo($saveOrder)
    {
        $billingAddress = $saveOrder->getBillingAddress();

        $products = [];

        foreach ($saveOrder->getAllVisibleItems() as $item) {
            $pro = self::getHelp()->getProductRepo->load($item->getProductId());

            $pro->setStoreId(self::getHelp()->getFunc->getStoreId());

            $price = self::getHelp()->getFunc->digit2(
                self::getHelp()->getTax->getTaxPrice($item, $item->getPrice(), true)
            );

            $sale_price = $item->getFinalPrice() > 0 ? self::getHelp()->getFunc->digit2(
                self::getHelp()->getTax->getTaxPrice($item, $item->getFinalPrice(), true)
            ) : $price;

            $ct = self::getHelp()->getManager->buildMultiCategory($pro->getCategoryIds());

            $brand = '';
            foreach (self::$brandAttribute as $v)
            {
                $brand = $pro->getAttributeText($v);
                if (!empty($brand)) {
                    break;
                }
            }

            $products[] = array(
                'product_id' => $item->getProductId(),
                'name' => $item->getName(),
                'url' => $pro->getProductUrl(),
                'main_image' => self::getProductImage($pro),
                'category' => $ct,
                'brand' => $brand,
                'price' => $price,
                'sale_price' => $sale_price,
                'quantity' => (int) $item->getQtyOrdered(),
                'variation_id' => $pro->getId(),
                'variation_sku' => $item->getSku()
            );
        }

        return array(
            "order_no" => $saveOrder->getIncrementId(),
            "order_status" => $saveOrder->getState(),
            "refund_value" => self::getHelp()->getFunc->digit2($saveOrder->getTotalRefunded()) ?? 0,
            "created_at" => self::getHelp()->getFunc->correctDate($saveOrder->getCreatedAt()),
            "email_address" => $billingAddress->getEmail(),
            "phone" => self::getHelp()->getFunc->validateTelephone($billingAddress->getTelephone()),
            "firstname" => $billingAddress->getFirstname(),
            "lastname" => $billingAddress->getLastname(),
            "city" => $billingAddress->getCity(),
            "county" => $billingAddress->getRegion(),
            "address" => implode(" ", $billingAddress->getStreet()),
            "discount_value" => self::getHelp()->getFunc->digit2($saveOrder->getDiscountAmount()),
            "discount_code" => $saveOrder->getCouponCode() ?? "",
            "shipping" => self::getHelp()->getFunc->digit2($saveOrder->getShippingInclTax()),
            "tax" => self::getHelp()->getFunc->digit2($saveOrder->getTaxAmount()),// ->getFullTaxInfo()
            "total_value" => self::getHelp()->getFunc->digit2($saveOrder->getGrandTotal()),
            "products" => $products
        );
    }

    /** @noinspection PhpUnused */
    public function execute()
    {
        if (!self::getHelp()->getRequest->getParam("mime-type")) {
            self::getHelp()->getRequest->setParam("mime-type", 'json');
        }
        self::$error =  self::getHelp()->getFunc->isParamValid([
            'key' => 'Required|Key|allow_export',
            'start_date' => 'Required|DateCheck|StartDate',
            'page' => null,
            'customerId' => null
        ]);

        if ($this->status())
        {
            return self::getHelp()->getFunc->readOrWrite(self::$fileName, self::$secondName, $this);
        }

        return self::getHelp()->getFunc->Output('status', self::$error);
    }

    public static function freshData(): array
    {
        $or = array();
        $stop = false;
        self::$params = self::getHelp()->getRequest->getParams();

        if (isset(self::$params['page']))
        {
            $stop = true;
        }

        self::$brandAttribute = self::getHelp()->getConfig->getBrandAttribute();
        self::$params['page'] = (int) (isset(self::$params['page']) ? self::$params['page'] : 1);
        self::$params['limit'] = (int) (isset(self::$params['limit']) ? self::$params['limit'] : 50);

        self::$data['startDate'] = date(
            self::getHelp()->getConfig->getDateStart(),
            strtotime(self::$params['start_date'])
        );

        self::$data['endDate'] = date(
            self::getHelp()->getConfig->getDateEnd(),
            !isset(self::$params['end_date']) ? time() : strtotime(self::$params['end_date'])
        );

        self::$data['Orders'] = self::getHelp()->getOrderRepo->getCollection()
            ->addFieldToFilter('store_id',array('in', self::getHelp()->getFunc->getStoreId()))
            ->addAttributeToFilter('created_at', array('from' => self::$data['startDate'], 'to' => self::$data['endDate']))
            ->setPageSize(self::$params['limit'])
            ->setOrder('created_at','ASC');
        //->addStoreFilter(self::getHelp()->getFunc->getStoreId());

        if ($stop) {
            $pages = self::$params['page'];
        } else {
            $pages = self::$data['Orders']->getLastPageNumber();
        }

        do {
            self::$data['Orders']->setCurPage(self::$params['page'])->load();

            if (self::$params['page'] == self::$data['Orders']->getCurPage()) {
                foreach (self::$data['Orders'] as $orders) {
                    $or[] = self::getOrderInfo($orders);
                }
            }

            self::$params['page']++;
            self::$data['Orders']->clear();
        } while (self::$params['page'] <= $pages);

        return $or;
    }
}
