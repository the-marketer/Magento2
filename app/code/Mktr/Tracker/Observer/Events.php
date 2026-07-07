<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Observer;

use Magento\Framework\Event\ObserverInterface;
use Mktr\Tracker\Helper\Data;
use Magento\Newsletter\Model\Subscriber;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;

class Events implements ObserverInterface
{
    private static $observer = null;
    private static $eventName = null;
    private static $eventAction = null;
    private static $eventData = [];
    private $request;
    private $response;
    private $checkoutSession;
    private $messageManager;
    private $url;
    private $productFactory;
    private $cart;
    const sendApi = false;

    const observerEvents = [
        "checkout_cart_product_add_after" => "addToCart",
        "sales_quote_remove_item" => "removeFromCart",
        "wishlist_add_product" => "addToWishlist",
        "controller_action_predispatch_wishlist_index_remove" => "removeFromWishlist",
        "checkout_onepage_controller_success_action" => "saveOrder",
        "multishipping_checkout_controller_success_action" => "saveOrder",
        "model_save_after" => "emailAndPhone",
        "customer_register_success" => "Register",
        "customer_login" => "RegisterOrLogIn",
        /* "review_controller_product_init_after" => "Review", */
        "admin_system_config_changed_section_mktr_tracker" => "SaveButton",
        "sales_order_save_after" => "UpdateOrder",
        /* TODO CARD PAY 'sales_order_save_commit_after' */
        "sales_order_place_after" => "saveOrder",
        "controller_action_predispatch_catalog_product_view" => "addToCartAndCheckout",
        "controller_action_postdispatch_checkout_cart_index" => "applyDiscountCode"
    ];

    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    public function __construct(
        Data $help,
        RequestInterface $request,
        ResponseInterface $response,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        ProductFactory $productFactory,
        Cart $cart
    ) {
        self::$ins["Help"] = $help;
        $this->request = $request;
        $this->response = $response;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->url = $url;
        $this->productFactory = $productFactory;
        $this->cart = $cart;
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
        if ($name == null) {
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

        if ($variant == null) {
            $variant = self::$observer->getQuoteItem();
        }

        self::$eventData = [
            'product_id' => self::$observer->getEvent()->getProduct()->getId(),
            'quantity'=> (int) self::$observer->getQuoteItem()->getQty(),
            'variation' => [
                'id' => $variant->getProduct()->getId(),
                'sku' => $variant->getProduct()->getSku()
            ]
        ];

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

        if ($variant == null) {
            $variant = self::$observer->getQuoteItem();
        }

        self::$eventData = [
            'product_id' => $product->getProductId(),
            'quantity'=> (int) self::$observer->getQuoteItem()->getQty(),
            'variation' => [
                'id' => $variant->getProduct()->getId(),
                'sku' => $variant->getProduct()->getSku()
            ]
        ];

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

        self::$eventData = [
            'product_id' => $ID,
            'variation' => [
                'id' => $valueID,
                /** TODO: Magento 1 = load($valueID)->getSku() | Magento 2 = getById($valueID)->getSku() */
                'sku' => self::getHelp()->getProductRepo->load($valueID)->getSku()
            ]
        ];

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

        self::$eventData = [
            'product_id' => $ID,
            'variation' => [
                'id' => $valueID,
                /** TODO: Magento 1 = load($valueID)->getSku() | Magento 2 = getById($valueID)->getSku() */
                'sku' => self::getHelp()->getProductRepo->load($valueID)->getSku()
            ]
        ];

        self::MktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function saveOrder()
    {
        $saveOrder = self::$observer->getOrder();
        
        if ($saveOrder === null) {
            $orderIds = self::$observer->getEvent()->getOrderIds();
            $saveOrder = self::getHelp()->getOrderRepo->load($orderIds[0]);
        }
        
        if ($saveOrder !== null) {
            if (self::getHelp()->getMageVersion > "1.4.2.0") {
                $billingAddress = $saveOrder->getbillingAddress();
            } else {
                $billingAddress = $saveOrder->getBillingAddress();
            }
    
            $products = [];
    
            foreach ($saveOrder->getAllVisibleItems() as $item) {
                $products[] = [
                    'product_id' => $item->getProductId(),
                    'price' => self::getHelp()->getFunc->digit2($item->getFinalPriceInclTax() > 0 ? $item->getPriceInclTax() : $item->getPriceInclTax()),
                    'quantity' => (int) $item->getQtyOrdered(),
                    'variation_sku' => $item->getSku()
                ];
            }
            $couponCode = $saveOrder->getCouponCode();
    
            if ($couponCode == null) {
                $couponCode = '';
            }
    
            self::$eventData = [
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
            ];

            if (self::sendApi) {
                self::getHelp()->getApi->send("save_order", self::$eventData);
            }
            
            self::MktrSessionSet();
        }
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
            
            $tApi = self::getHelp()->getSessionName."Api";
            self::getHelp()->getSession->{"set".$tApi}([ 'Sub' => true ]);

            if (!$object->getDefaultShipping()) {
                $object1 = self::getHelp()->getCustomerData
                    ->setWebsiteId(self::getHelp()->getWebsite->getId())
                    ->loadByEmail($object->getEmail());
                if ($object1->getEmail() !== null) {
                    $object = $object1;
                }
            }
            
            $this->EmailSet($object);
        }
    }
    /** @noinspection PhpUnused */
    public function Register()
    {
        $fName = self::getHelp()->getSessionName."Api";
        self::getHelp()->getSession->{"set".$fName}([ 'Sub' => self::getHelp()->getRequest->getParam('is_subscribed') ]);
        
        $customer = self::$observer->getCustomer();
        $this->EmailSet($customer);
    }
    /** @noinspection PhpUnused */
    public function RegisterOrLogIn()
    {
        $customer = self::$observer->getCustomer();

        $this->EmailSet($customer);
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

        if ($object->getDefaultShipping()) {
            $customerAddress = self::getHelp()->getCustomerAddress->load($object->getDefaultShipping());
            $emailData['phone'] = self::getHelp()->getFunc->validateTelephone($customerAddress->getTelephone());
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

        $send = [
            'order_number' => $o->getIncrementId(),
            'order_status' => $status
        ];

        self::getHelp()->getApi->send("update_order_status", $send, false);
    }

    /** @noinspection PhpUnused */
    public function addToCartAndCheckout()
    {
        $addToCart = $this->request->getParam('mktrAddCart', 0);
        $productId = $this->request->getParam('mktrPID', null);

        if ($addToCart != 1 || empty($productId)) {
            return;
        }

        $redirectUrl = $this->url->getUrl('checkout/cart');
        $product = $this->productFactory->create()->load($productId);

        if ($product && $product->getId()) {
            try {
                $this->cart->addProduct($product, ['qty' => 1]);
                $this->cart->save();
                $this->checkoutSession->setCartWasUpdated(true);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        } else {
            $this->messageManager->addErrorMessage(__('Invalid product.'));
        }

        $this->response->setRedirect($redirectUrl);
    }

    /** @noinspection PhpUnused */
    public function applyDiscountCode()
    {
        $redirectUrl = $this->url->getUrl('checkout/cart');

        try {
            $addDiscount = (int) $this->request->getParam('mktrAddDiscount', 0);
            $code = trim((string) $this->request->getParam('code', ''));

            if ($addDiscount !== 1 || $code === '') {
                return;
            }

            $quote = $this->checkoutSession->getQuote();

            if (!$quote->hasItems()) {
                $this->messageManager->addErrorMessage(__('Your cart is empty. Please add products to your cart before applying a discount code.'));
            } elseif ($quote->getCouponCode()) {
                $this->messageManager->addErrorMessage(__('A coupon is already applied. Please remove it before applying a new one.'));
            } else {
                $quote->setCouponCode($code)->collectTotals()->save();

                if ($quote->getCouponCode() === $code) {
                    $this->messageManager->addSuccessMessage(__('The discount code has been applied successfully.'));
                } else {
                    $this->messageManager->addErrorMessage(__('Invalid discount code.'));
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        $this->response->setRedirect($redirectUrl);
    }

    /** @noinspection PhpReturnValueOfMethodIsNeverUsedInspection */
    private static function MktrSessionSet()
    {
        /* TODO : UPDATE */
        $fName = self::getHelp()->getSessionName.self::$eventName;

        self::getHelp()->getSession->{"set".$fName}(self::$eventData);
        return self::$eventAction;
    }
}
