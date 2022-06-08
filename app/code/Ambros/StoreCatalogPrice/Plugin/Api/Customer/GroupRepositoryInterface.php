<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Api\Customer;

/**
 * Customer group repository plugin
 */
class GroupRepositoryInterface
{
    
    /**
     * Update index
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\UpdateIndexInterface
     */
    protected $updateIndex;
    
    /**
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer
     */
    protected $tableMaintainer;
    
    /**
     * Dimension factory
     * 
     * @var \Magento\Framework\Indexer\DimensionFactory
     */
    protected $dimensionFactory;

    /**
     * Dimension mode configuration
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration
     */
    protected $dimensionModeConfiguration;

    /**
     * Store dimension provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider
     */
    protected $storeDimensionProvider;

    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\UpdateIndexInterface $updateIndex
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\Indexer\DimensionFactory $dimensionFactory
     * @param \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $dimensionModeConfiguration
     * @param \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider $storeDimensionProvider
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Model\Indexer\Product\Price\UpdateIndexInterface $updateIndex,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\Indexer\DimensionFactory $dimensionFactory,
        \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $dimensionModeConfiguration,
        \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider $storeDimensionProvider
    )
    {
        $this->updateIndex = $updateIndex;
        $this->tableMaintainer = $tableMaintainer;
        $this->dimensionFactory = $dimensionFactory;
        $this->dimensionModeConfiguration = $dimensionModeConfiguration;
        $this->storeDimensionProvider = $storeDimensionProvider;
    }
    
    /**
     * Get affected dimensions
     *
     * @param int $customerGroupId
     * @return \Magento\Framework\Indexer\Dimension[][]
     */
    protected function getAffectedDimensions(int $customerGroupId): array
    {
        $customerGroupDimensionName = \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME;
        $dimensions = $this->dimensionModeConfiguration->getDimensionConfiguration();
        if (!in_array($customerGroupDimensionName, $dimensions, true)) {
            return [];
        }
        $customerGroupDimension = $this->dimensionFactory->create($customerGroupDimensionName, (string) $customerGroupId);
        $affectedDimensions = [];
        if (in_array(\Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME, $dimensions, true)) {
            foreach ($this->storeDimensionProvider as $storeDimension) {
                $affectedDimensions[] = [$customerGroupDimension, $storeDimension];
            }
        } else {
            $affectedDimensions[] = [$customerGroupDimension];
        }
        return $affectedDimensions;
    }

    /**
     * Around save
     *
     * @param \Magento\Customer\Api\GroupRepositoryInterface $subject
     * @param \Closure $proceed
     * @param \Magento\Customer\Api\Data\GroupInterface $customerGroup
     * @return \Magento\Customer\Api\Data\GroupInterface
     */
    public function aroundSave(
        \Magento\Customer\Api\GroupRepositoryInterface $subject,
        \Closure $proceed,
        \Magento\Customer\Api\Data\GroupInterface $customerGroup
    )
    {
        $isNewCustomerGroup = !$customerGroup->getId();
        $result = $proceed($customerGroup);
        if ($isNewCustomerGroup) {
            foreach ($this->getAffectedDimensions((int) $result->getId()) as $dimensions) {
                $this->tableMaintainer->createTablesForDimensions($dimensions);
            }
        }
        $this->updateIndex->update($result, $isNewCustomerGroup);
        return $result;
    }

    /**
     * After delete by ID
     *
     * @param \Magento\Customer\Api\GroupRepositoryInterface $subject
     * @param bool $result
     * @param string $customerGroupId
     * @return bool
     */
    public function afterDeleteById(
        \Magento\Customer\Api\GroupRepositoryInterface $subject,
        bool $result,
        string $customerGroupId
    )
    {
        foreach ($this->getAffectedDimensions((int) $customerGroupId) as $dimensions) {
            $this->tableMaintainer->dropTablesForDimensions($dimensions);
        }
        return $result;
    }

}