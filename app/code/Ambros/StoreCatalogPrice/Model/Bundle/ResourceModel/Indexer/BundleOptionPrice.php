<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer;

/**
 * Bundle option price indexer
 */
class BundleOptionPrice
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
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
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
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
        $this->connectionProvider->getConnection()->delete($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_opt_tmp'));
        return $this;
    }
    
    /**
     * Execute
     * 
     * @return $this
     */
    public function execute()
    {
        $connection = $this->connectionProvider->getConnection();
        $this->clear();
        $columns = ['entity_id', 'customer_group_id', 'store_id', 'option_id'];
        $select = $connection->select()
            ->from($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_sel_tmp'), $columns)
            ->group($columns);
        $price = (string) $connection->getCheckSql('is_required = 1', 'price', 'NULL');
        $tierPrice = (string) $connection->getCheckSql('is_required = 1', 'tier_price', 'NULL');
        $select->columns([
            'min_price' => $this->connectionProvider->getSql('MIN('.$price.')'),
            'alt_price' => $this->connectionProvider->getSql('MIN(price)'),
            'max_price' => $connection->getCheckSql('group_type = 0', 'MAX(price)', 'SUM(price)'),
            'tier_price' => $this->connectionProvider->getSql('MIN('.$tierPrice.')'),
            'alt_tier_price' => $this->connectionProvider->getSql('MIN(tier_price)'),
        ]);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_opt_tmp'), []);
        } else {
            $connection->query($select->insertFromSelect($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_opt_tmp')));
        }
        return $this;
    }
    
}