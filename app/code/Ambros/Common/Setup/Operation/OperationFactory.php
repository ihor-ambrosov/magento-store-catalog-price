<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

use Magento\Framework\ObjectManagerInterface;

/**
 * Setup operation factory
 */
class OperationFactory
{
    
    /**
     * Object Manager
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @return void
     */
    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create
     *
     * @param string $instanceName
     * @param array $data
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     * @throws \InvalidArgumentException
     */
    public function create($instanceName, array $data = [])
    {
        $operation = $this->objectManager->create($instanceName, $data);
        if (!$operation instanceof \Ambros\Common\Setup\Operation\OperationInterface) {
            throw new \InvalidArgumentException(__('Type %1 is not instance of %2', $instanceName, \Ambros\Common\Setup\Operation\OperationInterface::class));
        }
        return $operation;
    }
    
}