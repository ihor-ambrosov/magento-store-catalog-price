<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

/**
 * Abstract setup operation
 */
abstract class AbstractOperation implements \Ambros\Common\Setup\Operation\OperationInterface
{
    
    /**
     * Setup
     * 
     * @var \Magento\Framework\Setup\SetupInterface
     */
    protected $setup;
    
    /**
     * Module context
     * 
     * @var \Magento\Framework\Setup\ModuleContextInterface
     */
    protected $moduleContext;
    
    /**
     * Set setup
     * 
     * @param \Magento\Framework\Setup\SetupInterface $setup
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function setSetup(\Magento\Framework\Setup\SetupInterface $setup): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $this->setup = $setup;
        return $this;
    }
    
    /**
     * Get setup
     * 
     * @return \Magento\Framework\Setup\SetupInterface
     */
    public function getSetup(): \Magento\Framework\Setup\SetupInterface
    {
        if (empty($this->setup) || !($this->setup instanceof \Magento\Framework\Setup\SetupInterface)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Setup is undefined.'));
        }
        return $this->setup;
    }
    
    /**
     * Set module context
     * 
     * @param \Magento\Framework\Setup\ModuleContextInterface $moduleContext
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function setModuleContext(\Magento\Framework\Setup\ModuleContextInterface $moduleContext): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $this->moduleContext = $moduleContext;
        return $this;
    }
    
    /**
     * Get module context
     * 
     * @return \Magento\Framework\Setup\ModuleContextInterface
     */
    public function getModuleContext(): \Magento\Framework\Setup\ModuleContextInterface
    {
        if (empty($this->moduleContext) || !($this->moduleContext instanceof \Magento\Framework\Setup\ModuleContextInterface)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Module context is undefined.'));
        }
        return $this->moduleContext;
    }
    
    /**
     * Get connection
     * 
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected function getConnection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->getSetup()->getConnection();
    }
    
    /**
     * Get table
     * 
     * @param string $tableName
     * @return string
     */
    protected function getTable(string $tableName): string
    {
        return $this->getSetup()->getTable($tableName);
    }
    
    /**
     * Compare module version
     * 
     * @param string $version
     * @param string $operator
     * @return bool
     */
    protected function compareModuleVersion(string $version, string $operator): bool
    {
        return (bool) version_compare($this->getModuleContext()->getVersion(), $version, $operator);
    }
    
    /**
     * Check if module version is less
     * 
     * @param string $version
     * @return bool
     */
    protected function isModuleVersionLess(string $version): bool
    {
        return $this->compareModuleVersion($version, '<');
    }
    
    /**
     * Execute
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    abstract public function execute(): \Ambros\Common\Setup\Operation\OperationInterface;
    
}