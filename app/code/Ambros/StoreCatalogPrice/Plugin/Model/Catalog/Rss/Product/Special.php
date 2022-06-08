<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Rss\Product;

/**
 * Special price product RSS plugin
 */
class Special
{
    
    /**
     * Product factory
     * 
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Around get products collection
     * 
     * @param \Magento\Catalog\Model\Rss\Product\Special $subject
     * @param \Closure $proceed
     * @param int $storeId
     * @param int $customerGroupId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundGetProductsCollection(
        \Magento\Catalog\Model\Rss\Product\Special $subject,
        \Closure $proceed,
        $storeId,
        $customerGroupId
    )
    {
        $storeId = (int) $this->storeManager->getStore($storeId)->getId();
        $product = $this->productFactory->create();
        $product->setStoreId($storeId);
        $collection = $product->getResourceCollection();
        $collection->addPriceDataFieldFilter('%s < %s', ['final_price', 'price']);
        $collection->addPriceData($customerGroupId, $storeId);
        $collection->addAttributeToSelect(
            [
                'name',
                'short_description',
                'description',
                'price',
                'thumbnail',
                'special_price',
                'special_to_date',
                'msrp_display_actual_price_type',
                'msrp',
            ],
            'left'
        );
        $collection->addAttributeToSort('name', 'asc');
        return $collection;
    }
    
}