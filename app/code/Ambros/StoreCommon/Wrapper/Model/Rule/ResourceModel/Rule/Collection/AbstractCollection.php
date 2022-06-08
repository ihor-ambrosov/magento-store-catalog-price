<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Wrapper\Model\Rule\ResourceModel\Rule\Collection;

/**
 * Abstract rule collection wrapper
 */
class AbstractCollection extends \Ambros\Common\DataObject\Wrapper
{
    
    /**
     * Add stores to result
     *
     * @param bool|null $flag
     * @return $this
     */
    public function addStoresToResult($flag = null)
    {
        $this->getObject()->setFlag('add_stores_to_result', $flag === null ? true : $flag);
        return $this;
    }
    
    /**
     * Add store filter
     *
     * @param int|int[]|\Magento\Store\Model\Store $storeId
     * @return $this
     */
    public function addStoreFilter($storeId)
    {
        $object = $this->getObject();
        $connection = $object->getConnection();
        if ($object->getFlag('is_store_table_joined')) {
            return $this;
        }
        $storeIds = is_array($storeId) ? $storeId : [$storeId];
        $entityInfo = $this->invokeMethod('_getAssociatedEntityInfo', 'store');
        $object->setFlag('is_store_table_joined', true);
        foreach ($storeIds as $index => $store) {
            if ($store instanceof \Magento\Store\Model\Store) {
                $storeIds[$index] = $store->getId();
            }
            $storeIds[$index] = (int) $storeIds[$index];
        }
        $ruleIdField = $entityInfo['rule_id_field'];
        $entityIdField = $entityInfo['entity_id_field'];
        $object->getSelect()->join(
            [
                'store' => $connection->select()
                    ->from($object->getTable($entityInfo['associations_table']), [$ruleIdField])
                    ->distinct(true)
                    ->where($connection->quoteInto($entityIdField.' IN (?)', $storeIds))
            ],
            'main_table.'.$ruleIdField.' = store.'.$ruleIdField,
            []
        );
        return $this;
    }
    
}