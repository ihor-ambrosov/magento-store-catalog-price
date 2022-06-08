<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

/**
 * Setup operation interface
 */
interface OperationInterface
{
    
    /**
     * Set setup
     * 
     * @param \Magento\Framework\Setup\SetupInterface $setup
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function setSetup(\Magento\Framework\Setup\SetupInterface $setup): \Ambros\Common\Setup\Operation\OperationInterface;
    
    /**
     * Get setup
     * 
     * @return \Magento\Framework\Setup\SetupInterface
     */
    public function getSetup(): \Magento\Framework\Setup\SetupInterface;
    
    /**
     * Set module context
     * 
     * @param \Magento\Framework\Setup\ModuleContextInterface $moduleContext
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function setModuleContext(\Magento\Framework\Setup\ModuleContextInterface $moduleContext): \Ambros\Common\Setup\Operation\OperationInterface;
    
    /**
     * Get module context
     * 
     * @return \Magento\Framework\Setup\ModuleContextInterface
     */
    public function getModuleContext(): \Magento\Framework\Setup\ModuleContextInterface;
    
    /**
     * Execute
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function execute(): \Ambros\Common\Setup\Operation\OperationInterface;
    
}