<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Plugin\Model\Rule\ResourceModel\Rule\Collection;

/**
 * Abstract rule collection plugin
 */
class AbstractCollection extends \Ambros\Common\Plugin\Data\Framework\Collection\AbstractDb
{
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Wrapper\Model\Rule\ResourceModel\Rule\Collection\AbstractCollectionFactory $wrapperFactory
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Wrapper\Model\Rule\ResourceModel\Rule\Collection\AbstractCollectionFactory $wrapperFactory
    )
    {
        parent::__construct($wrapperFactory);
    }
    
    /**
     * After load
     *
     * @return $this
     */
    protected function afterLoad()
    {
        $subject = $this->getSubject();
        $this->invokeSubjectParentMethod(\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection::class, '_afterLoad');
        $items = $this->getSubjectPropertyValue('_items');
        if ($subject->getFlag('add_stores_to_result') && $items) {
            foreach ($items as $item) {
                $item->afterLoad();
            }
        }
        return $this;
    }
    
    /**
     * Around add websites to result
     * 
     * @param \Magento\Rule\Model\ResourceModel\Rule\Collection\AbstractCollection $subject
     * @param \Closure $proceed
     * @param bool|null $flag
     * @return \Magento\Rule\Model\ResourceModel\Rule\Collection\AbstractCollection
     */
    public function aroundAddWebsitesToResult(
        \Magento\Rule\Model\ResourceModel\Rule\Collection\AbstractCollection $subject,
        \Closure $proceed,
        $flag = null
    )
    {
        $this->setSubject($subject);
        $this->getSubjectWrapper()->addStoresToResult($flag);
        return $subject;
    }
    
    /**
     * Around add field to filter
     * 
     * @param \Magento\Rule\Model\ResourceModel\Rule\Collection\AbstractCollection $subject
     * @param \Closure $proceed
     * @param string $field
     * @param null|string|array $condition
     * @return \Magento\Rule\Model\ResourceModel\Rule\Collection\AbstractCollection
     */
    public function aroundAddFieldToFilter(
        \Magento\Rule\Model\ResourceModel\Rule\Collection\AbstractCollection $subject,
        \Closure $proceed,
        $field,
        $condition = null
    )
    {
        $this->setSubject($subject);
        if ($field == 'store_ids') {
            $this->getSubjectWrapper()->addStoreFilter($condition);
            return $subject;
        }
        $this->invokeSubjectParentMethod(\Magento\Framework\Data\Collection\AbstractDb::class, 'addFieldToFilter', $field, $condition);
        return $subject;
    }
    
}