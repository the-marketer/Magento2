<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject\Helper\DataObjectHelper;
use Magento\Framework\Model\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Newsletter\Helper\Data as NewsletterHelper;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class Newsletter extends \Magento\Newsletter\Model\Subscriber
{
    /**
     * @var Config
     */
    private $mktrConfig;

    public function __construct(
        Context $context,
        Registry $registry,
        NewsletterHelper $newsletterData,
        ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $customerAccountManagement,
        StateInterface $inlineTranslation,
        Config $mktrConfig,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        DateTime $dateTime = null,
        CustomerInterfaceFactory $customerFactory = null,
        DataObjectHelper $dataObjectHelper = null,
        SubscriptionManagerInterface $subscriptionManager = null
    ) {
        $this->mktrConfig = $mktrConfig;
        parent::__construct(
            $context,
            $registry,
            $newsletterData,
            $scopeConfig,
            $transportBuilder,
            $storeManager,
            $customerSession,
            $customerRepository,
            $customerAccountManagement,
            $inlineTranslation,
            $resource,
            $resourceCollection,
            $data,
            $dateTime,
            $customerFactory,
            $dataObjectHelper,
            $subscriptionManager
        );
    }

    public function sendConfirmationSuccessEmail()
    {
        if ($this->mktrConfig->getOptIn() == 0) {
            return parent::sendConfirmationSuccessEmail();
        }
        return $this;
    }

    public function sendUnsubscriptionEmail()
    {
        if ($this->mktrConfig->getOptIn() == 0) {
            return parent::sendUnsubscriptionEmail();
        }
        return $this;
    }
}
