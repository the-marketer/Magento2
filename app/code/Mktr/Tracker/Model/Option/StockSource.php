<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Model\Option;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class StockSource implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var SourceRepositoryInterface
     */
    private $sourceRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var bool|null
     */
    private $msiEnabled = null;

    /**
     * @var array|null
     */
    private $list = null;

    public function __construct(
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ModuleManager $moduleManager
    ) {
        $this->sourceRepository = $sourceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->moduleManager = $moduleManager;
    }

    private function checkMSI()
    {
        if ($this->msiEnabled === null) {
            $this->msiEnabled = $this->moduleManager->isEnabled('Magento_Inventory');
        }
        return $this->msiEnabled;
    }

    private function getList($type = 0)
    {
        if ($this->list === null) {
            $this->list = [
                ['all' => __('All')],
                [['value' => 'all', 'label' => __('All')]]
            ];
            if ($this->checkMSI()) {
                foreach ($this->sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $v) {
                    $this->list[0][$v['source_code']] = __($v['name']);
                    $this->list[1][] = ['value' => $v['source_code'], 'label' => __($v['name'])];
                }
            }
        }
        return $this->list[$type];
    }

    /**
     * @return array
     * @noinspection PhpUnused
     */
    public function toOptionArray(): array
    {
        return $this->getList(1);
    }

    /** @noinspection PhpUnused */
    public function toArray(): array
    {
        return $this->getList(0);
    }
}
