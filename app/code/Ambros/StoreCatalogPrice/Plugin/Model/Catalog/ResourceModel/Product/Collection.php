<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product;

/**
 * Product collection plugin
 */
class Collection extends \Ambros\StoreCommon\Plugin\Model\Catalog\ResourceModel\Product\Collection
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Price index table resolver
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver
     */
    protected $priceIndexTableResolver;
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Module manager
     * 
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\Framework\Module\Manager $moduleManager
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceScope = $priceScope;
        $this->priceIndexTableResolver = $priceIndexTableResolver;
        $this->productMetadata = $productMetadata;
        $this->moduleManager = $moduleManager;
    }
    
    /**
     * Product limitation join website
     *
     * @return $this
     */
    protected function productLimitationJoinWebsite()
    {
        parent::productLimitationJoinWebsite();
        if (!$this->moduleManager->isEnabled('Ambros_StoreCatalog')) {
            return $this;
        }
        $subject = $this->getSubject();
        $filters = $this->getFilters();
        $connection = $subject->getConnection();
        $select = $subject->getSelect();
        $joinStore = false;
        $conditions = ['product_store.product_id = e.entity_id'];
        if (isset($filters['store_id']) && (!isset($filters['visibility']) && !isset($filters['category_id'])) && !$subject->isEnabledFlat()) {
            $joinStore = true;
            $conditions[] = $connection->quoteInto('product_store.store_id = ?', $filters['store_id'], 'int');
        }
        $fromPart = $select->getPart(\Magento\Framework\DB\Select::FROM);
        if (isset($fromPart['product_store'])) {
            if (!$joinStore) {
                unset($fromPart['product_store']);
            } else {
                $fromPart['product_store']['joinCondition'] = implode(' AND ', $conditions);
            }
            $select->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        } elseif ($joinStore) {
            $select->join(
                ['product_store' => $subject->getTable('ambros_store__catalog_product_store')],
                implode(' AND ', $conditions),
                []
            );
        }
        
        return $this;
    }
    
    /**
     * Product limitation price
     * 
     * @param bool $joinLeft
     * @return $this
     */
    protected function productLimitationPrice($joinLeft = false)
    {
        $subject = $this->getSubject();
        $filters = $this->getFilters();
        if (!$filters->isUsingPriceIndex() || !$this->isFilterSet('price_store_id') || !$this->isFilterSet('customer_group_id')) {
            return $this;
        }
        $subject->removeAttributeToSelect('price');
        $connection = $subject->getConnection();
        $select = $subject->getSelect();
        $storeId = $this->priceScope->getStoreId((int) $filters['price_store_id']);
        $customerGroupId = (int) $filters['customer_group_id'];
        $joinConditionsArray = [
            'price_index.entity_id = e.entity_id',
            $connection->quoteInto('price_index.customer_group_id = ?', $customerGroupId),
        ];
        if ($storeId) {
            $joinConditionsArray[] = $connection->quoteInto('price_index.store_id = ?', $storeId);
        }
        $joinConditions = implode(' AND ', $joinConditionsArray);
        $fromPart = $select->getPart(\Magento\Framework\DB\Select::FROM);
        if (!isset($fromPart['price_index'])) {
            $columns = [
                'price',
                'tax_class_id',
                'final_price',
                'minimal_price' => $connection->getCheckSql(
                    'price_index.tier_price IS NOT NULL',
                    (string) $connection->getLeastSql(['price_index.min_price', 'price_index.tier_price']),
                    'price_index.min_price'
                ),
                'min_price',
                'max_price',
                'tier_price',
            ];
            $tableName = ['price_index' => $this->priceIndexTableResolver->resolve($storeId, $customerGroupId)];
            if ($joinLeft) {
                $select->joinLeft($tableName, $joinConditions, $columns);
            } else {
                $select->join($tableName, $joinConditions, $columns);
            }
            foreach ($this->getSubjectPropertyValue('_priceDataFieldFilters') as $filterData) {
                $select->where(sprintf(...$filterData));
            }
        } else {
            $fromPart['price_index']['joinCondition'] = $joinConditions;
            $select->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        }
        $this->getSubjectPropertyValue('_resourceHelper')->prepareColumnsList($select);
        return $this;
    }
    
    /**
     * Add price data
     * 
     * @param int $customerGroupId
     * @param int $storeId
     * @return $this
     */
    protected function addPriceData($customerGroupId = null, $storeId = null)
    {
        $subject = $this->getSubject();
        $filters = $this->getFilters();
        $filters->setUsePriceIndex(true);
        if (!isset($filters['customer_group_id']) && $customerGroupId === null) {
            $customerGroupId = $this->getSubjectPropertyValue('_customerSession')->getCustomerGroupId();
        }
        if (!isset($filters['price_store_id']) && $storeId === null) {
            $storeId = $this->getSubjectPropertyValue('_storeManager')->getStore($subject->getStoreId())->getId();
        }
        if ($customerGroupId !== null) {
            $filters['customer_group_id'] = $customerGroupId;
        }
        if ($storeId !== null) {
            $filters['price_store_id'] = $storeId;
        }
        $this->setFilters($filters);
        $this->applyProductLimitations();
        return $this;
    }
    
    /**
     * Around add price data
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @param int $customerGroupId
     * @param int $storeId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddPriceData(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed,
        $customerGroupId = null,
        $storeId = null
    )
    {
        $this->setSubject($subject);
        $this->addPriceData($customerGroupId, $storeId);
        return $subject;
    }
    
    /**
     * Around apply front-end price limitations
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundApplyFrontendPriceLimitations(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $filters = $this->getFilters();
        $filters->setUsePriceIndex(true);
        if (!isset($filters['customer_group_id'])) {
            $customerGroupId = $this->getSubjectPropertyValue('_customerSession')->getCustomerGroupId();
            $filters['customer_group_id'] = $customerGroupId;
        }
        if (!isset($filters['price_store_id'])) {
            $filters['price_store_id'] = $this->getSubjectPropertyValue('_storeManager')->getStore($subject->getStoreId())->getId();
        }
        $this->setFilters($filters);
        $this->applyProductLimitations();
        return $subject;
    }
    
    /**
     * Get tier price select
     *
     * @param array $productIds
     * @return \Magento\Framework\DB\Select
     */
    protected function getTierPriceSelect(array $productIds)
    {
        $subject = $this->getSubject();
        $attribute = $subject->getAttribute(\Magento\Catalog\Api\Data\ProductAttributeInterface::CODE_TIER_PRICE);
        if (!$this->priceScope->isGlobal() && $subject->getStoreId() !== null) {
            $storeId = $this->priceScope->getStoreIdByAttribute($attribute, $subject->getStoreId());
        } else {
            $storeId = 0;
        }
        return $attribute->getBackend()->getResource()->getSelect($storeId)
            ->columns(['product_id' => 'entity_id'])
            ->where('entity_id IN (?)', $productIds)
            ->order('qty');
    }
    
    /**
     * Fill tier price data
     * 
     * @param \Magento\Framework\DB\Select $select
     * @return $this
     */
    protected function fillTierPriceData(\Magento\Framework\DB\Select $select)
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $pricesData = [];
        foreach ($connection->fetchAll($select) as $priceData) {
            $pricesData[$priceData['product_id']][] = $priceData;
        }
        $backend = $subject->getAttribute(\Magento\Catalog\Api\Data\ProductAttributeInterface::CODE_TIER_PRICE)->getBackend();
        foreach ($subject->getItems() as $item) {
            $productId = $item->getData('entity_id');
            $backend->setPriceData($item, isset($pricesData[$productId]) ? $pricesData[$productId] : []);
        }
        return $this;
    }
    
    /**
     * Around add tier price data
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddTierPriceData(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        if ($subject->getFlag('tier_price_added')) {
            return $subject;
        }
        $productIds = [];
        foreach ($subject->getItems() as $item) {
            $productIds[] = $item->getData('entity_id');
        }
        if (!$productIds) {
            return $subject;
        }
        $this->fillTierPriceData($this->getTierPriceSelect($productIds));
        $subject->setFlag('tier_price_added', true);
        return $subject;
    }
    
    /**
     * Around add store filter
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @param int $customerGroupId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddTierPriceDataByGroupId(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed,
        $customerGroupId
    )
    {
        $this->setSubject($subject);
        if ($subject->getFlag('tier_price_added')) {
            return $subject;
        }
        $productIds = [];
        foreach ($subject->getItems() as $item) {
            $productIds[] = $item->getData('entity_id');
        }
        if (!$productIds) {
            return $subject;
        }
        $select = $this->getTierPriceSelect($productIds);
        $select->where('(customer_group_id = ? AND all_groups = 0) OR all_groups = 1', $customerGroupId);
        $this->fillTierPriceData($select);
        $subject->setFlag('tier_price_added', true);
        return $subject;
    }
    
    /**
     * Before get table
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param string $table
     * @return string|null
     */
    public function beforeGetTable(\Magento\Catalog\Model\ResourceModel\Product\Collection $subject, $table)
    {
        if ($table === 'catalog_product_entity_tier_price') {
            return 'ambros_store__catalog_product_entity_tier_price';
        } else {
            return null;
        }
    }
    
}