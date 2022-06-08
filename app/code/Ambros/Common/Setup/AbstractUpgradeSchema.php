<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup;

/**
 * Upgrade schema
 */
abstract class AbstractUpgradeSchema implements \Magento\Framework\Setup\UpgradeSchemaInterface
{
    
    /**
     * Operation
     * 
     * @var \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected $operation;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\Setup\Operation\OperationInterface $operation
     * @return void
     */
    public function __construct(
        \Ambros\Common\Setup\Operation\OperationInterface $operation
    )
    {
        $this->operation = $operation;
    }
    
    /**
     * Upgrade
     *
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     * @return void
     */
    public function upgrade(\Magento\Framework\Setup\SchemaSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->operation->setSetup($setup);
        $this->operation->setModuleContext($context);
        $this->operation->execute();
        $setup->endSetup();
    }
    
}