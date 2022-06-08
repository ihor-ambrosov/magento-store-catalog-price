<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Plugin\Model\Catalog\ResourceModel\Product;

/**
 * Product collection plugin
 */
class Collection extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Get filters
     * 
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitation
     */
    protected function getFilters(): \Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitation
    {
        return $this->getSubjectPropertyValue('_productLimitationFilters');
    }
    
    /**
     * Set filters
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitation $filters
     * @return $this
     */
    protected function setFilters(
        \Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitation $filters
    )
    {
        $this->setSubjectPropertyValue('_productLimitationFilters', $filters);
        return $this;
    }
    
    /**
     * Set filter
     * 
     * @param string $filterName
     * @param mixed $filterValue
     * @return $this
     */
    protected function setFilter(string $filterName, $filterValue)
    {
        $filters = $this->getFilters();
        $filters[$filterName] = $filterValue;
        $this->setFilters($filters);
        return $this;
    }
    
    /**
     * Check if filter is set
     * 
     * @param string $filterName
     * @return bool
     */
    protected function isFilterSet(string $filterName): bool
    {
        $filters = $this->getFilters();
        return isset($filters[$filterName]) && (string) $filters[$filterName] !== '';
    }
    
    /**
     * Product limitation join website
     * 
     * @return $this
     */
    protected function productLimitationJoinWebsite()
    {
        $this->invokeSubjectMethod('_productLimitationJoinWebsite');
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
        $this->invokeSubjectMethod('_productLimitationPrice', $joinLeft);
        return $this;
    }
    
    /**
     * Product limitation join price
     * 
     * @return $this
     */
    protected function productLimitationJoinPrice()
    {
        $this->productLimitationPrice();
        return $this;
    }
    
    /**
     * Apply product limitations
     *
     * @return $this
     */
    protected function applyProductLimitations()
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $this->invokeSubjectMethod('_prepareProductLimitationFilters');
        $this->productLimitationJoinWebsite();
        $this->productLimitationJoinPrice();
        $filters = $this->getFilters();
        if (!isset($filters['category_id']) && !isset($filters['visibility'])) {
            return $this;
        }
        $conditions = [
            'cat_index.product_id = e.entity_id',
            $connection->quoteInto('cat_index.store_id=?', $filters['store_id'], 'int'),
        ];
        if (isset($filters['visibility']) && !isset($filters['store_table'])) {
            $conditions[] = $connection->quoteInto('cat_index.visibility IN(?)', $filters['visibility'], 'int');
        }
        $conditions[] = $connection->quoteInto('cat_index.category_id = ?', $filters['category_id'], 'int');
        if (isset($filters['category_is_anchor'])) {
            $conditions[] = $connection->quoteInto('cat_index.is_parent = ?', $filters['category_is_anchor']);
        }
        $joinCondition = implode(' AND ', $conditions);
        $select = $subject->getSelect();
        $fromPart = $select->getPart(\Magento\Framework\DB\Select::FROM);
        if (isset($fromPart['cat_index'])) {
            $fromPart['cat_index']['joinCondition'] = $joinCondition;
            $select->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        } else {
            $select->join(
                ['cat_index' => $this->getSubjectPropertyValue('tableMaintainer')->getMainTable($subject->getStoreId())],
                $joinCondition,
                ['cat_index_position' => 'position']
            );
        }
        $this->invokeSubjectMethod('_productLimitationJoinStore');
        $this->getSubjectPropertyValue('_eventManager')->dispatch(
            'catalog_product_collection_apply_limitations_after',
            ['collection' => $subject]
        );
        return $this;
    }
    
    /**
     * Add store filter
     * 
     * @param null|string|bool|int|\Magento\Store\Model\Store $store
     * @return $this
     */
    protected function addStoreFilter($store = null)
    {
        $subject = $this->getSubject();
        if ($store === null) {
            $store = $subject->getStoreId();
        }
        $store = $this->getSubjectPropertyValue('_storeManager')->getStore($store);
        if ($store->getId() != \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
            $subject->setStoreId($store);
            $this->setFilter('store_id', $store->getId());
            $this->applyProductLimitations();
        }
        return $this;
    }
    
    /**
     * Add website filter
     * 
     * @param null|bool|int|string|array $websites
     * @return $this
     */
    protected function addWebsiteFilter($websites = null)
    {
        if (!is_array($websites)) {
            $websites = [$this->getSubjectPropertyValue('_storeManager')->getWebsite($websites)->getId()];
        }
        $this->setFilter('website_ids', $websites);
        $this->applyProductLimitations();
        return $this;
    }
    
    /**
     * Add category filter
     * 
     * @param \Magento\Catalog\Model\Category $category
     * @return $this
     */
    protected function addCategoryFilter(\Magento\Catalog\Model\Category $category)
    {
        $subject = $this->getSubject();
        $filters = $this->getFilters();
        $filters['category_id'] = $category->getId();
        if ($category->getIsAnchor()) {
            unset($filters['category_is_anchor']);
        } else {
            $filters['category_is_anchor'] = 1;
        }
        $this->setFilters($filters);
        if ($subject->getStoreId() == \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
            $this->invokeSubjectMethod('_applyZeroStoreProductLimitations');
        } else {
            $this->applyProductLimitations();
        }
        return $this;
    }
    
    /**
     * Set visibility
     * 
     * @param array $visibility
     * @return $this
     */
    protected function setVisibility($visibility)
    {
        $subject = $this->getSubject();
        $this->setFilter('visibility', $visibility);
        if ($subject->getStoreId() == \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
            $subject->addAttributeToFilter('visibility', $visibility);
        } else {
            $this->applyProductLimitations();
        }
        return $this;
    }
    
    /**
     * Around add store filter
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @param null|string|bool|int|\Magento\Store\Model\Store $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddStoreFilter(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed,
        $store = null
    )
    {
        $this->setSubject($subject);
        $this->addStoreFilter($store);
        return $subject;
    }
    
    /**
     * Around add website filter
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @param null|bool|int|string|array $websites
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddWebsiteFilter(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed,
        $websites = null
    )
    {
        $this->setSubject($subject);
        $this->addWebsiteFilter($websites);
        return $subject;
    }
    
    /**
     * Around add category filter
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Category $category
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddCategoryFilter(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed,
        \Magento\Catalog\Model\Category $category
    )
    {
        $this->setSubject($subject);
        $this->addCategoryFilter($category);
        return $subject;
    }
    
    /**
     * Around set visibility
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param \Closure $proceed
     * @param array $visibility
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundSetVisibility(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        \Closure $proceed,
        $visibility
    )
    {
        $this->setSubject($subject);
        $this->setVisibility($visibility);
        return $subject;
    }
    
}