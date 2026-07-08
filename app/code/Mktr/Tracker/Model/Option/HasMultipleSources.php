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
use Magento\Framework\ObjectManagerInterface;

class HasMultipleSources extends Value
{
    private const SOURCE_REPOSITORY = 'Magento\InventoryApi\Api\SourceRepositoryInterface';

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var int|null
     */
    private $msiEnabled = null;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    private function checkMSI()
    {
        if ($this->msiEnabled === null) {
            try {
                $this->msiEnabled = $this->moduleManager->isEnabled('Magento_Inventory')
                    && interface_exists(self::SOURCE_REPOSITORY)
                    ? 1
                    : 0;
            } catch (\Throwable $e) {
                $this->msiEnabled = 0;
            }
        }
        return $this->msiEnabled;
    }

    private function getSourceRepository()
    {
        if (!$this->checkMSI()) {
            return null;
        }

        try {
            return $this->objectManager->get(self::SOURCE_REPOSITORY);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getSourceValue($source, $key)
    {
        if (is_array($source) && isset($source[$key])) {
            return $source[$key];
        }

        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (is_object($source) && method_exists($source, $method)) {
            return $source->{$method}();
        }

        return null;
    }

    private function hasNonDefaultSource(): int
    {
        $sourceRepository = $this->getSourceRepository();
        if ($sourceRepository === null) {
            return 0;
        }

        try {
            foreach ($sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $v) {
                if ($this->getSourceValue($v, 'source_code') !== 'default') {
                    return 1;
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }

    /**
     * @return mixed
     * @noinspection PhpUnused
     */
    public function afterLoad()
    {
        $this->setValue($this->hasNonDefaultSource());

        return parent::afterLoad();
    }

    public function beforeSave()
    {
        $this->setValue($this->hasNonDefaultSource());
        return parent::beforeSave();
    }
}
