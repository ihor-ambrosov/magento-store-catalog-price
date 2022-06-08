<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price;

/**
 * Price index table resolver
 */
class PriceIndexTableResolver
{
    
    /**
     * Price table resolver
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver
     */
    protected $priceTableResolver;
    
    /**
     * Dimension factory
     * 
     * @var \Magento\Framework\Indexer\DimensionFactory
     */
    protected $dimensionFactory;
    
    /**
     * HTTP context
     * 
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface 
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver $priceTableResolver
     * @param \Magento\Framework\Indexer\DimensionFactory $dimensionFactory
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver $priceTableResolver,
        \Magento\Framework\Indexer\DimensionFactory $dimensionFactory,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->priceTableResolver = $priceTableResolver;
        $this->dimensionFactory = $dimensionFactory;
        $this->httpContext = $httpContext;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Create store dimension
     * 
     * @param int|null $storeId
     * @return \Magento\Framework\Indexer\Dimension
     */
    protected function createStoreDimension(int $storeId = null): \Magento\Framework\Indexer\Dimension
    {
        return $this->dimensionFactory->create(
            \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME,
            (string) $this->storeManager->getStore(
                $storeId === null ? 
                    $this->httpContext->getValue(\Magento\Store\Model\StoreManagerInterface::CONTEXT_STORE) : 
                    $storeId
            )->getId()
        );
    }
    
    /**
     * Create dimension from customer group
     * 
     * @param int|null $customerGroupId
     * @return \Magento\Framework\Indexer\Dimension
     */
    protected function createCustomerGroupDimension(int $customerGroupId = null): \Magento\Framework\Indexer\Dimension
    {
        return $this->dimensionFactory->create(
            \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME,
            $customerGroupId === null ? 
                (string) $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_GROUP) : 
                (string) $customerGroupId
        );
    }
    
    /**
     * Resolve by dimensions
     * 
     * @param array $dimensions
     * @return string
     */
    public function resolveByDimensions(array $dimensions): string
    {
        return (string) $this->priceTableResolver->resolve('ambros_store__catalog_product_index_price', $dimensions);
    }
    
    /**
     * Resolve
     * 
     * @param int|null $storeId
     * @param int|null $customerGroupId
     * @return string
     */
    public function resolve(int $storeId = null, int $customerGroupId = null): string
    {
        return $this->resolveByDimensions([
            $this->createStoreDimension($storeId),
            $this->createCustomerGroupDimension($customerGroupId),
        ]);
    }
    
    /**
     * Resolve by dimension names
     * 
     * @param array $dimensionNames
     * @param int|null $storeId
     * @param int|null $customerGroupId
     * @return string
     */
    public function resolveByDimensionNames(array $dimensionNames, int $storeId = null, int $customerGroupId = null): string
    {
        $dimensions = [];
        foreach ($dimensionNames as $dimensionName) {
            if ($dimensionName === \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME) {
                $dimensions[] = $this->createStoreDimension($storeId);
            }
            if ($dimensionName === \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME) {
                $dimensions[] = $this->createCustomerGroupDimension($customerGroupId);
            }
        }
        return $this->resolveByDimensions($dimensions);
    }
    
}