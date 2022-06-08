<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Attribute\Backend;

/**
 * Product tier price attribute back-end resource plugin
 */
class Tierprice extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Before get main table
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $subject
     * @return void
     */
    public function beforeGetMainTable(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $subject
    )
    {
        $this->setSubject($subject);
        $this->invokeSubjectMethod('_setMainTable', 'ambros_store__catalog_product_entity_tier_price', 'value_id');
    }
    
    /**
     * Around get select
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $subject
     * @param \Closure $proceed
     * @param int|null $storeId
     * @return \Magento\Framework\DB\Select
     */
    public function aroundGetSelect(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $subject,
        \Closure $proceed,
        $storeId = null
    )
    {
        $this->setSubject($subject);
        $connection = $subject->getConnection();
        $select = $connection->select()
            ->from($subject->getMainTable(), $this->invokeSubjectMethod('_loadPriceDataColumns', [
                'price_id' => $subject->getIdFieldName(),
                'store_id' => 'store_id',
                'all_groups' => 'all_groups',
                'cust_group' => 'customer_group_id',
                'price' => 'value',
            ]));
        if ($storeId !== null) {
            if ($storeId == '0') {
                $select->where('store_id = ?', $storeId);
            } else {
                $select->where('store_id IN (?)', [0, $storeId]);
            }
        }
        return $select;
    }
    
    /**
     * Around delete price data
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $subject
     * @param \Closure $proceed
     * @param int $productId
     * @param int $storeId
     * @param int $priceId
     * @return int
     */
    public function aroundDeletePriceData(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $subject,
        \Closure $proceed,
        $productId,
        $storeId = null,
        $priceId = null
    )
    {
        $this->setSubject($subject);
        $connection = $subject->getConnection();
        $conditions = [$connection->quoteInto('entity_id = ?', $productId)];
        if ($storeId !== null) {
            $conditions[] = $connection->quoteInto('store_id = ?', $storeId);
        }
        if ($priceId !== null) {
            $conditions[] = $connection->quoteInto($subject->getIdFieldName().' = ?', $priceId);
        }
        return $connection->delete($subject->getMainTable(), implode(' AND ', $conditions));
    }
    
}