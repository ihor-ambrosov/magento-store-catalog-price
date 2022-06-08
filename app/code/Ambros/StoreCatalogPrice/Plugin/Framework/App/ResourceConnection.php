<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Framework\App;

/**
 * Resource connection plugin
 */
class ResourceConnection
{
    
    /**
     * Price index table resolver
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver
     */
    protected $priceIndexTableResolver;

    /**
     * Dimension mode configuration
     * 
     * @var \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration
     */
    protected $dimensionModeConfiguration;

    /**
     * Constructor
     * 
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver
     * @param \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $dimensionModeConfiguration
     * @return void
     */
    public function __construct(
        \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver,
        \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $dimensionModeConfiguration
    )
    {
        $this->priceIndexTableResolver = $priceIndexTableResolver;
        $this->dimensionModeConfiguration = $dimensionModeConfiguration;
    }

    /**
     * After get table name
     * 
     * @param \Magento\Framework\App\ResourceConnection $subject
     * @param string $result
     * @param string|string[] $tableName
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterGetTableName(
        \Magento\Framework\App\ResourceConnection $subject,
        string $result,
        $tableName
    )
    {
        $dimensionNames = $this->dimensionModeConfiguration->getDimensionConfiguration();
        if (!is_array($tableName) && $tableName === 'ambros_store__catalog_product_index_price' && $dimensionNames) {
            return $this->priceIndexTableResolver->resolveByDimensionNames($dimensionNames);
        }
        return $result;
    }

}