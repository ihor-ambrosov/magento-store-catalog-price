<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer;

/**
 * Bundle selection price indexer
 */
class BundleSelectionPrice
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
        $this->connectionProvider->getConnection()->delete($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_sel_tmp'));
        return $this;
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
        } else {
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
    }
    
    /**
     * Get group type SQL
     * 
     * @return \Zend_Db_Expr
     */
    protected function getGroupTypeSql(): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->getCheckSql($this->connectionProvider->getCondition(['bo.type = \'select\'', 'bo.type = \'radio\''], 'OR'), '0', '1');
    }
    
    /**
     * Get columns
     * 
     * @param \Zend_Db_Expr $price
     * @param \Zend_Db_Expr $tierPrice
     * @return array
     */
    protected function getColumns(\Zend_Db_Expr $price, \Zend_Db_Expr $tierPrice): array
    {
        return [
            'group_type' => $this->getGroupTypeSql(),
            'is_required' => 'bo.required',
            'price' => $price,
            'tier_price' => $tierPrice,
        ];
    }
    
    /**
     * Get base select
     * 
     * @return \Magento\Framework\DB\Select
     */
    protected function getBaseSelect(): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->select()
            ->from(
                ['i' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_tmp')],
                ['entity_id', 'customer_group_id', 'store_id']
            )
            ->join(
                ['parent_product' => $this->connectionProvider->getTable('catalog_product_entity')],
                'parent_product.entity_id = i.entity_id',
                []
            )
            ->join(
                ['bo' => $this->connectionProvider->getTable('catalog_product_bundle_option')],
                'bo.parent_id = parent_product.entity_id',
                ['option_id']
            )
            ->join(
                ['bs' => $this->connectionProvider->getTable('catalog_product_bundle_selection')],
                'bs.option_id = bo.option_id',
                ['selection_id']
            );
    }
    
    /**
     * Get fixed tier price SQL
     * 
     * @param string $selectionPrice
     * @param string $selectionPriceType
     * @return \Zend_Db_Expr
     */
    protected function getFixedTierPriceSql(string $selectionPrice, string $selectionPriceType): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->getCheckSql(
            'i.base_tier IS NOT NULL',
            (string) $connection->getCheckSql(
                $selectionPriceType.' = 1',
                (string) $this->connectionProvider->getRoundSql(
                    'i.base_tier - '.(string) $this->connectionProvider->getPercentValueSql('i.base_tier', $selectionPrice)
                ),
                (string) $connection->getCheckSql(
                    'i.tier_percent > 0',
                    (string) $this->connectionProvider->getInversePercentValueRoundSql($selectionPrice, 'i.tier_percent'),
                    $selectionPrice
                )
            ).' * bs.selection_qty',
            'NULL'
        );
    }
    
    /**
     * Get fixed special price SQL
     * 
     * @param string $selectionPrice
     * @param string $selectionPriceType
     * @return \Zend_Db_Expr
     */
    protected function getFixedSpecialPriceSql(string $selectionPrice, string $selectionPriceType): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        return $this->connectionProvider->getSql(
            (string) $connection->getCheckSql(
                $selectionPriceType.' = 1',
                (string) $this->connectionProvider->getPercentValueRoundSql('i.price', $selectionPrice),    
                (string) $connection->getCheckSql(
                    $this->connectionProvider->getCondition(['i.special_price > 0', 'i.special_price < 100'], 'AND'),
                    (string) $this->connectionProvider->getPercentValueRoundSql($selectionPrice, 'i.special_price'),
                    $selectionPrice
                )
            ).' * bs.selection_qty'
        );
    }
    
    /**
     * Get fixed price SQL
     * 
     * @param string $selectionPrice
     * @param string $selectionPriceType
     * @return \Zend_Db_Expr
     */
    protected function getFixedPriceSql(string $selectionPrice, string $selectionPriceType): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $specialPrice = (string) $this->getFixedSpecialPriceSql($selectionPrice, $selectionPriceType);
        $tierPrice = (string) $this->getFixedTierPriceSql($selectionPrice, $selectionPriceType);
        return $connection->getLeastSql([$specialPrice, $connection->getIfNullSql($tierPrice, $specialPrice)]);
    }
    
    /**
     * Execute fixed
     * 
     * @param array $dimensions
     * @param bool $fullReindexAction
     * @return $this
     */
    protected function executeFixed(array $dimensions, bool $fullReindexAction)
    {
        $connection = $this->connectionProvider->getConnection();
        $selectionTable = $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_sel_tmp');
        $priceType = \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED;
        $baseSelect = $this->getBaseSelect()
            ->where('i.price_type = ?', $priceType)
            ->columns($this->getColumns(
                $this->getFixedPriceSql('bs.selection_price_value', 'bs.selection_price_type'),
                $this->getFixedTierPriceSql('bs.selection_price_value', 'bs.selection_price_type')
            ));
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($baseSelect, $selectionTable, []);
        } else {
            $connection->query($baseSelect->insertFromSelect($selectionTable));
        }
        $connection->query(
            $connection->select()
                ->join(
                    ['i' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_tmp')],
                    $this->connectionProvider->getCondition([
                        'i.entity_id = bspi.entity_id',
                        'i.customer_group_id = bspi.customer_group_id',
                        'i.store_id = bspi.store_id',
                    ], 'AND'),
                    []
                )
                ->join(
                    ['bs' => $this->connectionProvider->getTable('catalog_product_bundle_selection')],
                    'bs.selection_id = bspi.selection_id',
                    []
                )
                ->joinInner(
                    ['bsp' => $this->connectionProvider->getTable('ambros_store__catalog_product_bundle_selection_price')],
                    $this->connectionProvider->getCondition([
                        'bsp.selection_id = bspi.selection_id',
                        'bsp.store_id = bspi.store_id',
                    ], 'AND'),
                    []
                )
                ->where('i.price_type = ?', $priceType)
                ->columns([
                    'price' => $this->getFixedPriceSql('bsp.selection_price_value', 'bsp.selection_price_type'),
                    'tier_price' => $this->getFixedTierPriceSql('bsp.selection_price_value', 'bsp.selection_price_type'),
                ])
                ->crossUpdateFromSelect(['bspi' => $selectionTable])
        );
        return $this;
    }
    
    /**
     * Get dynamic min price SQL
     * 
     * @return \Zend_Db_Expr
     */
    protected function getDynamicMinPriceSql(): \Zend_Db_Expr
    {
        return $this->connectionProvider->getSql('idx.min_price * bs.selection_qty');
    }
    
    /**
     * Get dynamic special price SQL
     * 
     * @return \Zend_Db_Expr
     */
    protected function getDynamicSpecialPriceSql(): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $minPrice = (string) $this->getDynamicMinPriceSql();
        return $connection->getCheckSql(
            $this->connectionProvider->getCondition(['i.special_price > 0', 'i.special_price < 100'], 'AND'),
            (string) $this->connectionProvider->getPercentValueRoundSql($minPrice, 'i.special_price'),
            $minPrice
        );
    }
    
    /**
     * Get dynamic tier price SQL
     * 
     * @return \Zend_Db_Expr
     */
    protected function getDynamicTierPriceSql(): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->getCheckSql(
            'i.tier_percent IS NOT NULL',
            (string) $this->connectionProvider->getInversePercentValueRoundSql((string) $this->getDynamicMinPriceSql(), 'i.tier_percent'),
            'NULL'
        );
    }
    
    /**
     * Get dynamic price SQL
     * 
     * @return \Zend_Db_Expr
     */
    protected function getDynamicPriceSql(): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->getLeastSql([
            $this->getDynamicSpecialPriceSql(),
            $connection->getIfNullSql((string) $this->getDynamicTierPriceSql(), (string) $this->getDynamicMinPriceSql()),
        ]);
    }
    
    /**
     * Execute dynamic
     *
     * @param array $dimensions
     * @param bool $fullReindexAction
     * @return $this
     */
    protected function executeDynamic(array $dimensions, bool $fullReindexAction)
    {
        $connection = $this->connectionProvider->getConnection();
        $selectionTable = $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_sel_tmp');
        $priceType = \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC;
        $baseSelect = $this->getBaseSelect()
            ->join(
                ['idx' => $this->getMainTable($dimensions, $fullReindexAction)],
                $this->connectionProvider->getCondition([
                    'bs.product_id = idx.entity_id',
                    'i.customer_group_id = idx.customer_group_id',
                    'i.store_id = idx.store_id',
                ], 'AND'),
                []
            )
            ->where('i.price_type = ?', $priceType)
            ->columns($this->getColumns($this->getDynamicPriceSql(), $this->getDynamicTierPriceSql()));
        
        if (version_compare($this->productMetadata->getVersion(), '2.4.2', '>=')) {
            $baseSelect->join(
                ['si' => $this->connectionProvider->getTable('cataloginventory_stock_status')],
                'si.product_id = bs.product_id',
                []
            );
            $baseSelect->where('si.stock_status = ?', \Magento\CatalogInventory\Model\Stock::STOCK_IN_STOCK);
        }
        
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($baseSelect, $selectionTable, []);
        } else {
            $connection->query($baseSelect->insertFromSelect($selectionTable));
        }
        return $this;
    }
    
    /**
     * Execute
     * 
     * @param array $dimensions
     * @param bool $fullReindexAction
     * @return $this
     */
    public function execute(array $dimensions, bool $fullReindexAction)
    {
        $this->clear();
        $this->executeFixed($dimensions, $fullReindexAction);
        $this->executeDynamic($dimensions, $fullReindexAction);
        return $this;
    }
    
}