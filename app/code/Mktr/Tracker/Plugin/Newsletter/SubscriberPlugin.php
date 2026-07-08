<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Plugin\Newsletter;

use Magento\Newsletter\Model\Subscriber;
use Mktr\Tracker\Model\Config;

class SubscriberPlugin
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param Subscriber $subject
     * @param callable $proceed
     * @return Subscriber
     */
    public function aroundSendConfirmationSuccessEmail(Subscriber $subject, callable $proceed)
    {
        if ($this->config->getOptIn() != 0) {
            return $subject;
        }

        return $proceed();
    }

    /**
     * @param Subscriber $subject
     * @param callable $proceed
     * @return Subscriber
     */
    public function aroundSendUnsubscriptionEmail(Subscriber $subject, callable $proceed)
    {
        if ($this->config->getOptIn() != 0) {
            return $subject;
        }

        return $proceed();
    }
}
