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
use Magento\Framework\App\Config\Value;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class HasMultipleSources extends Value
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
     * @var int|null
     */
    private $msiEnabled = null;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ModuleManager $moduleManager,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->sourceRepository = $sourceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->moduleManager = $moduleManager;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    private function checkMSI()
    {
        if ($this->msiEnabled === null) {
            $this->msiEnabled = $this->moduleManager->isEnabled('Magento_Inventory') ? 1 : 0;
        }
        return $this->msiEnabled;
    }

    /**
     * @return mixed
     * @noinspection PhpUnused
     */
    public function afterLoad()
    {
        $setMSI = 0;

        if ($this->checkMSI()) {
            foreach ($this->sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $v) {
                if ($v['source_code'] !== 'default') {
                    $setMSI = 1;
                    break;
                }
            }
        }

        $this->setValue($setMSI);

        return parent::afterLoad();
    }

    public function beforeSave()
    {
        $setMSI = 0;

        if ($this->checkMSI()) {
            foreach ($this->sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $v) {
                if ($v['source_code'] !== 'default') {
                    $setMSI = 1;
                    break;
                }
            }
        }

        $this->setValue($setMSI);
        return parent::beforeSave();
    }
}
