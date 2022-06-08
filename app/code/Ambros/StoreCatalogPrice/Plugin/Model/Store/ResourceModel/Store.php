<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Store\ResourceModel;

/**
 * Store resource plugin
 */
class Store
{
    
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
     * Customer group dimension provider
     * 
     * @var \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider
     */
    protected $customerGroupDimensionProvider;

    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\Indexer\DimensionFactory $dimensionFactory
     * @param \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $dimensionModeConfiguration
     * @param \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider $customerGroupDimensionProvider
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\Indexer\DimensionFactory $dimensionFactory,
        \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $dimensionModeConfiguration,
        \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider $customerGroupDimensionProvider
    )
    {
        $this->tableMaintainer = $tableMaintainer;
        $this->dimensionFactory = $dimensionFactory;
        $this->dimensionModeConfiguration = $dimensionModeConfiguration;
        $this->customerGroupDimensionProvider = $customerGroupDimensionProvider;
    }
    
    /**
     * Get affected dimensions
     *
     * @param int $storeId
     * @return \Magento\Framework\Indexer\Dimension[][]
     */
    protected function getAffectedDimensions(int $storeId): array
    {
        $storeDimensionName = \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME;
        $dimensions = $this->dimensionModeConfiguration->getDimensionConfiguration();
        if (!in_array($storeDimensionName, $dimensions, true)) {
            return [];
        }
        $storeDimension = $this->dimensionFactory->create($storeDimensionName, (string) $storeId);
        $affectedDimensions = [];
        if (in_array(\Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME, $dimensions, true)) {
            foreach ($this->customerGroupDimensionProvider as $customerGroupDimension) {
                $affectedDimensions[] = [$customerGroupDimension, $storeDimension];
            }
        } else {
            $affectedDimensions[] = [$storeDimension];
        }
        return $affectedDimensions;
    }

    /**
     * After delete
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $result
     * @param \Magento\Framework\Model\AbstractModel $store
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    public function afterDelete(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        $result,
        \Magento\Framework\Model\AbstractModel $store
    )
    {
        foreach ($this->getAffectedDimensions((int) $store->getId()) as $dimensions) {
            $this->tableMaintainer->dropTablesForDimensions($dimensions);
        }
        return $result;
    }

    /**
     * After save
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $result
     * @param \Magento\Framework\Model\AbstractModel $store
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    public function afterSave(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        $result,
        \Magento\Framework\Model\AbstractModel $store
    )
    {
        if ($store->isObjectNew()) {
            foreach ($this->getAffectedDimensions((int) $store->getId()) as $dimensions) {
                $this->tableMaintainer->createTablesForDimensions($dimensions);
            }
        }
        return $result;
    }
   
}