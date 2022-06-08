<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin;

/**
 * Inheritor plugin
 */
class InheritorPlugin extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Parent
     * 
     * @var object
     */
    protected $parent;
    
    /**
     * Parent wrapper
     * 
     * @var \Ambros\Common\DataObject\Wrapper 
     */
    protected $parentWrapper;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
    )
    {
        parent::__construct($wrapperFactory);
    }
    
    /**
     * Set parent
     * 
     * @param object $parent
     * @return $this
     */
    protected function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }
    
    /**
     * Get parent
     * 
     * @return object
     */
    protected function getParent()
    {
        if (empty($this->parent)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Parent is undefined for %1 plugin.', get_class($this)));
        }
        return $this->parent;
    }
    
    /**
     * Get parent wrapper
     * 
     * @return \Ambros\Common\DataObject\Wrapper
     */
    protected function getParentWrapper(): \Ambros\Common\DataObject\Wrapper
    {
        $parent = $this->getParent();
        return $parent->wrapper ?? $this->wrapperFactory->create()->setObject($parent);
    }
    
    /**
     * Invoke parent method
     * 
     * @param string $methodName
     * @param mixed ...$arguments
     * @return mixed
     */
    protected function invokeParentMethod(string $methodName, ...$arguments)
    {
        return $this->getParentWrapper()->invokeMethod($methodName, ...$arguments);
    }
    
    /**
     * Get parent property value
     * 
     * @param string $propertyName
     * @return mixed
     */
    protected function getParentPropertyValue(string $propertyName)
    {
        return $this->getParentWrapper()->getPropertyValue($propertyName);
    }
    
    /**
     * Set parent property value
     * 
     * @param string $propertyName
     * @param mixed $propertyValue
     * @return $this
     */
    protected function setParentPropertyValue(string $propertyName, $propertyValue)
    {
        $this->getParentWrapper()->setPropertyValue($propertyName, $propertyValue);
        return $this;
    }
    
}