<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\CatalogSearch\ResourceModel\Advanced;

/**
 * Advanced catalog search product collection plugin
 */
class Collection extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Collection
{
    
    /**
     * Add category filter
     * 
     * @param \Magento\Catalog\Model\Category $category
     * @return $this
     */
    protected function addCategoryFilter(\Magento\Catalog\Model\Category $category)
    {
        $subject = $this->getSubject();
        if (version_compare($this->productMetadata->getVersion(), '2.3.2', '>=')) {
            if (version_compare($this->productMetadata->getVersion(), '2.4.2', '>=')) {
                $this->invokeSubjectMethod('setAttributeFilterData', \Magento\Catalog\Model\Category::ENTITY, 'category_ids', $category->getId());
            }
            $defaultFilterStrategyApplyChecker = $this->getSubjectPropertyValue('defaultFilterStrategyApplyChecker');
            if ($defaultFilterStrategyApplyChecker->isApplicable()) {
                parent::addCategoryFilter($category);
            } else {
                if (version_compare($this->productMetadata->getVersion(), '2.4.2', '<')) {
                    $subject->addFieldToFilter('category_ids', $category->getId());
                }
                $this->productLimitationPrice();
            }
        } else {
            parent::addCategoryFilter($category);
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
        if (version_compare($this->productMetadata->getVersion(), '2.3.2', '>=')) {
            if (version_compare($this->productMetadata->getVersion(), '2.4.2', '>=')) {
                $this->invokeSubjectMethod('setAttributeFilterData', \Magento\Catalog\Model\Product::ENTITY, 'visibility', $visibility);
            }
            $defaultFilterStrategyApplyChecker = $this->getSubjectPropertyValue('defaultFilterStrategyApplyChecker');
            if ($defaultFilterStrategyApplyChecker->isApplicable()) {
                parent::setVisibility($visibility);
            } else {
                if (version_compare($this->productMetadata->getVersion(), '2.4.2', '<')) {
                    $subject->addFieldToFilter('visibility', $visibility);
                }
            }
        } else {
            parent::setVisibility($visibility);
        }
        return $this;
    }
    
}