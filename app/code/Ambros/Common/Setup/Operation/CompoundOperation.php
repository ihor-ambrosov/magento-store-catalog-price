<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

/**
 * Compound setup operation
 */
abstract class CompoundOperation extends \Ambros\Common\Setup\Operation\AbstractOperation
{
    
    /**
     * Operations
     * 
     * @var array
     */
    protected $operations;
    
    /**
     * Operation instances
     * 
     * @var array
     */
    protected $operationInstances;
    
    /**
     * Operation factory
     * 
     * @var \Ambros\Common\Setup\Operation\OperationFactory
     */
    protected $operationFactory;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\Setup\Operation\OperationFactory $operationFactory
     * @param array $operations
     * @return void
     */
    public function __construct(
        \Ambros\Common\Setup\Operation\OperationFactory $operationFactory,
        array $operations = []
    )
    {
        $this->operationFactory = $operationFactory;
        $this->operations = $operations;
    }
    
    /**
     * Validate operations
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function validateOperations(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        foreach ($this->operations as $operation) {
            if (empty($operation['class'])) {
                throw new \Magento\Framework\Exception\LocalizedException(__('The parameter "class" is missing. Set the "class" and try again.'));
            }
            if (empty($operation['sortOrder'])) {
                throw new \Magento\Framework\Exception\LocalizedException(__('The parameter "sortOrder" is missing. Set the "sortOrder" and try again.'));
            }
        }
        return $this;
    }
    
    /**
     * Get operation sort order
     *
     * @param array $operation
     * @return int
     */
    protected function getOperationSortOrder(array $operation): int
    {
        return !empty($operation['sortOrder']) ? (int) $operation['sortOrder'] : 0;
    }
    
    /**
     * Sort operations
     *
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function sortOperations(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        usort($this->operations, function (array $operation1, array $operation2) {
            return $this->getOperationSortOrder($operation1) <=> $this->getOperationSortOrder($operation2);
        });
        return $this;
    }
    
    /**
     * Get operation instances
     *
     * @return \Ambros\Common\Setup\Operation\OperationInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOperationInstances()
    {
        if ($this->operationInstances !== null) {
            return $this->operationInstances;
        }
        $this->validateOperations();
        $this->sortOperations();
        foreach ($this->operations as $operation) {
            $operationClass = $operation['class'];
            $operationArguments = !empty($operation['arguments']) ? $operation['arguments'] : [];
            $this->operationInstances[] = $this->operationFactory->create($operationClass, $operationArguments);
        }
        return $this->operationInstances;
    }
    
    /**
     * Set setup
     * 
     * @param \Magento\Framework\Setup\SetupInterface $setup
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function setSetup(\Magento\Framework\Setup\SetupInterface $setup): \Ambros\Common\Setup\Operation\OperationInterface
    {
        parent::setSetup($setup);
        foreach ($this->getOperationInstances() as $operationInstance) {
            $operationInstance->setSetup($setup);
        }
        return $this;
    }
    
    /**
     * Set module context
     * 
     * @param \Magento\Framework\Setup\ModuleContextInterface $moduleContext
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function setModuleContext(\Magento\Framework\Setup\ModuleContextInterface $moduleContext): \Ambros\Common\Setup\Operation\OperationInterface
    {
        parent::setModuleContext($moduleContext);
        foreach ($this->getOperationInstances() as $operationInstance) {
            $operationInstance->setModuleContext($moduleContext);
        }
        return $this;
    }
    
    /**
     * Execute
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function execute(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        foreach ($this->getOperationInstances() as $operationInstance) {
            $operationInstance->execute();
        }
        return $this;
    }
    
}