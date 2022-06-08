<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Review\ResourceModel\Review\Product;

/**
 * Review product collection plugin
 */
class Collection extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Collection
{
    
    /**
     * Add store filter
     * 
     * @param int|int[] $storeId
     * @return $this
     */
    protected function addStoreFilter($storeId = null)
    {
        $subject = $this->getSubject();
        if (null === $storeId) {
            $storeId = $subject->getStoreId();
        }
        parent::addStoreFilter($storeId);
        if (!is_array($storeId)) {
            $storeId = [$storeId];
        }
        $storesIds = $this->getSubjectPropertyValue('_storesIds');
        if (!empty($storesIds)) {
            $storesIds = array_intersect($storesIds, $storeId);
        } else {
            $storesIds = $storeId;
        }
        $this->setSubjectPropertyValue('_storesIds', $storesIds);
        return $this;
    }
    
}