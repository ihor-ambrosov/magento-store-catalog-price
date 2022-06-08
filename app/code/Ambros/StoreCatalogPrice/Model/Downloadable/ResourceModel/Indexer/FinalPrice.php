<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer;

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
     * Downloadable link price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer\DownloadableLinkPrice
     */
    protected $downloadableLinkPrice;
    
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
     * @param \Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer\DownloadableLinkPrice $downloadableLinkPrice
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice $baseFinalPrice
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier $basePriceModifier
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\IndexTableStructureProvider $priceTableStructureProvider,
        \Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer\DownloadableLinkPrice $downloadableLinkPrice,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice $baseFinalPrice,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier $basePriceModifier,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceTableStructureProvider = $priceTableStructureProvider;
        $this->downloadableLinkPrice = $downloadableLinkPrice;
        $this->baseFinalPrice = $baseFinalPrice;
        $this->basePriceModifier = $basePriceModifier;
        $this->tableMaintainer = $tableMaintainer;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Apply downloadable link price
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure
     * @param array $dimensions
     * @return $this
     */
    protected function applyDownloadableLinkPrice(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure,
        array $dimensions
    )
    {
        $connection = $this->connectionProvider->getConnection();
        $temporaryTableName = 'ambros_store__catalog_product_index_price_downlod_temp';
        $connection->createTemporaryTableLike(
            $temporaryTableName,
            $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_downlod_tmp'),
            true
        );
        $this->downloadableLinkPrice->execute($dimensions);
        $connection->query(
            $connection->select()
                ->join(
                    ['id' => $temporaryTableName],
                    $this->connectionProvider->getCondition([
                        'i.entity_id = id.entity_id',
                        'i.customer_group_id = id.customer_group_id',
                        'i.store_id = id.store_id',
                    ], 'AND'),
                    []
                )
                ->columns([
                    'min_price' => $this->connectionProvider->getSql('i.min_price + id.min_price'),
                    'max_price' => $this->connectionProvider->getSql('i.max_price + id.max_price'),
                    'tier_price' => $this->connectionProvider->getSql((string) $connection->getCheckSql(
                        'i.tier_price IS NOT NULL',
                        '(i.tier_price + id.min_price)',
                        'NULL'
                    ))
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
     * @return $this
     */
    public function execute(array $dimensions, array $entityIds)
    {
        $priceTableStructure = $this->priceTableStructureProvider->create($dimensions);
        $select = $this->baseFinalPrice->getQuery(
            $dimensions,
            \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
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
        $this->applyDownloadableLinkPrice($priceTableStructure, $dimensions);
        return $this;
    }
    
}