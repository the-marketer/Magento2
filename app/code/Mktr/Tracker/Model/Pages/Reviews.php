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

use Magento\Review\Model\Rating;
use Magento\Review\Model\Review;
use Magento\Store\Api\StoreRepositoryInterface;
use Mktr\Tracker\Helper\Data;
use Mktr\Tracker\Model\Config;
use Mktr\Tracker\Model\ReviewLogs;

class Reviews
{
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
     * @var Review
     */
    private $review;

    /**
     * @var Rating
     */
    private $rating;

    /**
     * @var ReviewLogs
     */
    private $reviewLogs;

    /**
     * @var array|null
     */
    private $storeList = null;

    public function __construct(
        Data $helper,
        Config $config,
        StoreRepositoryInterface $storeRepository,
        Review $review,
        Rating $rating,
        ReviewLogs $reviewLogs
    ) {
        $this->helper = $helper;
        $this->config = $config;
        $this->storeRepository = $storeRepository;
        $this->review = $review;
        $this->rating = $rating;
        $this->reviewLogs = $reviewLogs;
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

    public function execute()
    {
        $xml = ['execute' => 'none'];
        $t = $this->helper->getRequest->getParam("start_date") ?? date('Y-m-d');
        $o = $this->helper->getApi->send("product_reviews", ['t' => strtotime($t)], false);

        if ($o->getContent() == 'Access not allowed' || $o->getContent() == 'false' || $o->getContent() == false) {
            return $xml;
        }

        $xml = simplexml_load_string($o->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $key = 'key' . $this->config->getRestKey();

        $ratingCollection = $this->rating->getResourceCollection()->getItems();
        $rating = [];

        foreach ($ratingCollection as $v) {
            if ($v->getIsActive() == 1) {
                $s = 1;
                foreach ($v->getOptions() as $vv) {
                    $rating[$vv->getRatingId()][$s] = (int) $vv->getOptionId();
                    $s++;
                }
            }
        }

        foreach ($xml->review as $value) {
            if (isset($value->review_date)) {
                $revID = (string) $value->review_id;
                if (!isset($this->reviewLogs->{$key}[$revID])) {
                    $review = clone $this->review;
                    $review->unsetData('review_id');
                    $review->setCreatedAt($value->review_date);
                    $review->setEntityPkValue($value->product_id);
                    $review->setStatusId(1);
                    $review->setTitle(substr($value->review_text, 0, 40));
                    $review->setDetail($value->review_text);
                    $review->setEntityId(1);
                    $review->setStoreId($this->helper->getFunc->getStoreId());

                    $customer = $this->helper->getCustomerData
                        ->setWebsiteId($this->helper->getWebsite->getId())
                        ->loadByEmail($value->review_email);

                    if ($customer->getId() != null) {
                        $review->setCustomerId($customer->getId());
                    }

                    $review->setNickname($value->review_author);
                    $review->setStores($this->getStoreList());
                    $review->save();

                    $commentId = $review->getId();

                    foreach ($rating as $kk => $vv) {
                        $rate = (int) $value->rating;
                        if ($rate > 0) {
                            $rate = round(((int) $value->rating / 2));
                        } else {
                            $rate = 1;
                        }

                        $ratingModel = clone $this->rating;
                        $ratingModel
                            ->setRatingId($kk)
                            ->setReviewId($commentId)
                            ->addOptionVote($vv[$rate], $value->product_id);
                    }

                    $review->aggregate();

                    $this->reviewLogs->addTo($key, ['id' => $commentId, 'expire' => strtotime("+10 day")], $revID);
                }
            }
        }

        $revStore = [];
        if (isset($this->reviewLogs->{$key}) && is_array($this->reviewLogs->{$key})) {
            foreach ($this->reviewLogs->{$key} as $k => $val) {
                if (time() < $val['expire']) {
                    $revStore[$k] = $val;
                }
            }
        }

        $this->reviewLogs->{$key} = $revStore;
        $this->reviewLogs->save();

        return $xml;
    }
}
