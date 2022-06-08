<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\DataObject;

/**
 * Object reflection factory
 */
class ReflectionFactory extends \Ambros\Common\DataObject\Factory
{
    
    /**
     * Instances
     * 
     * @var array
     */
    protected $instances = [];
    
    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        $instanceName = \Ambros\Common\DataObject\Reflection::class
    )
    {
        parent::__construct($objectManager, $instanceName);
    }
    
    /**
     * Create
     *
     * @param array $data
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function create(array $data = [])
    {
        if (empty($data['class'])) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Class argument is not found for %1 factory.', get_class($this)));
        }
        $class = $data['class'];
        if (array_key_exists($class, $this->instances)) {
            return $this->instances[$class];
        }
        return $this->instances[$class] = parent::create($data);
    }
    
}