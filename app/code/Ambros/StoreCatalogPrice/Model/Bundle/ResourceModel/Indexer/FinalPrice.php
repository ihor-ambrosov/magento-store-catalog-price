<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer;

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
     * Base price modifier
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier 
     */
    protected $basePriceModifier;
    
    /**
     * Tier price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\TierPrice
     */
    protected $tierPrice;
    
    /**
     * Bundle price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundlePrice
     */
    protected $bundlePrice;
    
    /**
     * Bundle selection price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundleSelectionPrice
     */
    protected $bundleSelectionPrice;
    
    /**
     * Bundle option price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundleOptionPrice
     */
    protected $bundleOptionPrice;
    
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
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier $basePriceModifier
     * @param \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\TierPrice $tierPrice
     * @param \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundlePrice $bundlePrice
     * @param \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundleSelectionPrice $bundleSelectionPrice
     * @param \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundleOptionPrice $bundleOptionPrice
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\IndexTableStructureProvider $priceTableStructureProvider,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier $basePriceModifier,
        \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\TierPrice $tierPrice,
        \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundlePrice $bundlePrice,
        \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundleSelectionPrice $bundleSelectionPrice,
        \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\BundleOptionPrice $bundleOptionPrice,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceTableStructureProvider = $priceTableStructureProvider;
        $this->tableMaintainer = $tableMaintainer;
        $this->basePriceModifier = $basePriceModifier;
        $this->tierPrice = $tierPrice;
        $this->bundlePrice = $bundlePrice;
        $this->bundleSelectionPrice = $bundleSelectionPrice;
        $this->bundleOptionPrice = $bundleOptionPrice;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Apply bundle price
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure
     * @return $this
     */
    protected function applyBundlePrice(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure
    )
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $connection->select()
            ->from($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_tmp'), [
                'entity_id',
                'customer_group_id',
                'store_id',
                'tax_class_id',
                'orig_price',
                'price',
                'min_price',
                'max_price',
                'tier_price',
            ]);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, $priceTableStructure->getTableName(), []);
        } else {
            $connection->query($select->insertFromSelect($priceTableStructure->getTableName()));
        }
        return $this;
    }

    /**
     * Apply bundle option price
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure
     * @return $this
     */
    protected function applyBundleOptionPrice(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTableStructure
    )
    {
        $connection = $this->connectionProvider->getConnection();
        $minPrice = 'i.min_price + '.((string) $connection->getIfNullSql('io.min_price', '0'));
        $tierPrice = 'i.tier_price + '.((string) $connection->getIfNullSql('io.tier_price', '0'));
        $select = $connection->select()
            ->join(
                [
                    'io' => $connection->select()
                        ->from($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_opt_tmp'), [
                            'entity_id',
                            'customer_group_id',
                            'store_id',
                            'min_price' => $this->connectionProvider->getSql('SUM(min_price)'),
                            'alt_price' => $this->connectionProvider->getSql('MIN(alt_price)'),
                            'max_price' => $this->connectionProvider->getSql('SUM(max_price)'),
                            'tier_price' => $this->connectionProvider->getSql('SUM(tier_price)'),
                            'alt_tier_price' => $this->connectionProvider->getSql('MIN(alt_tier_price)'),
                        ])
                        ->group(['entity_id', 'customer_group_id', 'store_id'])
                ],
                $this->connectionProvider->getCondition([
                    'i.entity_id = io.entity_id',
                    'i.customer_group_id = io.customer_group_id',
                    'i.store_id = io.store_id',
                ], 'AND'),
                []
            )
            ->columns([
                'min_price' => $connection->getCheckSql($minPrice.' = 0', 'io.alt_price', $minPrice),
                'max_price' => $this->connectionProvider->getSql('io.max_price + i.max_price'),
                'tier_price' => $connection->getCheckSql($tierPrice.' = 0', 'io.alt_tier_price', $tierPrice),
            ]);
        $connection->query($select->crossUpdateFromSelect(['i' => $priceTableStructure->getTableName()]));
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
        $this->tableMaintainer->createMainTmpTable($dimensions);
        $this->tierPrice->execute($dimensions, $entityIds);
        $this->bundlePrice->execute($dimensions, $entityIds);
        $this->bundleSelectionPrice->execute($dimensions, $fullReindexAction);
        $this->bundleOptionPrice->execute($dimensions, $entityIds);
        $this->connectionProvider->getConnection()->delete($priceTableStructure->getTableName());
        $this->applyBundlePrice($priceTableStructure);
        $this->applyBundleOptionPrice($priceTableStructure);
        $this->basePriceModifier->modifyPrice($priceTableStructure, $entityIds);
        return $this;
    }
    
}