<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Setup\Operation;

/**
 * Install data setup operation
 */
class InstallData extends \Ambros\Common\Setup\Operation\AbstractOperation
{
    
    /**
     * EAV setup factory
     * 
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    protected $eavSetupFactory;
    
    /**
     * Cache manager
     * 
     * @var \Magento\Framework\App\Cache\Manager
     */
    protected $cacheManager;
    
    /**
     * Indexer registry
     * 
     * @var \Magento\Framework\Indexer\IndexerRegistry 
     */
    protected $indexerRegistry;
    
    /**
     * EAV setup
     * 
     * @var \Magento\Eav\Setup\EavSetup
     */
    protected $eavSetup;
    
    /**
     * Constructor
     * 
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     * @param \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry
     * @return void
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Framework\App\Cache\Manager $cacheManager,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->cacheManager = $cacheManager;
        $this->indexerRegistry = $indexerRegistry;
    }
    
    /**
     * Get EAV setup
     * 
     * @return \Magento\Eav\Setup\EavSetup
     */
    protected function getEavSetup(): \Magento\Eav\Setup\EavSetup
    {
        if ($this->eavSetup !== null) {
            return $this->eavSetup;
        }
        return $this->eavSetup = $this->eavSetupFactory->create(['setup' => $this->getSetup()]);
    }
    
    /**
     * Update product attribute scope
     * 
     * @param string $attributeCode
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function updateProductAttributeScope(string $attributeCode): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $this->getEavSetup()->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $attributeCode,
            'is_global',
            \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE
        );
        return $this;
    }
    
    /**
     * Update product MSRP attributes scope
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function updateProductMsrpAttributesScope(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $this->updateProductAttributeScope('msrp');
        $this->updateProductAttributeScope('msrp_display_actual_price_type');
        return $this;
    }
    
    /**
     * Update product special price attributes scope
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function updateProductSpecialPriceAttributesScope(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $this->updateProductAttributeScope('special_from_date');
        $this->updateProductAttributeScope('special_to_date');
        return $this;
    }
    
    /**
     * Copy product tier prices
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function copyProductTierPrices(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $connection = $this->getConnection();
        $connection->query(
            $connection->insertFromSelect(
                $connection->select()
                    ->from(['tp' => $this->getTable('catalog_product_entity_tier_price')], [])
                    ->joinLeft(
                        ['sw' => $this->getTable('store_website')],
                        'tp.website_id = sw.website_id',
                        []
                    )
                    ->joinLeft(
                        ['s' => $this->getTable('store')],
                        'sw.website_id = s.website_id',
                        []
                    )
                    ->columns([
                        'entity_id' => 'tp.entity_id',
                        'all_groups' => 'tp.all_groups',
                        'customer_group_id' => 'tp.customer_group_id',
                        'qty' => 'tp.qty',
                        'value' => 'tp.value',
                        'store_id' => $connection->getIfNullSql('s.store_id', $connection->quote('0')),
                        'percentage_value' => 'tp.percentage_value',
                    ]),
                $this->getTable('ambros_store__catalog_product_entity_tier_price'),
                [
                    'entity_id',
                    'all_groups',
                    'customer_group_id',
                    'qty',
                    'value',
                    'store_id',
                    'percentage_value'
                ]
            )
        );
        return $this;
    }
    
    /**
     * Execute
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function execute(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $this->updateProductMsrpAttributesScope();
        $this->updateProductSpecialPriceAttributesScope();
        $this->copyProductTierPrices();
        $indexer = $this->indexerRegistry->get(\Magento\Catalog\Model\Indexer\Product\Price\Processor::INDEXER_ID);
        $indexer->invalidate();
        return $this;
    }
   
}