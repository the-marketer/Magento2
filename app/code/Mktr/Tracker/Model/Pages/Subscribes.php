<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Pages;

class Subscribes
{
    private static $ins = [
        "Help" => null,
        "Config" => null,
        "Subscriber" => null
    ];

    private static $error = null;

    private static function status()
    {
        return self::$error == null;
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
    public static function getStoreList()
    {
        if (self::$ins["Config"] == null) {
            self::$ins["Config"] = [];
            foreach (\Magento\Framework\App\ObjectManager::getInstance()
                         ->get('\Magento\Store\Api\StoreRepositoryInterface')
                         ->getList() as $k) {
                if (self::getHelp()->getConfig->getStoreValue("status", $k->getId()) &&
                    self::getHelp()->getConfig->getStoreValue("rest_key", $k->getId()) === self::getHelp()->getConfig->getRestKey()) {
                    self::$ins["Config"][] = $k->getId();
                }
            }
        }
        return self::$ins["Config"];
    }

    public static function getSubscriber()
    {
        if (self::$ins["Subscriber"] == null) {
            self::$ins["Subscriber"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Newsletter\Model\Subscriber');
        }
        return self::$ins["Subscriber"];
    }

    private const DEFAULT_INTERVAL_HOURS = 24;

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
        $paramDateFrom = self::getHelp()->getRequest->getParam("date_from");
        $paramDateTo = self::getHelp()->getRequest->getParam("date_to");

        if ($paramDateFrom !== null && $paramDateTo !== null) {
            return [
                strtotime($paramDateFrom . ' 00:00:00'),
                strtotime($paramDateTo . ' 23:59:59')
            ];
        }

        $now = time();
        $intervalHours = (int) self::getHelp()->getConfig->getUpdateSubscribe() ?: self::DEFAULT_INTERVAL_HOURS;

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
        $response = self::getHelp()->getApi->send(
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
        $obj = \Magento\Framework\App\ObjectManager::getInstance();

        foreach ($emails as $email) {
            $obj->get('\Magento\Newsletter\Model\Subscriber')->loadByEmail($email)->unsubscribe();
        }
    }

    /**
     * @param \Magento\Newsletter\Model\Subscriber $subscriber
     * @return bool
     */
    private function isSubscribed($subscriber)
    {
        $status = $subscriber->getStatus();

        return $status !== null
            && (int) $status === \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED;
    }
}
