<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\Model\Framework;

/**
 * Abstract model plugin
 */
class AbstractModel extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Before load
     *
     * @param int $modelId
     * @param null|string $field
     * @return $this
     */
    protected function beforeLoad($modelId, $field = null)
    {
        $subject = $this->getSubject();
        $eventManager = $this->getSubjectPropertyValue('_eventManager');
        $eventPrefix = $this->getSubjectPropertyValue('_eventPrefix');
        $params = ['object' => $subject, 'field' => $field, 'value' => $modelId];
        $eventManager->dispatch('model_load_before', $params);
        $eventManager->dispatch($eventPrefix.'_load_before', array_merge($params, $this->invokeSubjectMethod('_getEventData')));
        return $this;
    }
    
    /**
     * After load
     *
     * @return $this
     */
    protected function afterLoad()
    {
        $subject = $this->getSubject();
        $eventManager = $this->getSubjectPropertyValue('_eventManager');
        $eventPrefix = $this->getSubjectPropertyValue('_eventPrefix');
        $eventManager->dispatch('model_load_after', ['object' => $subject]);
        $eventManager->dispatch($eventPrefix.'_load_after', $this->invokeSubjectMethod('_getEventData'));
        return $this;
    }
    
    /**
     * Around before load
     * 
     * @param \Magento\Framework\Model\AbstractModel $subject
     * @param \Closure $proceed
     * @param string $identifier
     * @param string|null $field
     * @return \Magento\Framework\Model\AbstractModel
     */
    public function aroundBeforeLoad(
        \Magento\Framework\Model\AbstractModel $subject,
        \Closure $proceed,
        $identifier,
        $field = null
    )
    {
        $this->setSubject($subject);
        $this->beforeLoad($identifier, $field);
        return $subject;
    }
    
    /**
     * Around after load
     * 
     * @param \Magento\Framework\Model\AbstractModel $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Model\AbstractModel
     */
    public function aroundAfterLoad(
        \Magento\Framework\Model\AbstractModel $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->afterLoad();
        $this->invokeSubjectMethod('updateStoredData');
        return $subject;
    }
    
}