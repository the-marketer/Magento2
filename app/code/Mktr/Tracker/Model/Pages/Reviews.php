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

class Reviews
{
    private static $rev = null;
    private static $rating = null;
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $error = null;

    private static function status()
    {
        return self::$error == null;
    }

    public function rev()
    {
        if (self::$rev == null) {
            self::$rev = \Magento\Framework\App\ObjectManager::getInstance()->get("\Magento\Review\Model\Review");
        }
        return self::$rev;
    }
    
    public function rating()
    {
        if (self::$rating == null) {
            self::$rating = \Magento\Framework\App\ObjectManager::getInstance()->get("Magento\Review\Model\Rating");
        }
        return self::$rating;
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

    public function execute()
    {
        $xml = ['execute' => 'none'];
        $t = self::getHelp()->getRequest->getParam("start_date") ?? date('Y-m-d');
        $o = self::getHelp()->getApi->send("product_reviews", ['t' => strtotime($t)], false);
        
        if ($o->getContent() == 'Access not allowed' || $o->getContent() == 'false' || $o->getContent() == false) {
            return $xml;
        }

        $xml = simplexml_load_string($o->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $key = 'key'.self::getHelp()->getConfig->getRestKey();

        $rating = [
            /*
            1 => array(1 => 1,  2 => 2,  3 => 3,  4 => 4,  5 => 5), //quality
            2 => array(1 => 6,  2 => 7,  3 => 8,  4 => 9,  5 => 10),//value
            3 => array(1 => 11, 2 => 12, 3 => 13, 4 => 14, 5 => 15),//price
            */
            4 => [1 => 16, 2 => 17, 3 => 18, 4 => 19, 5 => 20] //rating
        ];
        
        $ratingCollection = $this->rating()->getResourceCollection()->getItems();
        $rating = [];
        
        foreach ($ratingCollection as $k => $v) {
            if ($v->getIsActive() == 1) {
                $s = 1;
                foreach($v->getOptions() as $kk => $vv) {
                    $rating[$vv->getRatingId()][$s] = (int) $vv->getOptionId();
                    $s++;
                }
            }
        }

        foreach ($xml->review as $value) {
            if (isset($value->review_date)) {
                $revID = (string) $value->review_id;
                if (!isset(self::getHelp()->getReviewLogs->i()->{$key}[$revID])) {
                    $review = $this->rev();
                    $review->unsetData('review_id');
                    $review->setCreatedAt($value->review_date); //created date and time
                    $review->setEntityPkValue($value->product_id);//product id
                    $review->setStatusId(1); // status id
                    $review->setTitle(substr($value->review_text, 0, 40)); // review title
                    $review->setDetail($value->review_text); // review detail
                    $review->setEntityId(1); // leave it 1
                    $review->setStoreId(self::getHelp()->getFunc->getStoreId()); // store id

                    $customer = self::getHelp()->getCustomerData
                        ->setWebsiteId(self::getHelp()->getWebsite->getId())
                        ->loadByEmail($value->review_email);

                    if ($customer->getId() != null) {
                        $review->setCustomerId($customer->getId()); //null is for administrator
                    }

                    $review->setNickname($value->review_author); //customer nickname
                    //$review->setReviewId($review->getId());//set current review id$value->review_id
                    $review->setStores(self::getStoreList()); //store id's

                    $review->save();

                    $comment_id = $review->getId();

                    foreach ($rating as $kk => $vv) {
                        $rate = (int) $value->rating;
                        if ($rate > 0) {
                            $rate = round(((int) $value->rating / 2));
                        } else {
                            $rate = 1;
                        }

                        $this->rating()
                            ->setRatingId($kk)
                            ->setReviewId($comment_id)//$value->review_id
                            // ->setCustomerId($_customerId)
                            ->addOptionVote($vv[$rate], $value->product_id);
                    }

                    $review->aggregate();

                    self::getHelp()->getReviewLogs->i()->addTo($key, [ 'id' => $comment_id, 'expire' => strtotime("+10 day")] , $revID);
                }
            }
        }

        $revStore = array();

        foreach (self::getHelp()->getReviewLogs->i()->{$key} as $k => $val) {
            if (time() < $val['expire']) {
                $revStore[$k] = $val;
            }
        }

        self::getHelp()->getReviewLogs->i()->{$key} = $revStore;
        self::getHelp()->getReviewLogs->i()->save();

        return $xml;
    }
}
