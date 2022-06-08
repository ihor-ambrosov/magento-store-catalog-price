<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer;

/**
 * Tier price indexer
 */
class TierPrice
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Dimension condition applier
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier 
     */
    protected $dimensionConditionApplier;
    
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
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier $dimensionConditionApplier
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier $dimensionConditionApplier,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->dimensionConditionApplier = $dimensionConditionApplier;
        $this->tableMaintainer = $tableMaintainer;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Clear
     * 
     * @return $this
     */
    protected function clear()
    {
        $connection = $this->connectionProvider->getConnection();
        $connection->query(
            $connection->select()
                ->from(['i' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_tier_price')], null)
                ->join(
                    ['e' => $this->connectionProvider->getTable('catalog_product_entity')],
                    'i.entity_id = e.entity_id',
                    []
                )
                ->where('e.type_id = ?', \Magento\Bundle\Model\Product\Type::TYPE_CODE)
                ->deleteFromSelect('i')
        );
        return $this;
    }
    
    /**
     * Execute
     *
     * @param array $dimensions
     * @param array $entityIds
     * @return $this
     */
    public function execute(array $dimensions, array $entityIds)
    {
        $connection = $this->connectionProvider->getConnection();
        $this->clear();
        $select = $connection->select()
            ->from(
                ['tp' => $this->connectionProvider->getTable('ambros_store__catalog_product_entity_tier_price')],
                ['e.entity_id']
            )
            ->join(
                ['e' => $this->connectionProvider->getTable('catalog_product_entity')],
                'tp.entity_id = e.entity_id',
                []
            )
            ->join(
                ['cg' => $this->connectionProvider->getTable('customer_group')],
                $this->connectionProvider->getCondition([
                    'tp.all_groups = 1',
                    $this->connectionProvider->getCondition([
                        'tp.all_groups = 0',
                        'tp.customer_group_id = cg.customer_group_id',
                    ], 'AND'),
                ], 'OR'),
                ['customer_group_id']
            )
            ->join(
                ['ps' => $this->connectionProvider->getTable('store')],
                $this->connectionProvider->getCondition([
                    'tp.store_id = 0',
                    'tp.store_id = ps.store_id',
                ], 'OR'),
                ['store_id']
            )
            ->where('ps.store_id != 0')
            ->where('e.type_id = ?', \Magento\Bundle\Ui\DataProvider\Product\Listing\Collector\BundlePrice::PRODUCT_TYPE)
            ->columns($this->connectionProvider->getSql('MIN(tp.value)'))
            ->group(['e.entity_id', 'cg.customer_group_id', 'ps.store_id']);
        if (!empty($entityIds)) {
            $select->where('e.entity_id IN(?)', $entityIds);
        }
        $this->dimensionConditionApplier->execute($select, $dimensions);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, $this->connectionProvider->getTable('ambros_store__catalog_product_index_tier_price'), []);
        } else {
            $connection->query($select->insertFromSelect($this->connectionProvider->getTable('ambros_store__catalog_product_index_tier_price')));
        }
        return $this;
    }
    
}