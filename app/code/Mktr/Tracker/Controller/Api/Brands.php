<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mktr\Tracker\Helper\Data;

class Brands extends Action
{
    /**
     * @var Data
     */
    private $helper;

    private $fileName = "brands";
    private $secondName = "brand";

    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    public function execute()
    {
        $error = $this->helper->getFunc->isParamValid([
            'key' => 'KeyAuth'
        ]);

        if ($error === null) {
            return $this->helper->getFunc->readOrWrite($this->fileName, $this->secondName, $this);
        }

        return $this->helper->getFunc->Output('status', $error);
    }

    public function freshData(): array
    {
        $brandAttribute = $this->helper->getConfig->getBrandAttribute();
        $url = $this->helper->getBaseUrl . 'catalogsearch/result/?q=';
        $data = [];
        foreach ($brandAttribute as $item) {
            foreach ($this->helper->getBrands->get($item)->getOptions() as $option) {
                if ($option->getValue()) {
                    $data[] = [
                        'name' => $option->getLabel(),
                        'id' => $option->getValue(),
                        'url' => $url . $option->getLabel()
                    ];
                }
            }
        }

        return $data;
    }
}
