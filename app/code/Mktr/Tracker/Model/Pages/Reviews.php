<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

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
        if (self::$rev == null)
        {
            self::$rev = \Magento\Framework\App\ObjectManager::getInstance()->get("\Magento\Review\Model\Review");
        }
        return self::$rev;
    }
    public function rating()
    {
        if (self::$rating == null)
        {
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
                    self::getHelp()->getConfig->getStoreValue("rest_key", $k->getId()) === self::getHelp()->getConfig->getRestKey())
                {
                    self::$ins["Config"][] = $k->getId();
                }
            }
        }
        return self::$ins["Config"];
    }

    public function execute()
    {
        $t = self::getHelp()->getRequest->getParam("start_date") ?? date('Y-m-d');
        $o = self::getHelp()->getApi->send("product_reviews", ['t' => strtotime($t)], false);

        $xml = simplexml_load_string($o->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $rating = array(
            /*
            1 => array(1 => 1,  2 => 2,  3 => 3,  4 => 4,  5 => 5), //quality
            2 => array(1 => 6,  2 => 7,  3 => 8,  4 => 9,  5 => 10),//value
            3 => array(1 => 11, 2 => 12, 3 => 13, 4 => 14, 5 => 15),//price
            */
            4 => array(1 => 16, 2 => 17, 3 => 18, 4 => 19, 5 => 20) //rating
        );

        $added = array();
        $revStore = self::getHelp()->getData->{"reviewStore".self::getHelp()->getConfig->getRestKey()};

        foreach ($xml->review as $value) {
            if (isset($value->review_date)) {
                if (!isset($revStore[(string) $value->review_id])) {
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
                    foreach ($rating as $key => $vv) {
                        $this->rating()
                            ->setRatingId($key)
                            ->setReviewId($review->getId())//$value->review_id
                            // ->setCustomerId($_customerId)
                            ->addOptionVote($vv[round(((int)$value->rating / 2))], $value->product_id);
                    }
                    $review->aggregate();

                    $added[(string) $value->review_id] = $review->getId();
                } else {
                    $added[(string) $value->review_id] = self::getHelp()->getData->reviewStore[(string) $value->review_id];
                }
            }
        }

        self::getHelp()->getData->{"reviewStore".self::getHelp()->getConfig->getRestKey()} = $added;
        self::getHelp()->getData->save();

        return $xml;
    }
}
