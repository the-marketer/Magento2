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
    private $observer = null;
    private $eventName = null;
    private $eventAction = null;
    private $eventData = [];
    /**
     * @var Data
     */
    private $helper;
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
        "sales_order_place_after" => "saveOrder",
        "controller_action_predispatch_catalog_product_view" => "addToCartAndCheckout",
        "controller_action_postdispatch_checkout_cart_index" => "applyDiscountCode"
    ];

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
        $this->helper = $help;
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
        $this->eventAction = $this;
        $this->observer = $observer;

        $this->eventName = $this->getObserverEvents($observer->getEvent()->getName());

        if (!empty($this->eventName)) {
            $this->{$this->eventName}();
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
        $variant = $this->observer->getEvent()->getQuoteItem()->getOptionByCode('simple_product');

        if ($variant == null) {
            $variant = $this->observer->getQuoteItem();
        }

        $this->eventData = [
            'product_id' => $this->observer->getEvent()->getProduct()->getId(),
            'quantity'=> (int) $this->observer->getQuoteItem()->getQty(),
            'variation' => [
                'id' => $variant->getProduct()->getId(),
                'sku' => $variant->getProduct()->getSku()
            ]
        ];

        $this->mktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function removeFromCart()
    {
        $product = $this->observer->getQuoteItem();

        $variant = $this->observer
            ->getEvent()
            ->getQuoteItem()
            ->getOptionByCode('simple_product');

        if ($variant == null) {
            $variant = $this->observer->getQuoteItem();
        }

        $this->eventData = [
            'product_id' => $product->getProductId(),
            'quantity'=> (int) $this->observer->getQuoteItem()->getQty(),
            'variation' => [
                'id' => $variant->getProduct()->getId(),
                'sku' => $variant->getProduct()->getSku()
            ]
        ];

        $this->mktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function addToWishList()
    {
        $product = $this->observer->getItem()->getOptionByCode('simple_product');

        $ID = $this->observer->getEvent()->getProduct()->getId();

        if ($product == null) {
            $valueID = $ID;
        } else {
            $valueID = $product->getValue();
        }

        $this->eventData = [
            'product_id' => $ID,
            'variation' => [
                'id' => $valueID,
                'sku' => $this->helper->getProductRepo->load($valueID)->getSku()
            ]
        ];

        $this->mktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function removeFromWishlist()
    {
        $item = $this->helper->getWishItem->loadWithOptions($this->helper->getRequest->getParam('item'));

        $ID = $item->getProductId();
        $product = $item->getOptionByCode('simple_product');

        if ($product === null) {
            $valueID = $ID;
        } else {
            $valueID = $product->getProductId();
        }

        $this->eventData = [
            'product_id' => $ID,
            'variation' => [
                'id' => $valueID,
                'sku' => $this->helper->getProductRepo->load($valueID)->getSku()
            ]
        ];

        $this->mktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function saveOrder()
    {
        $saveOrder = $this->observer->getOrder();
        
        if ($saveOrder === null) {
            $orderIds = $this->observer->getEvent()->getOrderIds();
            $saveOrder = $this->helper->getOrderRepo->load($orderIds[0]);
        }
        
        if ($saveOrder !== null) {
            if ($this->helper->getMageVersion > "1.4.2.0") {
                $billingAddress = $saveOrder->getbillingAddress();
            } else {
                $billingAddress = $saveOrder->getBillingAddress();
            }
    
            $products = [];
    
            foreach ($saveOrder->getAllVisibleItems() as $item) {
                $products[] = [
                    'product_id' => $item->getProductId(),
                    'price' => $this->helper->getFunc->digit2($item->getFinalPriceInclTax() > 0 ? $item->getPriceInclTax() : $item->getPriceInclTax()),
                    'quantity' => (int) $item->getQtyOrdered(),
                    'variation_sku' => $item->getSku()
                ];
            }
            $couponCode = $saveOrder->getCouponCode();
    
            if ($couponCode == null) {
                $couponCode = '';
            }
    
            $this->eventData = [
                "number" => $saveOrder->getIncrementId(),
                "email_address" => $billingAddress->getEmail(),
                "phone" => $this->helper->getFunc->validateTelephone($billingAddress->getTelephone()),
                "firstname" => $billingAddress->getFirstname(),
                "lastname" => $billingAddress->getLastname(),
                "city" => $billingAddress->getCity(),
                "county" => $billingAddress->getRegion(),
                "address" => implode(" ", $billingAddress->getStreet()),
                "discount_value" => $this->helper->getFunc->digit2($saveOrder->getDiscountAmount()),
                "discount_code" => $couponCode,
                "shipping" => $this->helper->getFunc->digit2($saveOrder->getShippingInclTax()),
                "tax" => $this->helper->getFunc->digit2($saveOrder->getTaxAmount()),// ->getFullTaxInfo()
                "total_value" => $this->helper->getFunc->digit2($saveOrder->getGrandTotal()),
                "products" => $products
            ];

            if (self::sendApi) {
                $this->helper->getApi->send("save_order", $this->eventData);
            }
            
            $this->mktrSessionSet();
        }
    }

    /** @noinspection PhpUnused */
    public function emailAndPhone()
    {
        $object = $this->observer->getObject();

        /** @noinspection PhpUndefinedClassInspection */
        if ($object instanceof Subscriber) {
            if ($object->getEmail() === null) {
                $object = $this->helper->getCustomerSession->getCustomer();
            }
            
            $tApi = $this->helper->getSessionName."Api";
            $this->helper->getSession->{"set".$tApi}([ 'Sub' => true ]);

            if (!$object->getDefaultShipping()) {
                $object1 = $this->helper->getCustomerData
                    ->setWebsiteId($this->helper->getWebsite->getId())
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
        $fName = $this->helper->getSessionName."Api";
        $this->helper->getSession->{"set".$fName}([ 'Sub' => $this->helper->getRequest->getParam('is_subscribed') ]);
        
        $customer = $this->observer->getCustomer();
        $this->EmailSet($customer);
    }
    /** @noinspection PhpUnused */
    public function RegisterOrLogIn()
    {
        $customer = $this->observer->getCustomer();

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
            $customerAddress = $this->helper->getCustomerAddress->load($object->getDefaultShipping());
            $emailData['phone'] = $this->helper->getFunc->validateTelephone($customerAddress->getTelephone());
        }
        
        $this->eventName = "setEmail";

        $this->eventData = $emailData;

        $this->mktrSessionSet();
    }

    /** @noinspection PhpUnused */
    public function SaveButton()
    {
        $module = $this->helper->getFileSystem->setWorkDirectory();

        if ($this->helper->getConfig->getPushStatus() != 0) {
            $module->writeFile("firebase-config.js", $this->helper->getConfig->getFireBase());
            $module->writeFile("firebase-messaging-sw.js", $this->helper->getConfig->getFireBaseMessaging());
        } else {
            $module->deleteFile("firebase-config.js");
            $module->deleteFile("firebase-messaging-sw.js");
        }
    }

    /** @noinspection PhpUnused */
    public function UpdateOrder()
    {
        $o = $this->observer->getEvent()->getOrder();
        $status = $o->getState();

        $send = [
            'order_number' => $o->getIncrementId(),
            'order_status' => $status
        ];

        $this->helper->getApi->send("update_order_status", $send, false);
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
    private function mktrSessionSet()
    {
        $fName = $this->helper->getSessionName.$this->eventName;

        $this->helper->getSession->{"set".$fName}($this->eventData);
        return $this->eventAction;
    }
}
