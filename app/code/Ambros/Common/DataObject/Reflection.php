<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\DataObject;

/**
 * Object reflection
 */
class Reflection
{
    
    /**
     * Class
     * 
     * @var \ReflectionClass 
     */
    protected $class;
    
    /**
     * Methods
     * 
     * @var array
     */
    protected $methods = [];
    
    /**
     * Parent methods
     * 
     * @var array
     */
    protected $parentMethods = [];
    
    /**
     * Properties
     * 
     * @var array 
     */
    protected $properties = [];
    
    /**
     * Constructor
     * 
     * @param string $class
     * @return void
     */
    public function __construct(string $class)
    {
        $this->class = new \ReflectionClass($class);
    }
    
    /**
     * Get class method
     * 
     * @param \ReflectionClass $class
     * @param string $methodName
     * @return \ReflectionMethod
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getClassMethod(\ReflectionClass $class, string $methodName): \ReflectionMethod
    {
        $method = null;
        do {
            if ($class->hasMethod($methodName)) {
                $method = $class->getMethod($methodName);
                break;
            }
        } while ($class = $class->getParentClass());
        if ($method === null) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Method %1 is not found for %2 class.', $methodName, $class->getName()));
        }
        if (!$method->isPublic()) {
            $method->setAccessible(true);
        }
        return $method;
    }
    
    /**
     * Get parent class
     * 
     * @param string $className
     * @return \ReflectionClass
     */
    protected function getParentClass(string $className): \ReflectionClass
    {
        $class = $this->class;
        $isClassFound = false;
        do {
            if ($class->getName() === $className) {
                $isClassFound = true;
                break;
            }
        } while ($class = $class->getParentClass());
        if (!$isClassFound) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Parent class %1 is not found.', $className));
        }
        return $class;
    }
    
    /**
     * Get parent method
     * 
     * @param string $className
     * @param string $methodName
     * @return \ReflectionMethod
     */
    public function getParentMethod(string $className, string $methodName): \ReflectionMethod
    {
        if (!empty($this->parentMethods[$className][$methodName])) {
            return $this->parentMethods[$className][$methodName];
        }
        return $this->parentMethods[$className][$methodName] = $this->getClassMethod($this->getParentClass($className), $methodName);
    }
    
    /**
     * Get method
     * 
     * @param string $methodName
     * @return \ReflectionMethod
     */
    public function getMethod(string $methodName): \ReflectionMethod
    {
        if (!empty($this->methods[$methodName])) {
            return $this->methods[$methodName];
        }
        return $this->methods[$methodName] = $this->getClassMethod($this->class, $methodName);
    }
    
    /**
     * Invoke method
     * 
     * @param object $object
     * @param string $methodName
     * @param mixed ...$arguments
     * @return mixed
     */
    public function invokeMethod($object, string $methodName, ...$arguments)
    {
        return $this->getMethod($methodName)->invoke($object, ...$arguments);
    }
    
    /**
     * Invoke parent method
     * 
     * @param object $object
     * @param string $className
     * @param string $methodName
     * @param mixed ...$arguments
     * @return mixed
     */
    public function invokeParentMethod($object, string $className, string $methodName, ...$arguments)
    {
        return $this->getParentMethod($className, $methodName)->invoke($object, ...$arguments);
    }
    
    /**
     * Get class property
     * 
     * @param \ReflectionClass $class
     * @param string $propertyName
     * @return \ReflectionProperty
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getClassProperty(\ReflectionClass $class, string $propertyName): \ReflectionProperty
    {
        $property = null;
        do {
            if ($class->hasProperty($propertyName)) {
                $property = $class->getProperty($propertyName);
                break;
            }
        } while ($class = $class->getParentClass());
        if ($property === null) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Property %1 is not found for %2 class.', $propertyName, $class->getName()));
        }
        if (!$property->isPublic()) {
            $property->setAccessible(true);
        }
        return $property;
    }
    
    /**
     * Get property
     * 
     * @param string $propertyName
     * @return \ReflectionProperty
     */
    public function getProperty(string $propertyName): \ReflectionProperty
    {
        if (array_key_exists($propertyName, $this->properties)) {
            return $this->properties[$propertyName];
        }
        return $this->properties[$propertyName] = $this->getClassProperty($this->class, $propertyName);
    }
    
    /**
     * Get property value
     * 
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    public function getPropertyValue($object, string $propertyName)
    {
        return $this->getProperty($propertyName)->getValue($object);
    }
    
    /**
     * Set property value
     * 
     * @param object $object
     * @param string $propertyName
     * @param mixed $propertyValue
     * @return $this
     */
    public function setPropertyValue($object, string $propertyName, $propertyValue)
    {
        $this->getProperty($propertyName)->setValue($object, $propertyValue);
        return $this;
    }
    
}