<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Layer\Filter;

/**
 * Product price layer filter resource plugin
 */
class Price extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price index table resolver
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver
     */
    protected $priceIndexTableResolver;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope 
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceIndexTableResolver = $priceIndexTableResolver;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Get select
     * 
     * @return \Magento\Framework\DB\Select
     */
    protected function getSelect()
    {
        $collection = $this->getSubjectPropertyValue('layer')->getProductCollection();
        $customerGroupId = $this->getSubjectPropertyValue('session')->getCustomerGroupId();
        $storeId = $this->getSubjectPropertyValue('storeManager')->getStore()->getId();
        $collection->addPriceData($customerGroupId, $storeId);
        if ($collection->getCatalogPreparedSelect() !== null) {
            $select = clone $collection->getCatalogPreparedSelect();
        } else {
            $select = clone $collection->getSelect();
        }
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->reset(\Magento\Framework\DB\Select::ORDER);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);
        $fromPart = $select->getPart(\Magento\Framework\DB\Select::FROM);
        $mainTableAlias = \Magento\Catalog\Model\ResourceModel\Product\Collection::MAIN_TABLE_ALIAS;
        $indexTableAlias = \Magento\Catalog\Model\ResourceModel\Product\Collection::INDEX_TABLE_ALIAS;
        if (!isset($fromPart[$indexTableAlias]) || !isset($fromPart[$mainTableAlias])) {
            return $select;
        }
        $indexFromPart = $fromPart[$indexTableAlias];
        $indexFromConditions = explode('AND', $indexFromPart['joinCondition']);
        $indexFromPart['joinType'] = \Magento\Framework\DB\Select::FROM;
        $indexFromPart['joinCondition'] = null;
        $fromPart[$mainTableAlias] = $indexFromPart;
        unset($fromPart[$indexTableAlias]);
        foreach ($fromPart as $key => $fromJoinItem) {
            $fromPart[$key]['joinCondition'] = $this->invokeSubjectMethod('_replaceTableAlias', $fromJoinItem['joinCondition']);
        }
        $select->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        $wherePart = $select->getPart(\Magento\Framework\DB\Select::WHERE);
        foreach ($wherePart as $key => $wherePartItem) {
            $wherePart[$key] = $this->invokeSubjectMethod('_replaceTableAlias', $wherePartItem);
        }
        $select->setPart(\Magento\Framework\DB\Select::WHERE, $wherePart);
        $excludeJoinPart = $mainTableAlias.'.entity_id';
        foreach ($indexFromConditions as $condition) {
            if (strpos($condition, $excludeJoinPart) !== false) {
                continue;
            }
            $select->where($this->invokeSubjectMethod('_replaceTableAlias', $condition));
        }
        $select->where($this->invokeSubjectMethod('_getPriceExpression', $select).' IS NOT NULL');
        return $select;
    }
    
    /**
     * Around get count
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject
     * @param \Closure $proceed
     * @param int $range
     * @return array
     */
    public function aroundGetCount(
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject,
        \Closure $proceed,
        $range
    )
    {
        $this->setSubject($subject);
        $select = $this->getSelect();
        $priceExpression = $this->invokeSubjectMethod('_getFullPriceExpression', $select);
        $range = (float) $range;
        if ($range == 0) {
            $range = 1;
        }
        $countExpr = new \Zend_Db_Expr('COUNT(*)');
        $rangeExpr = new \Zend_Db_Expr("FLOOR(({$priceExpression}) / {$range}) + 1");
        $select->columns(['range' => $rangeExpr, 'count' => $countExpr]);
        $select->group($rangeExpr)->order(new \Zend_Db_Expr("({$rangeExpr}) ASC"));
        return $subject->getConnection()->fetchPairs($select);
    }
    
    /**
     * Around load previous prices
     * 
     * @param float $price
     * @param int $index
     * @param null|int $lowerPrice
     * @return array|false
     */
    public function aroundLoadPreviousPrices(
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject,
        \Closure $proceed,
        $price,
        $index,
        $lowerPrice = null
    )
    {
        $this->setSubject($subject);
        $select = $this->getSelect();
        $priceExpression = $this->invokeSubjectMethod('_getPriceExpression', $select);
        $select->columns('COUNT(*)')->where("{$priceExpression} < ".$this->invokeSubjectMethod('_getComparingValue', $price));
        if ($lowerPrice !== null) {
            $select->where("{$priceExpression} >= ".$this->invokeSubjectMethod('_getComparingValue', $lowerPrice));
        }
        $offset = $subject->getConnection()->fetchOne($select);
        if (!$offset) {
            return false;
        }
        return $subject->loadPrices($index - $offset + 1, $offset - 1, $lowerPrice);
    }
    
    /**
     * Around load prices
     * 
     * @param int $limit
     * @param null|int $offset
     * @param null|int $lowerPrice
     * @param null|int $upperPrice
     * @return array
     */
    public function aroundLoadPrices(
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject,
        \Closure $proceed,
        $limit,
        $offset = null,
        $lowerPrice = null,
        $upperPrice = null
    )
    {
        $this->setSubject($subject);
        $select = $this->getSelect();
        $priceExpression = $this->invokeSubjectMethod('_getPriceExpression', $select);
        $select->columns(['min_price_expr' => $this->invokeSubjectMethod('_getFullPriceExpression', $select)]);
        if ($lowerPrice !== null) {
            $select->where("{$priceExpression} >= ".$this->invokeSubjectMethod('_getComparingValue', $lowerPrice));
        }
        if ($upperPrice !== null) {
            $select->where("{$priceExpression} < ".$this->invokeSubjectMethod('_getComparingValue', $upperPrice));
        }
        $select->order("{$priceExpression} ASC")->limit($limit, $offset);
        return $subject->getConnection()->fetchCol($select);
    }
    
    /**
     * Around load next prices
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject
     * @param \Closure $proceed
     * @param float $price
     * @param int $rightIndex
     * @param null|int $upperPrice
     * @return array|false
     */
    public function aroundLoadNextPrices(
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject,
        \Closure $proceed,
        $price,
        $rightIndex,
        $upperPrice = null
    )
    {
        $this->setSubject($subject);
        $select = $this->getSelect();
        $pricesSelect = clone $select;
        $priceExpression = $this->invokeSubjectMethod('_getPriceExpression', $pricesSelect);
        $select->columns('COUNT(*)')
            ->where("{$priceExpression} > ".$this->invokeSubjectMethod('_getComparingValue', $price, false));
        if ($upperPrice !== null) {
            $select->where("{$priceExpression} < ".$this->invokeSubjectMethod('_getComparingValue', $upperPrice));
        }
        $connection = $this->getConnection();
        $offset = $connection->fetchOne($select);
        if (!$offset) {
            return false;
        }
        $pricesSelect->columns(['min_price_expr' => $this->invokeSubjectMethod('_getFullPriceExpression', $pricesSelect)])
            ->where("{$priceExpression} >= ".$this->invokeSubjectMethod('_getComparingValue', $price));
        if ($upperPrice !== null) {
            $pricesSelect->where("{$priceExpression} < ".$this->invokeSubjectMethod('_getComparingValue', $upperPrice));
        }
        $pricesSelect->order("{$priceExpression} DESC")->limit($rightIndex - $offset + 1, $offset - 1);
        return array_reverse($connection->fetchCol($pricesSelect));
    }
    
    /**
     * Around get main table
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject
     * @param \Closure $proceed
     * @throws \Magento\Framework\Exception\LocalizedException\LocalizedException
     * @return string
     */
    public function aroundGetMainTable(
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        return $this->priceIndexTableResolver->resolve(
            $this->priceScope->getStoreId(
                (int) $this->getSubjectPropertyValue('storeManager')->getStore(
                    $this->getSubjectPropertyValue('httpContext')->getValue(\Magento\Store\Model\StoreManagerInterface::CONTEXT_STORE)
                )->getId()
            )
        );
    }
    
}