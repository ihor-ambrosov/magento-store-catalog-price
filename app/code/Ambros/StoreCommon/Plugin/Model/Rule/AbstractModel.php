<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Plugin\Model\Rule;

/**
 * Abstract rule plugin
 */
class AbstractModel extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Around before save
     *
     * @param \Magento\Rule\Model\AbstractModel $subject
     * @param \Closure $proceed
     * @return \Magento\Rule\Model\AbstractModel
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundBeforeSave(
        \Magento\Rule\Model\AbstractModel $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        if ($subject->hasDiscountAmount()) {
            if ((int) $subject->getDiscountAmount() < 0) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Please choose a valid discount amount.'));
            }
        }
        $serializer = $this->getSubjectPropertyValue('serializer');
        $conditions = $subject->getConditions();
        if ($conditions) {
            $subject->setConditionsSerialized($serializer->serialize($conditions->asArray()));
            $this->setSubjectPropertyValue('_conditions', null);
        }
        $actions = $subject->getActions();
        if ($actions) {
            $subject->setActionsSerialized($serializer->serialize($actions->asArray()));
            $this->setSubjectPropertyValue('_actions', null);
        }
        if ($subject->hasStoreIds()) {
            $storeIds = $subject->getStoreIds();
            if (is_string($storeIds) && !empty($storeIds)) {
                $subject->setStoreIds(explode(',', $storeIds));
            }
        }
        if ($subject->hasCustomerGroupIds()) {
            $customerGroupIds = $subject->getCustomerGroupIds();
            if (is_string($customerGroupIds) && !empty($customerGroupIds)) {
                $subject->setCustomerGroupIds(explode(',', $customerGroupIds));
            }
        }
        $this->invokeSubjectParentMethod(\Magento\Framework\Model\AbstractModel::class, 'beforeSave');
        return $subject;
    }
    
    /**
     * Around validate data
     * 
     * @param \Magento\Rule\Model\AbstractModel $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DataObject $dataObject
     * @return bool|string[]
     */
    public function aroundValidateData(
        \Magento\Rule\Model\AbstractModel $subject,
        \Closure $proceed,
        \Magento\Framework\DataObject $dataObject
    )
    {
        $result = [];
        $fromDate = $toDate = null;
        if ($dataObject->hasFromDate() && $dataObject->hasToDate()) {
            $fromDate = $dataObject->getFromDate();
            $toDate = $dataObject->getToDate();
        }
        if ($fromDate && $toDate) {
            $fromDate = new \DateTime($fromDate);
            $toDate = new \DateTime($toDate);
            if ($fromDate > $toDate) {
                $result[] = __('End Date must follow Start Date.');
            }
        }
        if ($dataObject->hasStoreIds()) {
            $storeIds = $dataObject->getStoreIds();
            if (empty($storeIds)) {
                $result[] = __('Please specify a store.');
            }
        }
        if ($dataObject->hasCustomerGroupIds()) {
            $customerGroupIds = $dataObject->getCustomerGroupIds();
            if (empty($customerGroupIds)) {
                $result[] = __('Please specify Customer Groups.');
            }
        }
        return !empty($result) ? $result : true;
    }
    
    /**
     * Get store IDs
     *
     * @return array
     */
    protected function getStoreIds()
    {
        $subject = $this->getSubject();
        if (!$subject->hasStoreIds()) {
            $subject->setData('store_ids', (array) $subject->getResource()->getAssociatedEntityIds($subject->getId(), 'store'));
        }
        return $subject->getData('store_ids');
    }
    
    /**
     * Around call
     *
     * @param \Magento\Rule\Model\AbstractModel $subject
     * @param \Closure $proceed
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function around__call(
        \Magento\Rule\Model\AbstractModel $subject,
        \Closure $proceed,
        $method,
        $args
    )
    {
        $this->setSubject($subject);
        if ($method === 'getStoreIds') {
            return $this->getStoreIds();
        }
        return $proceed($method, $args);
    }
    
}