<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\CatalogSearch\ResourceModel\Fulltext;

/**
 * Full text catalog search product collection plugin
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
            $defaultFilterStrategyApplyChecker = $this->getSubjectPropertyValue('defaultFilterStrategyApplyChecker');
            $subject->addFieldToFilter('category_ids', $category->getId());
            if ($defaultFilterStrategyApplyChecker->isApplicable()) {
                parent::addCategoryFilter($category);
            } else {
                $subject->setFlag('has_category_filter', true);
                $this->productLimitationPrice();
            }
        } else {
            $subject->addFieldToFilter('category_ids', $category->getId());
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
            $defaultFilterStrategyApplyChecker = $this->getSubjectPropertyValue('defaultFilterStrategyApplyChecker');
            $subject->addFieldToFilter('visibility', $visibility);
            if ($defaultFilterStrategyApplyChecker->isApplicable()) {
                parent::setVisibility($visibility);
            }
        } else {
            $subject->addFieldToFilter('visibility', $visibility);
            parent::setVisibility($visibility);
        }
        return $this;
    }
    
}