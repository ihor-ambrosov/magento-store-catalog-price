<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Wrapper\Model\Rule\ResourceModel\Rule\Collection;

/**
 * Abstract rule collection wrapper factory
 */
class AbstractCollectionFactory extends \Ambros\Common\DataObject\WrapperFactory
{
    
    /**
     * Constructor
     * 
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        $instanceName = \Ambros\StoreCommon\Wrapper\Model\Rule\ResourceModel\Rule\Collection\AbstractCollection::class
    )
    {
        parent::__construct($objectManager, $instanceName);
    }
    
}