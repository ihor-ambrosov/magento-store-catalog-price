<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\DataObject;

/**
 * Object wrapper factory
 */
class WrapperFactory extends \Ambros\Common\DataObject\Factory
{
    
    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        $instanceName = \Ambros\Common\DataObject\Wrapper::class
    )
    {
        parent::__construct($objectManager, $instanceName);
    }
    
}