<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price;

/**
 * Product price index table structure provider
 */
class IndexTableStructureProvider
{
    
    /**
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer 
     */
    protected $tableMaintainer;
    
    /**
     * Index table structure factory
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory 
     */
    protected $indexTableStructureFactory;
    
    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory $indexTableStructureFactory
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory $indexTableStructureFactory
    )
    {
        $this->tableMaintainer = $tableMaintainer;
        $this->indexTableStructureFactory = $indexTableStructureFactory;
    }
    
    /**
     * Create
     * 
     * @param array $dimensions
     * @return \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure
     */
    public function create(array $dimensions): \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure
    {
        return $this->indexTableStructureFactory->create([
            'tableName' => $this->tableMaintainer->getMainTmpTable($dimensions),
            'entityField' => 'entity_id',
            'customerGroupField' => 'customer_group_id',
            'websiteField' => 'website_id',
            'taxClassField' => 'tax_class_id',
            'originalPriceField' => 'price',
            'finalPriceField' => 'final_price',
            'minPriceField' => 'min_price',
            'maxPriceField' => 'max_price',
            'tierPriceField' => 'tier_price',
        ]);
    }
    
}