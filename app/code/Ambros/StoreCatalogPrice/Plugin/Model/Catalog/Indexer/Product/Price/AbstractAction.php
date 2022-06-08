<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price;

/**
 * Product price abstract indexer action plugin
 */
class AbstractAction extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Store base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency
     */
    protected $storeBaseCurrency;
    
    /**
     * Store IDs
     * 
     * @var array
     */
    protected $storeIds;
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency $storeBaseCurrency
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency $storeBaseCurrency,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        parent::__construct($wrapperFactory);
        $this->storeBaseCurrency = $storeBaseCurrency;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Get main table by dimensions
     * 
     * @param array $dimensions
     * @return string
     */
    protected function getMainTableByDimensions(array $dimensions): string
    {
        $tableMaintainer = $this->getSubjectPropertyValue('tableMaintainer');
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            return $tableMaintainer->getMainTableByDimensions($dimensions);
        } else {
            return $tableMaintainer->getMainTable($dimensions);
        }
    }
    
    /**
     * Get store IDs
     * 
     * @return array
     */
    protected function getStoreIds(): array
    {
        if ($this->storeIds !== null) {
            return $this->storeIds;
        }
        $defaultIndexerResource = $this->getSubjectPropertyValue('_defaultIndexerResource');
        $connection = $defaultIndexerResource->getConnection();
        $select = $connection->select()
            ->from(['cw' => $defaultIndexerResource->getTable('store_website')], [])
            ->join(
                ['s' => $defaultIndexerResource->getTable('store')],
                's.website_id = cw.website_id',
                ['store_id']
            )
            ->where('cw.website_id != 0');
        $storeIds = $connection->fetchCol($select);
        $this->storeIds = is_array($storeIds) ? $storeIds : [];
        return $this->storeIds;
    }
    
    /**
     * Prepare store date table
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function prepareStoreDateTable()
    {
        $data = [];
        $defaultIndexerResource = $this->getSubjectPropertyValue('_defaultIndexerResource');
        $storeManager = $this->getSubjectPropertyValue('_storeManager');
        $dateTime = $this->getSubjectPropertyValue('_dateTime');
        $localeDate = $this->getSubjectPropertyValue('_localeDate');
        $connection = $defaultIndexerResource->getConnection();
        foreach ($this->getStoreIds() as $storeId) {
            $store = $storeManager->getStore($storeId);
            if (empty($store)) {
                continue;
            }
            $data[] = [
                'store_id' => (int) $store->getId(),
                'default_store_id' => (int) $store->getWebsite()->getDefaultStore()->getId(),
                'store_date' => $dateTime->formatDate($localeDate->scopeTimeStamp($store), false),
                'rate' => $this->storeBaseCurrency->getRate((int) $store->getId()),
            ];
        }
        $table = $defaultIndexerResource->getTable('ambros_store__catalog_product_index_store');
        $this->invokeSubjectMethod('_emptyTable', $table);
        if (empty($data)) {
            return $this;
        }
        foreach ($data as $row) {
            $connection->insertOnDuplicate($table, $row, array_keys($row));
        }
        return $this;
    }
    
    /**
     * Apply dimension condition
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param \Magento\Framework\Indexer\Dimension $dimension
     * @return $this
     */
    protected function applyDimensionCondition(\Magento\Framework\DB\Select $select, \Magento\Framework\Indexer\Dimension $dimension)
    {
        $dimensionName = $dimension->getName();
        $dimensionValue = $dimension->getValue();
        if ($dimensionName === \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME) {
            $select->where('ip_tmp.store_id = ?', $dimensionValue);
        }
        if ($dimensionName === \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME) {
            $select->where('ip_tmp.customer_group_id = ?', $dimensionValue);
        }
        return $this;
    }
    
    /**
     * Synchronize data
     *
     * @param array $processIds
     * @return $this
     */
    protected function syncData(array $processIds = [])
    {
        $dimensionCollectionFactory = $this->getSubjectPropertyValue('dimensionCollectionFactory');
        $defaultIndexerResource = $this->getSubjectPropertyValue('_defaultIndexerResource');
        $tableMaintainer = $this->getSubjectPropertyValue('tableMaintainer');
        $connection = $defaultIndexerResource->getConnection();
        foreach ($dimensionCollectionFactory->create() as $dimensions) {
            $select = $connection->select()->from(['ip_tmp' => $defaultIndexerResource->getIdxTable()]);
            foreach ($dimensions as $dimension) {
                $this->applyDimensionCondition($select, $dimension);
            }
            $connection->query($select->insertFromSelect($this->getMainTableByDimensions($dimensions)));
        }
        return $this;
    }
    
    /**
     * Re-index rows
     *
     * @param array $changedIds
     * @return array
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function reindexRows($changedIds = []): array
    {
        $subject = $this->getSubject();
        $dimensionCollectionFactory = $this->getSubjectPropertyValue('dimensionCollectionFactory');
        $defaultIndexerResource = $this->getSubjectPropertyValue('_defaultIndexerResource');
        $tableMaintainer = $this->getSubjectPropertyValue('tableMaintainer');
        $this->prepareStoreDateTable();
        $productsTypes = $this->invokeSubjectMethod('getProductsTypes', $changedIds);
        $parentProductsTypes = $this->invokeSubjectMethod('getParentProductsTypes', $changedIds);
        $changedIds = array_unique(array_merge($changedIds, ...array_values($parentProductsTypes)));
        $productsTypes = array_merge_recursive($productsTypes, $parentProductsTypes);
        if ($changedIds) {
            $this->invokeSubjectMethod('deleteIndexData', $changedIds);
        }
        $typeIndexers = $subject->getTypeIndexers();
        foreach ($typeIndexers as $productType => $indexer) {
            $entityIds = $productsTypes[$productType] ?? [];
            if (empty($entityIds)) {
                continue;
            }
            if ($indexer instanceof \Magento\Framework\Indexer\DimensionalIndexerInterface) {
                foreach ($dimensionCollectionFactory->create() as $dimensions) {
                    $tableMaintainer->createMainTmpTable($dimensions);
                    $temporaryTable = $tableMaintainer->getMainTmpTable($dimensions);
                    $this->invokeSubjectMethod('_emptyTable', $temporaryTable);
                    $indexer->executeByDimensions($dimensions, \SplFixedArray::fromArray($entityIds, false));
                    $this->invokeSubjectMethod('_insertFromTable', $temporaryTable, $this->getMainTableByDimensions($dimensions));
                }
            } else {
                $this->invokeSubjectMethod('_emptyTable', $defaultIndexerResource->getIdxTable());
                $this->invokeSubjectMethod('_copyRelationIndexData', $entityIds);
                $indexer->reindexEntity($entityIds);
                $this->syncData($entityIds);
            }
        }
        return $changedIds;
    }
    
}