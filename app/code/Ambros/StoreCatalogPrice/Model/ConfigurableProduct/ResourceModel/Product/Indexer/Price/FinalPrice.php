<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price;

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
     * Configurable option price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price\ConfigurableOptionPrice 
     */
    protected $configurableOptionPrice;
    
    /**
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer
     */
    protected $tableMaintainer;
    
    /**
     * Base final price
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice 
     */
    protected $baseFinalPrice;
    
    /**
     * Base price modifier
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier
     */
    protected $basePriceModifier;
    
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
     * @param \Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price\ConfigurableOptionPrice $configurableOptionPrice
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice $baseFinalPrice
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier $basePriceModifier
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\IndexTableStructureProvider $priceTableStructureProvider,
        \Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price\ConfigurableOptionPrice $configurableOptionPrice,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice $baseFinalPrice,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier $basePriceModifier,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceTableStructureProvider = $priceTableStructureProvider;
        $this->configurableOptionPrice = $configurableOptionPrice;
        $this->tableMaintainer = $tableMaintainer;
        $this->baseFinalPrice = $baseFinalPrice;
        $this->basePriceModifier = $basePriceModifier;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Apply configurable option price
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure
     * @param array $dimensions
     * @param array $entityIds
     * @param bool $fullReindexAction
     * @return $this
     */
    protected function applyConfigurableOptionPrice(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure,
        array $dimensions,
        array $entityIds,
        bool $fullReindexAction
    )
    {
        $connection = $this->connectionProvider->getConnection();
        $temporaryTableName = 'ambros_store__catalog_product_index_price_cfg_opt_temp';
        $connection->createTemporaryTableLike(
            $temporaryTableName,
            $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_cfg_opt_tmp'),
            true
        );
        $this->configurableOptionPrice->execute($dimensions, $entityIds, $fullReindexAction);
        $connection->query(
            $connection->select()
                ->join(
                    ['io' => $temporaryTableName],
                    $this->connectionProvider->getCondition([
                        'i.entity_id = io.entity_id',
                        'i.customer_group_id = io.customer_group_id',
                        'i.store_id = io.store_id',
                    ], 'AND'),
                    []
                )
                ->columns([
                    'min_price' => $this->connectionProvider->getSql('i.min_price - i.price + io.min_price'),
                    'max_price' => $this->connectionProvider->getSql('i.max_price - i.price + io.max_price'),
                    'tier_price' => $this->connectionProvider->getSql('io.tier_price'),
                ])
                ->crossUpdateFromSelect(['i' => $priceTableStructure->getTableName()])
        );
        $connection->delete($temporaryTableName);
        return $this;
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
        $select = $this->baseFinalPrice->getQuery(
            $dimensions,
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE,
            $entityIds
        );
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, $priceTableStructure->getTableName(), []);
        } else {
            $this->connectionProvider->getConnection()->query($select->insertFromSelect($priceTableStructure->getTableName(), [], false));
        }
        $this->basePriceModifier->modifyPrice($priceTableStructure, $entityIds);
        $this->applyConfigurableOptionPrice($priceTableStructure, $dimensions, $entityIds, $fullReindexAction);
        return $this;
    }
    
}