<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/
namespace Mktr\Tracker\Observer;

use Magento\Framework\Event\ObserverInterface;
use Mktr\Tracker\Helper\Data;
use Magento\Newsletter\Model\Subscriber;

class Events implements ObserverInterface
{
    private static $observer = null;
    private static $eventName = null;
    private static $eventAction = null;
    private static $eventData = [];

    const observerEvents = array(
        "checkout_cart_product_add_after" => "addToCart",
        "sales_quote_remove_item" => "removeFromCart",
        "wishlist_add_product" => "addToWishlist",
        "controller_action_predispatch_wishlist_index_remove" => "removeFromWishlist",
        "checkout_onepage_controller_success_action" => "saveOrder",
        "multishipping_checkout_controller_success_action" => "saveOrder",
        "model_save_after" => "emailAndPhone",
        "customer_register_success" => "RegisterOrLogIn",
        "customer_login" => "RegisterOrLogIn",
        /* "review_controller_product_init_after" => "Review", */
        "admin_system_config_changed_section_mktr_tracker" => "SaveButton",
        "sales_order_save_after" => "UpdateOrder"
    );

    private static $ins = array(
        "Help" => null,
        "Config" => null
    );

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    public function __construct(Data $help)
    {
        self::$ins["Help"] = $help;
    }

    /** @noinspection PhpUnused */
    public function execute($observer): bool
    {
        self::$eventAction = $this;
        self::$observer = $observer;

        self::$eventName = $this->getObserverEvents($observer->getEvent()->getName());

        if (!empty(self::$eventName)) {
            $this->{self::$eventName}();
        }
        return true;
    }

    public static function getObserverEvents($name = null)
    {
        if ($name == null)
        {
            return self::observerEvents;
        }
        if (isset(self::observerEvents[$name])) {
            return self::observerEvents[$name];
        }
        return null;
    }

