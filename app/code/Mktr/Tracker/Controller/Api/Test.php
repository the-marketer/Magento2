<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mktr\Tracker\Helper\Data;

class Test extends Action
{
    // private static $cons = null;
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    /** TODO: Magento 2 */
    public static function getStores()
    {
        if (self::$ins["Config"] == null) {
            self::$ins["Config"] = \Magento\Framework\App\ObjectManager::getInstance()
                ->get('\Magento\Store\Api\StoreRepositoryInterface')->getList();
        }
        return self::$ins["Config"];
    }

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

    /** @noinspection PhpUnused */
    public function execute()
    {
        $upFeed = self::getHelp()->getData->update_feed;
        $upReview = self::getHelp()->getData->update_review;

        foreach (self::getStores() as $k)
        {
            if ($k->getId() != 0) {
                self::getHelp()->getConfig->setScopeCode($k->getId());
                self::getHelp()->getFunc->setStoreId($k->getId());

                if (self::getHelp()->getConfig->getStatus() != 0) {

                    if (self::getHelp()->getConfig->getCronFeed() != 0 && $upFeed < time())
                    {
                        self::getHelp()->getFunc->Write(self::getHelp()->getPagesFeed);

                        self::getHelp()->getData->update_feed =
                            strtotime("+".self::getHelp()->getConfig->getUpdateFeed()." hour");
                    }

                    if (self::getHelp()->getConfig->getCronReview() != 0 && $upReview < time())
                    {
                        self::getHelp()->getPagesReviews->execute();
                        self::getHelp()->getData->update_review =
                            strtotime("+".self::getHelp()->getConfig->getUpdateReview()." hour");
                    }
                }
            }
        }

        self::getHelp()->getData->save();

        self::getHelp()->getRequest->setParam("mime-type", 'json');
        return self::getHelp()->getFunc->Output('status', self::getHelp()->getData->getData() );
    }
}
