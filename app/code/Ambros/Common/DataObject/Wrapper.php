<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\DataObject;

/**
 * Object wrapper
 */
class Wrapper
{
    
    /**
     * Object reflection factory
     * 
     * @var \Ambros\Common\DataObject\ReflectionFactory
     */
    protected $objectReflectionFactory;
    
    /**
     * Object
     * 
     * @var object
     */
    protected $object;
    
    /**
     * Object reflection
     * 
     * @var \Ambros\Common\DataObject\Reflection
     */
    protected $objectReflection;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\ReflectionFactory $objectReflectionFactory
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\ReflectionFactory $objectReflectionFactory
    )
    {
        $this->objectReflectionFactory = $objectReflectionFactory;
    }
    
    /**
     * Get object
     * 
     * @return object
     */
    public function getObject()
    {
        if (empty($this->object)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Object is undefined for %1 wrapper.', get_class($this)));
        }
        return $this->object;
    }
    
    /**
     * Set object
     * 
     * @param object $object
     * @return $this
     */
    public function setObject($object)
    {
        $this->object = $object;
        $this->object->wrapper = $this;
        return $this;
    }
    
    /**
     * Get object reflection
     * 
     * @return \Ambros\Common\DataObject\Reflection
     */
    protected function getObjectReflection(): \Ambros\Common\DataObject\Reflection
    {
        if ($this->objectReflection !== null) {
            return $this->objectReflection;
        }
        return $this->objectReflection = $this->objectReflectionFactory->create(['class' => get_class($this->getObject())]);
    }
    
    /**
     * Invoke method
     * 
     * @param string $methodName
     * @param mixed ...$arguments
     * @return mixed
     */
    public function invokeMethod(string $methodName, ...$arguments)
    {
        return $this->getObjectReflection()->invokeMethod($this->getObject(), $methodName, ...$arguments);
    }
    
    /**
     * Invoke parent method
     * 
     * @param string $className
     * @param string $methodName
     * @param mixed ...$arguments
     * @return mixed
     */
    public function invokeParentMethod(string $className, string $methodName, ...$arguments)
    {
        return $this->getObjectReflection()->invokeParentMethod($this->getObject(), $className, $methodName, ...$arguments);
    }
    
    /**
     * Get property value
     * 
     * @param string $propertyName
     * @return mixed
     */
    public function getPropertyValue(string $propertyName)
    {
        return $this->getObjectReflection()->getPropertyValue($this->getObject(), $propertyName);
    }
    
    /**
     * Set property value
     * 
     * @param string $propertyName
     * @param mixed $propertyValue
     * @return $this
     */
    public function setPropertyValue(string $propertyName, $propertyValue)
    {
        $this->getObjectReflection()->setPropertyValue($this->getObject(), $propertyName, $propertyValue);
        return $this;
    }
    
}