    /** @noinspection PhpUnused */
    public function addToCart()
    {
        $variant = self::$observer->getEvent()->getQuoteItem()->getOptionByCode('simple_product');

        if ($variant == null)
        {
            $variant = self::$observer->getQuoteItem();
        }

        self::$eventData = array(
            'product_id' => self::$observer->getEvent()->getProduct()->getId(),
            'quantity'=> (int) self::$observer->getQuoteItem()->getQty(),
            'variation' => array(
                'id' => $variant->getProduct()->getId(),
                'sku' => $variant->getProduct()->getSku()
            )
        );

        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function removeFromCart()
    {
        $product = self::$observer->getQuoteItem();

        $variant = self::$observer
            ->getEvent()
            ->getQuoteItem()
            ->getOptionByCode('simple_product');

        if ($variant)
        {
            $variant = self::$observer->getQuoteItem();
        }

        self::$eventData = array(
            'product_id' => $product->getProductId(),
            'quantity'=> (int) self::$observer->getQuoteItem()->getQty(),
            'variation' => array(
                'id' => $variant->getProduct()->getId(),
                'sku' => $variant->getProduct()->getSku()
            )
        );

        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function addToWishList()
    {
        $product = self::$observer->getItem()->getOptionByCode('simple_product');

        $ID = self::$observer->getEvent()->getProduct()->getId();

        if ($product == null) {
            $valueID = $ID;
        } else {
            $valueID = $product->getValue();
        }

        self::$eventData = array(
            'product_id' => $ID,
            'variation' => array(
                'id' => $valueID,
                /** TODO: Magento 1 = load($valueID)->getSku() | Magento 2 = getById($valueID)->getSku() */
                'sku' => self::getHelp()->getProductRepo->load($valueID)->getSku()
            )
        );

        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function removeFromWishlist()
    {
        $item = self::getHelp()->getWishItem->loadWithOptions(self::getHelp()->getRequest->getParam('item'));

        $ID = $item->getProductId();
        $product = $item->getOptionByCode('simple_product');

        if ($product === null) {
            $valueID = $ID;
        } else {
            $valueID = $product->getProductId();
        }

        self::$eventData = array(
            'product_id' => $ID,
            'variation' => array(
                'id' => $valueID,
                /** TODO: Magento 1 = load($valueID)->getSku() | Magento 2 = getById($valueID)->getSku() */
                'sku' => self::getHelp()->getProductRepo->load($valueID)->getSku()
            )
        );

        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function saveOrder()
    {
        $saveOrder = self::$observer->getOrder();

        if (self::getHelp()->getMageVersion > "1.4.2.0")
        {
            $billingAddress = $saveOrder->getbillingAddress();
        } else {
            $billingAddress = $saveOrder->getBillingAddress();
        }

        $products = [];

        foreach ($saveOrder->getAllVisibleItems() as $item) {
            $products[] = array(
                'product_id' => $item->getProductId(),
                'price' => self::getHelp()->getFunc->digit2( self::getHelp()->getTax->getTaxPrice($item, $item->getPrice(), true) ),
                'quantity' => (int) $item->getQtyOrdered(),
                'variation_sku' => $item->getSku()
            );
        }
        $couponCode = $saveOrder->getCouponCode();

        if ($couponCode == null)
        {
            $couponCode = '';
        }

        self::$eventData = array(
            "number" => $saveOrder->getIncrementId(),
            "email_address" => $billingAddress->getEmail(),
            "phone" => self::getHelp()->getFunc->validateTelephone($billingAddress->getTelephone()),
            "firstname" => $billingAddress->getFirstname(),
            "lastname" => $billingAddress->getLastname(),
            "city" => $billingAddress->getCity(),
            "county" => $billingAddress->getRegion(),
            "address" => implode(" ", $billingAddress->getStreet()),
            "discount_value" => self::getHelp()->getFunc->digit2($saveOrder->getDiscountAmount()),
            "discount_code" => $couponCode,
            "shipping" => self::getHelp()->getFunc->digit2($saveOrder->getShippingInclTax()),
            "tax" => self::getHelp()->getFunc->digit2($saveOrder->getTaxAmount()),// ->getFullTaxInfo()
            "total_value" => self::getHelp()->getFunc->digit2($saveOrder->getGrandTotal()),
            "products" => $products
        );
        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function emailAndPhone()
    {
        $object = self::$observer->getObject();

        /** TODO: Magento 2 - Subscriber - Magento 1 - Mage_Newsletter_Model_Subscriber*/
        /** @noinspection PhpUndefinedClassInspection */
        if ($object instanceof Subscriber) {

            if ($object->getEmail() === null) {
                $object = self::getHelp()->getCustomerSession->getCustomer();
            }

            if (!$object->getDefaultShipping()) {
                $object1 = self::getHelp()->getCustomerData
                    ->setWebsiteId(self::getHelp()->getWebsite->getId())
                    ->loadByEmail($object->getEmail());
                if ($object1->getEmail() !== null) {
                    $object = $object1;
                }
            }

            $this->EmailSet($object);

            if ($object->getDefaultShipping()) {
                self::$eventName = "setPhone";

                $customerAddress = self::getHelp()->getCustomerAddress->load($object->getDefaultShipping());

                self::$eventData = [
                    'phone' => self::getHelp()->getFunc->validateTelephone($customerAddress->getTelephone())
                ];

                self::MktrSessionSet();
            }
        }
    }

    /** @noinspection PhpUnused */
    public function RegisterOrLogIn()
    {
        $customer = self::$observer->getCustomer();

        $this->EmailSet($customer);

        if ($customer->getDefaultShipping()) {
            self::$eventName = "setPhone";
            $address = self::getHelp()->getCustomerAddress->load($customer->getDefaultShipping());

            self::$eventData = array(
                'phone' => self::getHelp()->getFunc->validateTelephone($address->getTelephone())
            );

            self::MktrSessionSet();
        }
    }

    public function EmailSet($object)
    {
        $emailData = [
            'email_address' => $object->getEmail()
        ];

        $fName = $object->getFirstname();
        $lName = $object->getLastname();

        if ($fName) {
            $emailData['firstname'] = $fName;
        }
        if ($lName) {
            $emailData['lastname'] = $lName;
        }
        self::$eventName = "setEmail";

        self::$eventData = $emailData;

        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function SaveButton()
    {
        $module = self::getHelp()->getFileSystem->setWorkDirectory();

        if (self::getHelp()->getConfig->getPushStatus() != 0) {
            $module->writeFile("firebase-config.js", self::getHelp()->getConfig->getFireBase());
            $module->writeFile("firebase-messaging-sw.js", self::getHelp()->getConfig->getFireBaseMessaging());
        } else {
            $module->deleteFile("firebase-config.js");
            $module->deleteFile("firebase-messaging-sw.js");
        }
    }

    /** @noinspection PhpUnused */
    public function UpdateOrder()
    {
        $o = self::$observer->getEvent()->getOrder();
        $status = $o->getState();

        $send = array(
            'order_number' => $o->getIncrementId(),
            'order_status' => $status
        );

        self::getHelp()->getApi->send("update_order_status", $send, false);
    }

    /** @noinspection PhpReturnValueOfMethodIsNeverUsedInspection */
    private static function MktrSessionSet()
    {
        $fName = vsprintf(self::getHelp()->getSessionName, self::$eventName);

        self::getHelp()->getSession->{"set".$fName}(self::$eventData);
        return self::$eventAction;
    }
}
