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

use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Api\StoreRepositoryInterface;
use Mktr\Tracker\Helper\Data;
use Mktr\Tracker\Model\Config;

class Subscribes
{
    private const DEFAULT_INTERVAL_HOURS = 24;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var array|null
     */
    private $storeList = null;

    public function __construct(
        Data $helper,
        Config $config,
        StoreRepositoryInterface $storeRepository,
        SubscriberFactory $subscriberFactory
    ) {
        $this->helper = $helper;
        $this->config = $config;
        $this->storeRepository = $storeRepository;
        $this->subscriberFactory = $subscriberFactory;
    }

    public function getStoreList()
    {
        if ($this->storeList === null) {
            $this->storeList = [];
            foreach ($this->storeRepository->getList() as $store) {
                if ($this->config->getStoreValue("status", $store->getId()) &&
                    $this->config->getStoreValue("rest_key", $store->getId()) === $this->config->getRestKey()) {
                    $this->storeList[] = $store->getId();
                }
            }
        }
        return $this->storeList;
    }

    /**
     * @return array
     */
    public function execute()
    {
        list($dateFrom, $dateTo) = $this->resolveDateRange();

        $emails = $this->fetchUnsubscribedEmails($dateFrom, $dateTo);

        if ($emails === null) {
            return ['status' => 'N/A'];
        }

        $this->processUnsubscribes($emails);

        return ['status' => $emails];
    }

    /**
     * @return array
     */
    private function resolveDateRange()
    {
        $paramDateFrom = $this->helper->getRequest->getParam("date_from");
        $paramDateTo = $this->helper->getRequest->getParam("date_to");

        if ($paramDateFrom !== null && $paramDateTo !== null) {
            return [
                strtotime($paramDateFrom . ' 00:00:00'),
                strtotime($paramDateTo . ' 23:59:59')
            ];
        }

        $now = time();
        $intervalHours = (int) $this->config->getUpdateSubscribe() ?: self::DEFAULT_INTERVAL_HOURS;

        return [
            $now - ($intervalHours * 3600),
            $now
        ];
    }

    /**
     * @param int $dateFrom
     * @param int $dateTo
     * @return array|null
     */
    private function fetchUnsubscribedEmails($dateFrom, $dateTo)
    {
        $response = $this->helper->getApi->send(
            "unsubscribed_emails",
            ['date_from' => $dateFrom, 'date_to' => $dateTo],
            false
        );

        return json_decode($response->getContent());
    }

    /**
     * @param array $emails
     * @return void
     */
    private function processUnsubscribes($emails)
    {
        foreach ($emails as $email) {
            $this->subscriberFactory->create()->loadByEmail($email)->unsubscribe();
        }
    }
}
