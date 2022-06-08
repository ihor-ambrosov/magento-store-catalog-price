<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\GroupedProduct\ResourceModel\Product\Indexer\Price;

/**
 * Final price indexer
 */
class FinalPrice
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Price table structure provider
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\IndexTableStructureProvider
     */
    protected $priceTableStructureProvider;
    
    /**
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer 
     */
    protected $tableMaintainer;
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\IndexTableStructureProvider $priceTableStructureProvider
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\IndexTableStructureProvider $priceTableStructureProvider,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceTableStructureProvider = $priceTableStructureProvider;
        $this->tableMaintainer = $tableMaintainer;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Get main table
     *
     * @param array $dimensions
     * @param bool $fullReindexAction
     * @return string
     */
    protected function getMainTable(array $dimensions, bool $fullReindexAction): string
    {
        if ($fullReindexAction) {
            return $this->tableMaintainer->getMainReplicaTable($dimensions);
        }
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            return $this->tableMaintainer->getMainTableByDimensions($dimensions);
        } else {
            return $this->tableMaintainer->getMainTable($dimensions);
        }
    }
    
    /**
     * Get select
     * 
     * @param array $dimensions
     * @param array $entityIds
     * @param bool $fullReindexAction
     * @return \Magento\Framework\DB\Select
     */
    public function getSelect(array $dimensions, array $entityIds, bool $fullReindexAction): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        $null = $this->connectionProvider->getSql('NULL');
        $select = $connection->select()
            ->from(['e' => $this->connectionProvider->getTable('catalog_product_entity')], 'entity_id')
            ->joinLeft(
                ['l' => $this->connectionProvider->getTable('catalog_product_link')],
                $this->connectionProvider->getCondition([
                    'e.entity_id = l.product_id',
                    'l.link_type_id = '.\Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED,
                ], 'AND'),
                []
            )
            ->joinLeft(
                ['le' => $this->connectionProvider->getTable('catalog_product_entity')],
                'le.entity_id = l.linked_product_id',
                []
            )
            ->columns(['i.customer_group_id', 'i.store_id'])
            ->join(
                ['i' => $this->getMainTable($dimensions, $fullReindexAction)],
                'i.entity_id = l.linked_product_id',
                [
                    'tax_class_id' => $connection->getCheckSql('MIN(i.tax_class_id) IS NULL', '0', 'MIN(i.tax_class_id)'),
                    'price' => $null,
                    'final_price' => $null,
                    'min_price' => $this->connectionProvider->getSql(
                        'MIN('.(string) $connection->getCheckSql('le.required_options = 0', 'i.min_price', 0).')'
                    ),
                    'max_price' => $this->connectionProvider->getSql(
                        'MAX('.(string) $connection->getCheckSql('le.required_options = 0', 'i.max_price', 0).')'
                    ),
                    'tier_price' => $null,
                ]
            )
            ->group(['e.entity_id', 'i.customer_group_id', 'i.store_id'])
            ->where('e.type_id = ?', \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE);
        if ($entityIds !== null) {
            $select->where('e.entity_id IN (?)', $entityIds);
        }
        return $select;
    }
    
    /**
     * Execute
     * 
     * @param array $dimensions
     * @param array $entityIds
     * @param bool $fullReindexAction
     * @return $this
     */
    public function execute(array $dimensions, array $entityIds, bool $fullReindexAction)
    {
        $priceTableStructure = $this->priceTableStructureProvider->create($dimensions);
        $select = $this->getSelect($dimensions, $entityIds, $fullReindexAction);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, $priceTableStructure->getTableName(), []);
        } else {
            $this->connectionProvider->getConnection()->query($select->insertFromSelect($priceTableStructure->getTableName()));
        }
        return $this;
    }
    
}