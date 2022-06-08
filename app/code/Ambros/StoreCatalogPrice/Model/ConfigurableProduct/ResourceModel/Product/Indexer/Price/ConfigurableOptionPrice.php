<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price;

/**
 * Configurable option price indexer
 */
class ConfigurableOptionPrice
{
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;

    /**
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer
     */
    protected $tableMaintainer;
    
    /**
     * Scope configuration
     * 
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Module manager
     * 
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Object manager
     * 
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Base select processor
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface
     */
    protected $baseSelectProcessor;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\Framework\ObjectManagerInterface $objectManager
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->tableMaintainer = $tableMaintainer;
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->productMetadata = $productMetadata;
        $this->objectManager = $objectManager;
        if (version_compare($this->productMetadata->getVersion(), '2.4.2', '>=')) {
            if ($this->moduleManager->isEnabled('Magento_InventoryConfigurableProduct')) {
                $this->baseSelectProcessor = $this->objectManager->get(\Magento\InventoryConfigurableProduct\Pricing\Price\Indexer\BaseStockStatusSelectProcessor::class);
            } else {
                $this->baseSelectProcessor = $this->objectManager->get(\Magento\ConfigurableProduct\Model\ResourceModel\Product\Indexer\Price\BaseStockStatusSelectProcessor::class);
            }
        }
    }
    
    /**
     * Check if is configuration show out of stock
     * 
     * @return bool
     */
    protected function isConfigShowOutOfStock(): bool
    {
        return $this->scopeConfig->isSetFlag(
            \Magento\CatalogInventory\Model\Configuration::XML_PATH_SHOW_OUT_OF_STOCK,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    /**
     * Get main table
     *
     * @param array $dimensions
     * @param bool $fullReindexAction
     * @return string
     */
    protected function getMainTable(array $dimensions, bool $fullReindexAction): string
    {
        if ($fullReindexAction) {
            return $this->tableMaintainer->getMainReplicaTable($dimensions);
        }
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            return $this->tableMaintainer->getMainTableByDimensions($dimensions);
        } else {
            return $this->tableMaintainer->getMainTable($dimensions);
        }
    }
    
    /**
     * Execute
     * 
     * @param array $dimensions
     * @param array $entityIds
     * @param bool $fullReindexAction
     * @return $this
     */
    public function execute(array $dimensions, array $entityIds, bool $fullReindexAction)
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $connection->select()
            ->from(['i' => $this->getMainTable($dimensions, $fullReindexAction)], [])
            ->join(
                ['l' => $this->connectionProvider->getTable('catalog_product_super_link')],
                'l.product_id = i.entity_id',
                []
            )
            ->join(
                ['le' => $this->connectionProvider->getTable('catalog_product_entity')],
                'le.entity_id = l.parent_id',
                []
            );
        if (version_compare($this->productMetadata->getVersion(), '2.4.2', '>=')) {
            $this->baseSelectProcessor->process($select);
        } else {
            if ($this->isConfigShowOutOfStock()) {
                $select
                    ->join(
                        ['si' => $this->connectionProvider->getTable('cataloginventory_stock_item')],
                        'si.product_id = l.product_id',
                        []
                    )
                    ->where('si.is_in_stock = ?', \Magento\CatalogInventory\Model\Stock::STOCK_IN_STOCK);
            }
        }
        $select
            ->columns([
                'le.entity_id',
                'customer_group_id',
                'store_id',
                'MIN(final_price)',
                'MAX(final_price)',
                'MIN(tier_price)',
            ])
            ->group(['le.entity_id', 'customer_group_id', 'store_id']);
        if ($entityIds !== null) {
            $select->where('le.entity_id IN (?)', $entityIds, \Zend_Db::INT_TYPE);
        }
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, 'ambros_store__catalog_product_index_price_cfg_opt_temp', []);
        } else {
            $connection->query($select->insertFromSelect('ambros_store__catalog_product_index_price_cfg_opt_temp'));
        }
        return $this;
    }
}