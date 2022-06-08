<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\ProductAlert\ResourceModel\Price\Customer;

/**
 * Product price alert customer collection plugin
 */
class Collection extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Around join
     * 
     * @param \Magento\ProductAlert\Model\ResourceModel\Price\Customer\Collection $subject
     * @param \Closure $proceed
     * @param int $productId
     * @param int $storeId
     * @return \Magento\ProductAlert\Model\ResourceModel\Price\Customer\Collection
     */
    public function aroundJoin(
        \Magento\ProductAlert\Model\ResourceModel\Price\Customer\Collection $subject,
        \Closure $proceed,
        $productId,
        $storeId
    )
    {
        $this->setSubject($subject);
        $select = $subject->getSelect();
        $select->join(
            ['alert' => $subject->getTable('product_alert_price')],
            'e.entity_id = alert.customer_id',
            ['alert_price_id', 'price', 'add_date', 'last_send_date', 'send_count', 'status']
        );
        $select->where('alert.product_id = ?', $productId);
        if ($storeId) {
            $select->where('alert.store_id = ?', $storeId);
        }
        $this->invokeSubjectMethod('_setIdFieldName', 'alert_price_id');
        $subject->addAttributeToSelect('*');
        return $subject;
    }
    
}