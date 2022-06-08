<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\CatalogRule\Indexer;

/**
 * Catalog rule product price index modifier plugin
 */
class ProductPriceIndexModifier
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
    )
    {
        $this->connectionProvider = $connectionProvider;
    }
    
    /**
     * Around modify price
     * 
     * @param \Magento\CatalogRule\Model\Indexer\ProductPriceIndexModifier $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTable
     * @param array $entityIds
     * @return void
     * @throws \Exception
     */
    public function aroundModifyPrice(
        \Magento\CatalogRule\Model\Indexer\ProductPriceIndexModifier $subject,
        \Closure $proceed,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTable,
        array $entityIds = []
    ): void
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $connection->select();
        $select->join(
            ['cpis' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_store')],
            'cpis.store_id = i.store_id',
            []
        );
        $select->join(
            ['s' => $this->connectionProvider->getTable('store')],
            's.store_id = i.store_id',
            []
        );
        $select->join(
            ['cpp' => $this->connectionProvider->getTable('catalogrule_product_price')],
            $this->connectionProvider->getCondition([
                'cpp.product_id = i.'.$priceTable->getEntityField(),
                'cpp.customer_group_id = i.'.$priceTable->getCustomerGroupField(),
                'cpp.website_id = s.website_id',
                'cpp.rule_date = cpis.store_date',
            ], 'AND'),
            []
        );
        if ($entityIds) {
            $select->where('i.entity_id IN (?)', $entityIds, \Zend_Db::INT_TYPE);
        }
        $finalPriceField = $priceTable->getFinalPriceField();
        $minPriceField = $priceTable->getMinPriceField();
        $select->columns([
            $finalPriceField => $connection->getLeastSql([
                $finalPriceField,
                $connection->getIfNullSql('cpp.rule_price', 'i.'.$finalPriceField),
            ]),
            $minPriceField => $connection->getLeastSql([
                $minPriceField,
                $connection->getIfNullSql('cpp.rule_price', 'i.'.$minPriceField),
            ]),
        ]);
        $connection->query($connection->updateFromSelect($select, ['i' => $priceTable->getTableName()]));
    }
    
}