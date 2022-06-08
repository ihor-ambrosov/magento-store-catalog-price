<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\ProductAlert\ResourceModel;

/**
 * Product price alert resource plugin
 */
class Price
{
    
    /**
     * Around delete customer
     * 
     * @param \Magento\ProductAlert\Model\ResourceModel\Price $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param int $customerId
     * @param int $storeId
     * @return \Magento\ProductAlert\Model\ResourceModel\Price
     */
    public function aroundDeleteCustomer(
        \Magento\ProductAlert\Model\ResourceModel\Price $subject,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $object,
        $customerId,
        $storeId = null
    )
    {
        $connection = $subject->getConnection();
        $where = [];
        $where[] = $connection->quoteInto('customer_id = ?', $customerId);
        if ($storeId) {
            $where[] = $connection->quoteInto('store_id = ?', $storeId);
        }
        $connection->delete($subject->getMainTable(), $where);
        return $subject;
    }
    
}