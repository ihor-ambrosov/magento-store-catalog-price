<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Wrapper\Model\ProductAlert\ResourceModel\Price;

/**
 * Product price alert collection wrapper
 */
class Collection extends \Ambros\Common\DataObject\Wrapper
{
    
    /**
     * Add store filter
     *
     * @param mixed $store
     * @return $this
     */
    public function addStoreFilter($store)
    {
        $object = $this->getObject();
        $connection = $object->getConnection();
        if (is_array($store)) {
            $condition = $connection->quoteInto('store_id IN (?)', $store);
        } elseif ($store instanceof \Magento\Store\Model\Website) {
            $condition = $connection->quoteInto('store_id = ?', $store->getId());
        } else {
            $condition = $connection->quoteInto('store_id = ?', $store);
        }
        $object->addFilter('store_id', $condition, 'string');
        return $this;
    }
    
}