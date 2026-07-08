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

class Cron
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    public function __construct(Data $helper, StoreRepositoryInterface $storeRepository)
    {
        $this->helper = $helper;
        $this->storeRepository = $storeRepository;
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
                        $this->helper->getFunc->Write($this->helper->getPagesFeed);

                        $this->helper->getData->update_feed =
                            strtotime("+" . $this->helper->getConfig->getUpdateFeed() . " hour");
                    }

                    if ($this->helper->getConfig->getCronReview() != 0 && $upReview < time()) {
                        $this->helper->getPagesReviews->execute();
                        $this->helper->getData->update_review =
                            strtotime("+" . $this->helper->getConfig->getUpdateReview() . " hour");
                    }
                    if ($this->helper->getConfig->getCronSubscribe() != 0 && $upSubscribe < time()) {
                        $this->helper->getPagesSubscribes->execute();
                        $this->helper->getData->update_subscribe =
                            strtotime("+" . $this->helper->getConfig->getUpdateSubscribe() . " hour");
                    }
                }
            }
        }

        $this->helper->getData->save();
    }
}
