<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Indexer\Price;

/**
 * Product tier price indexer plugin
 */
class TierPrice extends \Ambros\Common\Plugin\Plugin
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
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
    )
    {
        $this->connectionProvider = $connectionProvider;
        parent::__construct($wrapperFactory);
    }
    
    /**
     * Add store join
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param bool $allStores
     * @return $this
     */
    protected function addStoreJoin(\Magento\Framework\DB\Select $select, bool $allStores)
    {
        $storeTable = ['s' => $this->connectionProvider->getTable('store')];
        if ($allStores) {
            $select
                ->joinCross($storeTable, [])
                ->where('s.store_id <> ?', 0)
                ->where('tp.store_id = ?', 0);
        } else {
            $select
                ->join($storeTable, 's.store_id = tp.store_id', [])
                ->where('tp.store_id <> 0');
        }
        return $this;
    }
    
    /**
     * Add customer group join
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param bool $allCustomerGroups
     * @return $this
     */
    protected function addCustomerGroupJoin(\Magento\Framework\DB\Select $select, bool $allCustomerGroups)
    {
        $customerGroupTable = ['cg' => $this->connectionProvider->getTable('customer_group')];
        if ($allCustomerGroups) {
            $select
                ->joinCross($customerGroupTable, [])
                ->where('tp.all_groups = ?', 1)
                ->where('tp.customer_group_id = ?', 0);
        } else {
            $select
                ->join($customerGroupTable, 'cg.customer_group_id = tp.customer_group_id', [])
                ->where('tp.all_groups = ?', 0);
        }
        return $this;
    }
    
    /**
     * Add price join
     * 
     * @param \Magento\Framework\DB\Select $select
     * @return string
     */
    protected function addPriceJoin(\Magento\Framework\DB\Select $select): string
    {
        $connection = $this->connectionProvider->getConnection();
        $priceAttribute = $this->getSubjectPropertyValue('attributeRepository')->get('price');
        $priceAttributeId = $priceAttribute->getAttributeId();
        $priceTable = $priceAttribute->getBackend()->getTable();
        $select->joinLeft(
            ['pd' => $priceTable],
            $this->connectionProvider->getCondition([
                'pd.entity_id = e.entity_id',
                'pd.attribute_id = '.$priceAttributeId,
                'pd.store_id = 0',
            ], 'AND'),
            []
        );
        if ($priceAttribute->isScopeGlobal()) {
            return 'pd.value';
        }
        $select->joinLeft(
            ['ps' => $priceTable],
            $this->connectionProvider->getCondition([
                'ps.entity_id = e.entity_id',
                'ps.attribute_id = '.$priceAttributeId,
                'ps.store_id = s.store_id',
            ]),
            []
        );
        return (string) $connection->getIfNullSql('ps.value', 'pd.value');
    }
    
    /**
     * Get select
     * 
     * @param bool $allStores
     * @param bool $allCustomerGroups
     * @param array $entityIds
     * @return \Magento\Framework\DB\Select
     */
    protected function getSelect(bool $allStores, bool $allCustomerGroups, array $entityIds = []): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $connection->select()
            ->from(['tp' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_tier_price')], [])
            ->where('tp.qty = ?', 1)
            ->join(
                ['e' => $this->connectionProvider->getTable('catalog_product_entity')],
                'e.entity_id = tp.entity_id',
                []
            );
        if (!empty($entityIds)) {
            $select->where('e.entity_id IN (?)', $entityIds, \Zend_Db::INT_TYPE);
        }
        $this->addStoreJoin($select, $allStores);
        $this->addCustomerGroupJoin($select, $allCustomerGroups);
        $price = $this->addPriceJoin($select);
        $select->columns([
            'e.entity_id',
            'cg.customer_group_id',
            's.store_id',
            'tier_price' => $connection->getCheckSql(
                'tp.value',
                'tp.value',
                (string) $this->connectionProvider->getInversePercentValueSql($price, 'tp.percentage_value')
            ),
        ]);
        return $select;
    }
    
    /**
     * Before get main table
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\TierPrice $subject
     * @return void
     */
    public function beforeGetMainTable(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\TierPrice $subject
    )
    {
        $this->setSubject($subject);
        $this->invokeSubjectMethod('_setMainTable', 'ambros_store__catalog_product_index_tier_price', 'value_id');
    }
    
    /**
     * Around re-index entity
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\TierPrice $subject
     * @param \Closure $proceed
     * @param array $entityIds
     * @return void
     */
    public function aroundReindexEntity(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\TierPrice $subject,
        \Closure $proceed,
        array $entityIds = []
    )
    {
        $this->setSubject($subject);
        $connection = $this->connectionProvider->getConnection();
        $table = $subject->getMainTable();
        $connection->delete($table, ['entity_id IN (?)' => $entityIds]);
        $combinations = [
            [true, true],
            [true, false],
            [false, true],
            [false, false],
        ];
        foreach ($combinations as $combination) {
            $connection->query(
                $this->getSelect($combination[0], $combination[1], $entityIds)->insertFromSelect($table)
            );
        }
    }
    
}