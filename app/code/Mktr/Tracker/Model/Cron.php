<?php
/** @noinspection SpellCheckingInspection */
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

use Magento\Store\Api\StoreRepositoryInterface;
use Mktr\Tracker\Helper\Data;
use Psr\Log\LoggerInterface;
use Throwable;

class Cron
{
    private const ORDER_SYNC_DAYS = 7;
    private const ORDER_BATCH_SIZE = 100;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Data $helper,
        StoreRepositoryInterface $storeRepository,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->storeRepository = $storeRepository;
        $this->logger = $logger;
    }

    public function execute()
    {
        $upFeed = $this->helper->getData->update_feed;
        $upReview = $this->helper->getData->update_review;
        $upSubscribe = $this->helper->getData->update_subscribe;

        foreach ($this->storeRepository->getList() as $k) {
            if ($k->getId() != 0) {
                $this->helper->getStoreManager->setCurrentStore($k->getId());
                $this->helper->getConfig->setScopeCode($k->getId());
                $this->helper->getFunc->setStoreId($k->getId());

                if ($this->helper->getConfig->getStatus() != 0) {
                    if ($this->helper->getConfig->getCronFeed() != 0 && $upFeed < time()) {
                        $this->runStoreJob(
                            'feed',
                            $k->getId(),
                            function () {
                                $this->helper->getFunc->Write($this->helper->getPagesFeed);
                                $this->helper->getData->update_feed =
                                    strtotime("+" . $this->helper->getConfig->getUpdateFeed() . " hour");
                            }
                        );

                        if ($this->helper->getConfig->getAllowExport() != 0) {
                            $this->runStoreJob(
                                'orders',
                                $k->getId(),
                                function () use ($k) {
                                    $this->syncRecentOrders($k->getId());
                                }
                            );
                        }
                    }

                    if ($this->helper->getConfig->getCronReview() != 0 && $upReview < time()) {
                        $this->runStoreJob(
                            'review',
                            $k->getId(),
                            function () {
                                $this->helper->getPagesReviews->execute();
                                $this->helper->getData->update_review =
                                    strtotime("+" . $this->helper->getConfig->getUpdateReview() . " hour");
                            }
                        );
                    }
                    if ($this->helper->getConfig->getCronSubscribe() != 0 && $upSubscribe < time()) {
                        $this->runStoreJob(
                            'subscribe',
                            $k->getId(),
                            function () {
                                $this->helper->getPagesSubscribes->execute();
                                $this->helper->getData->update_subscribe =
                                    strtotime("+" . $this->helper->getConfig->getUpdateSubscribe() . " hour");
                            }
                        );
                    }
                }
            }
        }

        $this->helper->getData->save();
    }

    private function syncRecentOrders($storeId): void
    {
        $collection = $this->helper->getOrderRepo->getCollection()
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('created_at', [
                'from' => date('Y-m-d H:i:s', strtotime('-' . self::ORDER_SYNC_DAYS . ' days'))
            ])
            ->setPageSize(self::ORDER_BATCH_SIZE)
            ->setOrder('entity_id', 'ASC');

        $lastPage = $collection->getLastPageNumber();

        for ($page = 1; $page <= $lastPage; $page++) {
            $collection->setCurPage($page)->load();

            foreach ($collection as $order) {
                try {
                    $payload = $this->buildOrderPayload($order);
                    if ($payload === null) {
                        continue;
                    }

                    $this->helper->getApi->send('save_order', $payload);
                    $this->helper->getApi->send('update_order_status', [
                        'order_number' => $order->getIncrementId(),
                        'order_status' => $order->getState()
                    ], false);
                } catch (Throwable $e) {
                    continue;
                }
            }

            $collection->clear();
        }
    }

    private function buildOrderPayload($order): ?array
    {
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null) {
            return null;
        }

        $products = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $products[] = [
                'product_id' => $item->getProductId(),
                'price' => $this->helper->getFunc->digit2($item->getPriceInclTax()),
                'quantity' => (int) $item->getQtyOrdered(),
                'variation_sku' => $item->getSku()
            ];
        }

        if (empty($products)) {
            return null;
        }

        return [
            'number' => $order->getIncrementId(),
            'email_address' => $billingAddress->getEmail(),
            'phone' => $this->helper->getFunc->validateTelephone($billingAddress->getTelephone()),
            'firstname' => $billingAddress->getFirstname(),
            'lastname' => $billingAddress->getLastname(),
            'city' => $billingAddress->getCity(),
            'county' => $billingAddress->getRegion(),
            'address' => implode(' ', $billingAddress->getStreet()),
            'discount_value' => $this->helper->getFunc->digit2($order->getDiscountAmount()),
            'discount_code' => $order->getCouponCode() ?? '',
            'shipping' => $this->helper->getFunc->digit2($order->getShippingInclTax()),
            'tax' => $this->helper->getFunc->digit2($order->getTaxAmount()),
            'total_value' => $this->helper->getFunc->digit2($order->getGrandTotal()),
            'products' => $products
        ];
    }

    private function runStoreJob(string $job, $storeId, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->logger->error('TheMarketer cron job failed', [
                'job' => $job,
                'store_id' => $storeId,
                'message' => $e->getMessage()
            ]);
        }
    }
}
