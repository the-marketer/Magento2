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
use Magento\Framework\ObjectManagerInterface;

class StockSource implements \Magento\Framework\Option\ArrayInterface
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
     * @var bool|null
     */
    private $msiEnabled = null;

    /**
     * @var array|null
     */
    private $list = null;

    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    private function checkMSI()
    {
        if ($this->msiEnabled === null) {
            try {
                $this->msiEnabled = $this->moduleManager->isEnabled('Magento_Inventory')
                    && interface_exists(self::SOURCE_REPOSITORY);
            } catch (\Throwable $e) {
                $this->msiEnabled = false;
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

    private function getList($type = 0)
    {
        if ($this->list === null) {
            $this->list = [
                ['all' => __('All')],
                [['value' => 'all', 'label' => __('All')]]
            ];
            $sourceRepository = $this->getSourceRepository();
            if ($sourceRepository !== null) {
                try {
                    foreach ($sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $v) {
                        $sourceCode = $this->getSourceValue($v, 'source_code');
                        $name = $this->getSourceValue($v, 'name');

                        if ($sourceCode === null || $name === null) {
                            continue;
                        }

                        $this->list[0][$sourceCode] = __($name);
                        $this->list[1][] = ['value' => $sourceCode, 'label' => __($name)];
                    }
                } catch (\Throwable $e) {
                    return $this->list[$type];
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
