<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\Data\Framework\Collection;

/**
 * Abstract database data collection plugin
 */
class AbstractDb extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Render filters
     * 
     * @return  $this
     */
    protected function renderFilters()
    {
        if ($this->getSubjectPropertyValue('_isFiltersRendered')) {
            return $this;
        }
        $this->invokeSubjectMethod('_renderFiltersBefore');
        $select = $this->getSubjectPropertyValue('_select');
        $connection = $this->getSubjectPropertyValue('_conn');
        foreach ($this->getSubjectPropertyValue('_filters') as $filter) {
            switch ($filter['type']) {
                case 'or': 
                    $select->orWhere($connection->quoteInto($filter['field'].' = ?', $filter['value']));
                    break;
                case 'string': 
                    $select->where($filter['value']);
                    break;
                case 'public': 
                    $field = $this->invokeSubjectMethod('_getMappedField', $filter['field']);
                    $select->where($this->invokeSubjectMethod('_getConditionSql', $field, $filter['value']), null, \Magento\Framework\DB\Select::TYPE_CONDITION);
                    break;
                default: 
                    $select->where($connection->quoteInto($filter['field'].' = ?', $filter['value']));
            }
        }
        $this->setSubjectPropertyValue('_isFiltersRendered', true);
        return $this;
    }
    
    /**
     * Before load
     *
     * @return $this
     */
    protected function beforeLoad()
    {
        $this->invokeSubjectMethod('_beforeLoad');
        return $this;
    }
    
    /**
     * After load
     *
     * @return $this
     */
    protected function afterLoad()
    {
        $this->invokeSubjectMethod('_afterLoad');
        return $this;
    }
    
    /**
     * Around load with filter
     * 
     * @param \Magento\Framework\Data\Collection\AbstractDb $subject
     * @param \Closure $proceed
     * @param bool $printQuery
     * @param bool $logQuery
     * @return \Magento\Framework\Data\Collection\AbstractDb
     */
    public function aroundLoadWithFilter(
        \Magento\Framework\Data\Collection\AbstractDb $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    )
    {
        $this->setSubject($subject);
        $this->beforeLoad();
        $this->renderFilters();
        $this->invokeSubjectMethod('_renderOrders');
        $this->invokeSubjectMethod('_renderLimit');
        $subject->printLogQuery($printQuery, $logQuery);
        $data = $subject->getData();
        $subject->resetData();
        $idFieldName = $subject->getIdFieldName();
        if (is_array($data)) {
            foreach ($data as $row) {
                $item = $subject->getNewEmptyItem();
                if ($idFieldName) {
                    $item->setIdFieldName($idFieldName);
                }
                $item->addData($row);
                $this->invokeSubjectMethod('beforeAddLoadedItem', $item);
                $subject->addItem($item);
            }
        }
        $this->invokeSubjectMethod('_setIsLoaded');
        $this->afterLoad();
        return $subject;
    }
    
    /**
     * Around get data
     * 
     * @param \Magento\Framework\Data\Collection\AbstractDb $subject
     * @param \Closure $proceed
     * @return array
     */
    public function aroundGetData(
        \Magento\Framework\Data\Collection\AbstractDb $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        if ($this->getSubjectPropertyValue('_data') === null) {
            $this->renderFilters();
            $this->invokeSubjectMethod('_renderOrders');
            $this->invokeSubjectMethod('_renderLimit');
            $this->setSubjectPropertyValue('_data', $this->invokeSubjectMethod('_fetchAll', $this->getSubject()->getSelect()));
            $this->invokeSubjectMethod('_afterLoadData');
        }
        return $this->getSubjectPropertyValue('_data');
    }
    
    /**
     * Around get select count SQL
     * 
     * @param \Magento\Framework\Data\Collection\AbstractDb $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\DB\Select
     */
    public function aroundGetSelectCountSql(
        \Magento\Framework\Data\Collection\AbstractDb $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->renderFilters();
        $select = $this->getSubject()->getSelect();
        $countSelect = clone $select;
        $countSelect->reset(\Magento\Framework\DB\Select::ORDER);
        $countSelect->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $countSelect->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);
        $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS);
        $groupPart = $select->getPart(\Magento\Framework\DB\Select::GROUP);
        if (!is_array($groupPart) || !count($groupPart)) {
            $countSelect->columns(new \Zend_Db_Expr('COUNT(*)'));
            return $countSelect;
        }
        $countSelect->reset(\Magento\Framework\DB\Select::GROUP);
        $countSelect->columns(new \Zend_Db_Expr(('COUNT(DISTINCT '.implode(', ', $select->getPart(\Magento\Framework\DB\Select::GROUP)).')')));
        return $countSelect;
    }
    
}