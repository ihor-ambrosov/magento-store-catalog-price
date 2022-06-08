<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer;

/**
 * Bundle price indexer
 */
class BundlePrice
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Join attribute processor
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor
     */
    protected $joinAttributeProcessor;
    
    /**
     * Module manager
     * 
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;
    
    /**
     * Dimension condition applier
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier
     */
    protected $dimensionConditionApplier;
    
    /**
     * Event manager
     * 
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;
    
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
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor $joinAttributeProcessor
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier $dimensionConditionApplier
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor $joinAttributeProcessor,
        \Magento\Framework\Module\Manager $moduleManager,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier $dimensionConditionApplier,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->joinAttributeProcessor = $joinAttributeProcessor;
        $this->moduleManager = $moduleManager;
        $this->dimensionConditionApplier = $dimensionConditionApplier;
        $this->eventManager = $eventManager;
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
        $this->connectionProvider->getConnection()->delete($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_tmp'));
        return $this;
    }
    
    /**
     * Add tier price join
     * 
     * @param \Magento\Framework\DB\Select $select
     * @return $this
     */
    protected function addTierPriceJoin(\Magento\Framework\DB\Select $select)
    {
        $select->joinLeft(
            ['tp' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_tier_price')],
            $this->connectionProvider->getCondition([
                'tp.entity_id = e.entity_id',
                'tp.store_id = s.store_id',
                'tp.customer_group_id = cg.customer_group_id',
            ], 'AND'),
            []
        );
        return $this;
    }
    
    /**
     * Get tax class SQL
     * 
     * @param \Magento\Framework\DB\Select $select
     * @return \Zend_Db_Expr
     */
    protected function getTaxClassSql(\Magento\Framework\DB\Select $select): \Zend_Db_Expr
    {
        if ($this->moduleManager->isEnabled('Magento_Tax')) {
            return $this->joinAttributeProcessor->process($select, 'tax_class_id');
        } else {
            return $this->connectionProvider->getSql('0');
        }
    }
    
    /**
     * Add tax class column
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param int $priceType
     * @return $this
     */
    protected function addTaxClassColumn(\Magento\Framework\DB\Select $select, int $priceType)
    {
        if ($priceType == \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC) {
            $select->columns(['tax_class_id' => $this->connectionProvider->getSql('0')]);
        } else {
            $taxClassId = (string) $this->getTaxClassSql($select);
            $select->columns([
                'tax_class_id' => $this->connectionProvider->getConnection()->getCheckSql($taxClassId.' IS NOT NULL', $taxClassId, 0)
            ]);
        }
        return $this;
    }
    
    /**
     * Get special price condition SQL
     * 
     * @param string $specialPrice
     * @param string $specialFrom
     * @param string $specialTo
     * @return \Zend_Db_Expr
     */
    protected function getSpecialPriceConditionSql(string $specialPrice, string $specialFrom, string $specialTo): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $currentDate = 'csd.store_date';
        return $this->connectionProvider->getSql(
            $this->connectionProvider->getCondition([
                $specialPrice.' IS NOT NULL',
                $specialPrice.' > 0',
                $specialPrice.' < 100',
                $this->connectionProvider->getCondition([
                    $specialFrom.' IS NULL',
                    $connection->getDatePartSql($specialFrom).' <= '.$currentDate,
                ], 'OR'),
                $this->connectionProvider->getCondition([
                    $specialTo.' IS NULL',
                    $connection->getDatePartSql($specialTo).' >= '.$currentDate
                ], 'OR'),
            ], 'AND')
        );
    }
    
    /**
     * Get special price SQL
     * 
     * @param string $specialPrice
     * @param string $specialFrom
     * @param string $specialTo
     * @return \Zend_Db_Expr
     */
    protected function getSpecialPriceSql(string $specialPrice, string $specialFrom, string $specialTo): \Zend_Db_Expr
    {
        return $this->connectionProvider->getConnection()
            ->getCheckSql(
                $this->getSpecialPriceConditionSql($specialPrice, $specialFrom, $specialTo),
                $specialPrice,
                '0'
            );
    }
    
    /**
     * Get tier price percentage SQL
     * 
     * @return \Zend_Db_Expr
     */
    protected function getTierPricePercentageSql(): \Zend_Db_Expr
    {
        return $this->connectionProvider->getSql('tp.min_price');
    }
    
    /**
     * Get tier price SQL
     * 
     * @param int $priceType
     * @param string $price
     * @return \Zend_Db_Expr
     */
    protected function getTierPriceSql(int $priceType, string $price): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $tierPricePercentage = (string) $this->getTierPricePercentageSql();
        if ($priceType == \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC) {
            return $connection->getCheckSql(
                $tierPricePercentage.' IS NOT NULL',
                '0',
                'NULL'
            );
        } else {
            return $connection->getCheckSql(
                $tierPricePercentage.' IS NOT NULL',
                (string) $this->connectionProvider->getInversePercentValueRoundSql($price, $tierPricePercentage),
                'NULL'
            );
        }
    }
    
    /**
     * Get final price SQL
     * 
     * @param int $priceType
     * @param string $price
     * @param string $specialPrice
     * @param string $specialFrom
     * @param string $specialTo
     * @return \Zend_Db_Expr
     */
    protected function getFinalPriceSql(int $priceType, string $price, string $specialPrice, string $specialFrom, string $specialTo): \Zend_Db_Expr
    {
        if ($priceType == \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC) {
            return $this->connectionProvider->getSql('0');
        } else {
            $connection = $this->connectionProvider->getConnection();
            $tierPrice = (string) $this->getTierPriceSql($priceType, $price);
            return $connection->getLeastSql([
                $price,
                (string) $connection->getIfNullSql(
                    (string) $connection->getCheckSql(
                        (string) $this->getSpecialPriceConditionSql($specialPrice, $specialFrom, $specialTo),
                        (string) $this->connectionProvider->getPercentValueRoundSql($price, $specialPrice),
                        'NULL'
                    ), 
                    $price
                ),
                (string) $connection->getIfNullSql($tierPrice, $price),
            ]);
        }
    }
    
    /**
     * Add customer group join
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param array $dimensions
     * @return $this
     */
    protected function addCustomerGroupJoin(\Magento\Framework\DB\Select $select, array $dimensions)
    {
        $customerGroupDimensionName = \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME;
        if (array_key_exists($customerGroupDimensionName, $dimensions)) {
            $customerGroupValue = $dimensions[$customerGroupDimensionName]->getValue();
            $customerGroupField = $this->dimensionConditionApplier->getDimensionField($customerGroupDimensionName);
            $customerGroupJoinCondition = sprintf('%s = %s', $customerGroupField, $customerGroupValue);
        } else {
            $customerGroupJoinCondition = '';
        }
        $select->joinInner(
            ['cg' => $this->connectionProvider->getTable('customer_group')],
            $customerGroupJoinCondition,
            ['customer_group_id']
        );
        return $this;
    }
    
    /**
     * Add store joins
     * 
     * @param \Magento\Framework\DB\Select $select
     * @return $this
     */
    protected function addStoreJoins(\Magento\Framework\DB\Select $select)
    {
        if ($this->moduleManager->isEnabled('Ambros_StoreCatalog')) {
            $select->joinInner(
                ['ps' => $this->connectionProvider->getTable('ambros_store__catalog_product_store')],
                'ps.product_id = e.entity_id',
                []
            );
            $select->joinInner(
                ['s' => $this->connectionProvider->getTable('store')],
                's.store_id = ps.store_id',
                ['s.store_id']
            );
        } else {
            $select->joinInner(
                ['pw' => $this->connectionProvider->getTable('catalog_product_website')],
                'pw.product_id = e.entity_id',
                []
            );
            $select->joinInner(
                ['s' => $this->connectionProvider->getTable('store')],
                's.website_id = pw.website_id',
                ['s.store_id']
            );
        }
        $select->joinInner(
            ['csd' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_store')],
            's.store_id = csd.store_id',
            []
        );
        return $this;
    }
    
    /**
     * Get select
     * 
     * @param int $priceType
     * @param array $dimensions
     * @param array $entityIds
     * @return \Magento\Framework\DB\Select
     */
    protected function getSelect(int $priceType, array $dimensions, array $entityIds): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $connection->select()->from(['e' => $this->connectionProvider->getTable('catalog_product_entity')], ['entity_id']);
        $this->addCustomerGroupJoin($select, $dimensions);
        $this->addStoreJoins($select);
        $this->addTierPriceJoin($select);
        $select->where('e.type_id = ?', \Magento\Bundle\Model\Product\Type::TYPE_CODE);
        $this->dimensionConditionApplier->execute($select, $dimensions);
        $this->joinAttributeProcessor->process($select, 'status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $this->addTaxClassColumn($select, $priceType);
        $this->joinAttributeProcessor->process($select, 'price_type', $priceType);
        $price = (string) $this->joinAttributeProcessor->process($select, 'price');
        $specialPrice = (string) $this->joinAttributeProcessor->process($select, 'special_price');
        $specialFrom = (string) $this->joinAttributeProcessor->process($select, 'special_from_date');
        $specialTo = (string) $this->joinAttributeProcessor->process($select, 'special_to_date');
        $tierPrice = $this->getTierPriceSql($priceType, $price);
        $finalPrice = $this->getFinalPriceSql($priceType, $price, $specialPrice, $specialFrom, $specialTo);
        $select->columns([
            'price_type' => $this->connectionProvider->getSql((string) $priceType),
            'special_price' => $this->getSpecialPriceSql($specialPrice, $specialFrom, $specialTo),
            'tier_percent' => $this->getTierPricePercentageSql(),
            'orig_price' => $connection->getIfNullSql($price, '0'),
            'price' => $finalPrice,
            'min_price' => $finalPrice,
            'max_price' => $finalPrice,
            'tier_price' => $tierPrice,
            'base_tier' => $tierPrice,
        ]);
        if ($entityIds !== null) {
            $select->where('e.entity_id IN(?)', $entityIds);
        }
        return $select;
    }
    
    /**
     * Execute by price type
     *
     * @param int $priceType
     * @param array $dimensions
     * @param array $entityIds
     * @return $this
     */
    protected function executeByPriceType(int $priceType, array $dimensions, array $entityIds)
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $this->getSelect($priceType, $dimensions, $entityIds);
        $this->eventManager->dispatch('catalog_product_prepare_index_select', [
            'select' => $select,
            'entity_field' => $this->connectionProvider->getSql('e.entity_id'),
            'website_field' => $this->connectionProvider->getSql('s.website_id'),
            'store_field' => $this->connectionProvider->getSql('s.store_id')
        ]);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, $this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_tmp'), []);
        } else {
            $connection->query($select->insertFromSelect($this->connectionProvider->getTable('ambros_store__catalog_product_index_price_bundle_tmp')));
        }
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
        $this->clear();
        $this->executeByPriceType(
            \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED,
            $dimensions,
            $entityIds
        );
        $this->executeByPriceType(
            \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC,
            $dimensions,
            $entityIds
        );
        return $this;
    }
    
}