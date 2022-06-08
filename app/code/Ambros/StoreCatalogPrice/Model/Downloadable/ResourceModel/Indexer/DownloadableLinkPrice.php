<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer;

/**
 * Downloadable link price indexer
 */
class DownloadableLinkPrice
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Table maintainer
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer
     */
    protected $tableMaintainer;
    
    /**
     * EAV configuration
     * 
     * @var \Magento\Eav\Model\Config
     */
    protected $eavConfig;
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $tableMaintainer,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceScope = $priceScope;
        $this->tableMaintainer = $tableMaintainer;
        $this->eavConfig = $eavConfig;
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Get attribute
     * 
     * @param string $attributeCode
     * @return \Magento\Eav\Model\Entity\Attribute\AbstractAttribute
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getAttribute($attributeCode)
    {
        return $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
    }
    
    /**
     * Execute
     * 
     * @param array $dimensions
     * @return $this
     */
    public function execute(array $dimensions)
    {
        $connection = $this->connectionProvider->getConnection();
        $dlType = $this->getAttribute('links_purchased_separately');
        $select = $connection->select();
        $select->from(
            ['i' => $this->tableMaintainer->getMainTmpTable($dimensions)],
            ['entity_id', 'customer_group_id', 'store_id']
        );
        $select->join(
            ['dl' => $dlType->getBackend()->getTable()],
            $this->connectionProvider->getCondition([
                'dl.entity_id = i.entity_id',
                'dl.attribute_id = '.$dlType->getAttributeId(),
                'dl.store_id = 0',
            ], 'AND'),
            []
        );
        $select->join(
            ['dll' => $this->connectionProvider->getTable('downloadable_link')],
            'dll.product_id = i.entity_id',
            []
        );
        $select->join(
            ['dlpd' => $this->connectionProvider->getTable('ambros_store__downloadable_link_price')],
            $this->connectionProvider->getCondition(['dll.link_id = dlpd.link_id', 'dlpd.store_id = 0'], 'AND'),
            []
        );
        if ($this->priceScope->isGlobal()) {
            $price = 'dlpd.price';
        } else {
            $select->joinLeft(
                ['dlps' => $this->connectionProvider->getTable('ambros_store__downloadable_link_price')],
                $this->connectionProvider->getCondition(['dlpd.link_id = dlps.link_id', 'dlps.store_id = i.store_id'], 'AND'),
                []
            );
            $price = (string) $connection->getCheckSql('dlps.price_id > 0', 'dlps.price', 'dlpd.price');
        }
        $select->where('dl.value = ?', 1);
        $select->group(['i.entity_id', 'i.customer_group_id', 'i.store_id']);
        $select->columns([
            'min_price' => $this->connectionProvider->getSql('MIN('.$price.')'),
            'max_price' => $this->connectionProvider->getSql('SUM('.$price.')'),
        ]);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->tableMaintainer->insertFromSelect($select, 'ambros_store__catalog_product_index_price_downlod_temp', []);
        } else {
            $connection->query($select->insertFromSelect('ambros_store__catalog_product_index_price_downlod_temp'));
        }
    }
    
}