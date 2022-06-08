<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Indexer\Price\Query;

/**
 * Base final price query plugin
 */
class BaseFinalPrice extends \Ambros\Common\Plugin\Plugin
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
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer
     */
    protected $tableMaintainer;
    
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
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor $joinAttributeProcessor
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier $dimensionConditionApplier
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor $joinAttributeProcessor,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\Module\Manager $moduleManager,
        \Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price\DimensionConditionApplier $dimensionConditionApplier
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->joinAttributeProcessor = $joinAttributeProcessor;
        $this->tableMaintainer = $tableMaintainer;
        $this->moduleManager = $moduleManager;
        $this->dimensionConditionApplier = $dimensionConditionApplier;
        parent::__construct($wrapperFactory);
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
            $customerField = $this->dimensionConditionApplier->getDimensionField($customerGroupDimensionName);
            $customerGroupJoinCondition = sprintf('%s = %s', $customerField, $customerGroupValue);
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
     * Get tier price join condition
     * 
     * @param string $tableAlias
     * @param string $allCustomerGroups
     * @param string $customerGroupId
     * @param string $qty
     * @param string $storeId
     * @return string
     */
    protected function getTierPriceJoinCondition(
        string $tableAlias,
        string $allCustomerGroups,
        string $customerGroupId,
        string $qty,
        string $storeId
    ): string
    {
        return $this->connectionProvider->getCondition([
            $tableAlias.'.entity_id = e.entity_id',
            $tableAlias.'.all_groups = '.$allCustomerGroups,
            $tableAlias.'.customer_group_id = '.$customerGroupId,
            $tableAlias.'.qty = '.$qty,
            $tableAlias.'.store_id = '.$storeId,
        ], 'AND');
    }
    
    /**
     * Add tier price joins
     * 
     * @param \Magento\Framework\DB\Select $select
     * @return $this
     */
    protected function addTierPriceJoins(\Magento\Framework\DB\Select $select)
    {
        $tierPriceTable = $this->connectionProvider->getTable('ambros_store__catalog_product_entity_tier_price');
        $select
            ->joinLeft(
                ['tp' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_tier_price')],
                $this->connectionProvider->getCondition([
                    'tp.entity_id = e.entity_id',
                    'tp.customer_group_id = cg.customer_group_id',
                    'tp.store_id = s.store_id',
                ], 'AND'),
                []
            )
            ->joinLeft(
                ['tp_1' => $tierPriceTable],
                $this->getTierPriceJoinCondition('tp_1', '0', 'cg.customer_group_id', '1', '0'),
                []
            )
            ->joinLeft(
                ['tp_2' => $tierPriceTable],
                $this->getTierPriceJoinCondition('tp_2', '0', 'cg.customer_group_id', '1', 's.store_id'),
                []
            )
            ->joinLeft(
                ['tp_3' => $tierPriceTable],
                $this->getTierPriceJoinCondition('tp_3', '1', '0', '1', '0'),
                []
            )
            ->joinLeft(
                ['tp_4' => $tierPriceTable],
                $this->getTierPriceJoinCondition('tp_3', '1', '0', '1', 's.store_id'),
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
     * @return $this
     */
    protected function addTaxClassColumn(
        \Magento\Framework\DB\Select $select
    )
    {
        $select->columns(['tax_class_id' => (string) $this->getTaxClassSql($select)]);
        return $this;
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
        $connection = $this->connectionProvider->getConnection();
        $currentDate = 'csd.store_date';
        return $connection->getCheckSql(
            $this->connectionProvider->getCondition([
                $specialPrice.' IS NOT NULL',
                $this->connectionProvider->getCondition([
                    $specialFrom.' IS NULL',
                    $connection->getDatePartSql($specialFrom).' <= '.$currentDate,
                ], 'OR'),
                $this->connectionProvider->getCondition([
                    $specialTo.' IS NULL',
                    $connection->getDatePartSql($specialTo).' >= '.$currentDate
                ], 'OR'),
            ], 'AND'),
            $specialPrice,
            '~0'
        );
    }
    
    /**
     * Get tier price SQL
     *
     * @param string $tableAlias
     * @param string $price
     * @return \Zend_Db_Expr
     */
    protected function getTierPriceSql(string $tableAlias, string $price): \Zend_Db_Expr
    {
        $value = $tableAlias.'.value';
        $percentageValueConverted = (string) $this->connectionProvider->getRoundSql($tableAlias.'.percentage_value * csd.rate');
        $valueConverted = (string) $this->connectionProvider->getRoundSql($value.' * csd.rate');
        return $this->connectionProvider->getConnection()->getCheckSql(
            $value.' = 0',
            (string) $this->connectionProvider->getInversePercentValueRoundSql($price, $percentageValueConverted),
            $valueConverted
        );
    }
    
    /**
     * Get total tier price SQL
     * 
     * @param string $price
     * @return \Zend_Db_Expr
     */
    protected function getTotalTierPriceSql(string $price): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $maxUnsignedBigint = '~0';
        return $connection->getCheckSql(
            $this->connectionProvider->getCondition([
                'tp_1.value_id is NULL',
                'tp_2.value_id is NULL',
                'tp_3.value_id is NULL',
                'tp_4.value_id is NULL', 
            ], 'AND'),
            'NULL',
            (string) $connection->getLeastSql([
                (string) $connection->getIfNullSql((string) $this->getTierPriceSql('tp_1', $price), $maxUnsignedBigint),
                (string) $connection->getIfNullSql((string) $this->getTierPriceSql('tp_2', $price), $maxUnsignedBigint),
                (string) $connection->getIfNullSql((string) $this->getTierPriceSql('tp_3', $price), $maxUnsignedBigint),
                (string) $connection->getIfNullSql((string) $this->getTierPriceSql('tp_4', $price), $maxUnsignedBigint),
            ])
        );
    }
    
    /**
     * Add entity identifiers condition
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param array $entityIds
     * @return $this
     */
    protected function addEntityIdsCondition(\Magento\Framework\DB\Select $select, array $entityIds = [])
    {
        if ($entityIds !== null) {
            $select->where(sprintf('e.entity_id BETWEEN %s AND %s', min($entityIds), max($entityIds)));
            $select->where('e.entity_id IN (?)', $entityIds);
        }
        return $this;
    }
    
    /**
     * Around get query
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Indexer\Dimension[] $dimensions
     * @param string $productType
     * @param array $entityIds
     * @return \Magento\Framework\DB\Select
     * @throws \LogicException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    public function aroundGetQuery(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice $subject,
        \Closure $proceed,
        array $dimensions,
        string $productType,
        array $entityIds = []
    ): \Magento\Framework\DB\Select
    {
        $this->setSubject($subject);
        $connection = $this->connectionProvider->getConnection();
        $select = $connection->select()
            ->from(['e' => $this->connectionProvider->getTable('catalog_product_entity')], ['entity_id']);
        $this->addCustomerGroupJoin($select, $dimensions);
        $this->addStoreJoins($select);
        $this->addTierPriceJoins($select);
        $this->dimensionConditionApplier->execute($select, $dimensions);
        $this->addTaxClassColumn($select);
        $this->joinAttributeProcessor->process($select, 'status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $price = (string) $this->joinAttributeProcessor->process($select, 'price');
        $specialPrice = (string) $this->getSpecialPriceSql(
            (string) $this->joinAttributeProcessor->process($select, 'special_price'),
            (string) $this->joinAttributeProcessor->process($select, 'special_from_date'),
            (string) $this->joinAttributeProcessor->process($select, 'special_to_date')
        );
        $tierPrice = (string) $this->getTotalTierPriceSql($price);
        $finalPrice = (string) $connection->getLeastSql([
            $price,
            $specialPrice,
            (string) $connection->getIfNullSql($tierPrice, '~0'),
        ]);
        $select->columns([
            'price' => $connection->getIfNullSql($price, 0),
            'final_price' => $connection->getIfNullSql($finalPrice, 0),
            'min_price' => $connection->getIfNullSql($finalPrice, 0),
            'max_price' => $connection->getIfNullSql($finalPrice, 0),
            'tier_price' => $this->connectionProvider->getSql($tierPrice),
        ]);
        $select->where('e.type_id = ?', $productType);
        $this->addEntityIdsCondition($select, $entityIds);
        $this->getSubjectPropertyValue('eventManager')->dispatch('prepare_catalog_product_index_select', [
            'select' => $select,
            'entity_field' => $this->connectionProvider->getSql('e.entity_id'),
            'website_field' => $this->connectionProvider->getSql('s.website_id'),
            'store_field' => $this->connectionProvider->getSql('s.store_id'),
        ]);
        return $select;
    }
    
}