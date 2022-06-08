<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price;

/**
 * Product price indexer dimension mode configuration plugin
 */
class DimensionModeConfiguration extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Dimension modes
     */
    const DIMENSION_NONE = \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration::DIMENSION_NONE;
    const DIMENSION_STORE = 'store';
    const DIMENSION_CUSTOMER_GROUP = \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration::DIMENSION_CUSTOMER_GROUP;
    const DIMENSION_STORE_AND_CUSTOMER_GROUP = 'store_and_customer_group';
    
    /**
     * Dimension modes mapping
     * 
     * @var array
     */
    protected $modesMapping = [
        self::DIMENSION_NONE => [
        ],
        self::DIMENSION_STORE => [
            \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME
        ],
        self::DIMENSION_CUSTOMER_GROUP => [
            \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME
        ],
        self::DIMENSION_STORE_AND_CUSTOMER_GROUP => [
            \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME,
            \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME
        ],
    ];
    
    /**
     * Around get dimension modes
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $subject
     * @param \Closure $proceed
     * @return array
     */
    public function aroundGetDimensionModes(
        \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $subject,
        \Closure $proceed
    ): array
    {
        return $this->modesMapping;
    }
    
    /**
     * Around get dimension configuration
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $subject
     * @param \Closure $proceed
     * @param string|null $mode
     * @return string[]
     * @throws \InvalidArgumentException
     */
    public function aroundGetDimensionConfiguration(
        \Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration $subject,
        \Closure $proceed,
        string $mode = null
    ): array
    {
        $this->setSubject($subject);
        if ($mode && !isset($this->modesMapping[$mode])) {
            throw new \InvalidArgumentException(sprintf('Undefined dimension mode "%s".', $mode));
        }
        return $this->modesMapping[$mode ?? $this->invokeSubjectMethod('getCurrentMode')];
    }
    
}