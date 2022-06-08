<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Downloadable\ResourceModel\Indexer;

/**
 * Downloadable product price indexer plugin
 */
class Price
{
    
    /**
     * Final price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer\FinalPrice
     */
    protected $finalPrice;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer\FinalPrice $finalPrice
     * @return void
     */
    public function __construct(
        \Ambros\StoreCatalogPrice\Model\Downloadable\ResourceModel\Indexer\FinalPrice $finalPrice
    )
    {
        $this->finalPrice = $finalPrice;
    }
    
    /**
     * Around execute by dimensions
     * 
     * @param \Magento\Downloadable\Model\ResourceModel\Indexer\Price $subject
     * @param \Closure $proceed
     * @param array $dimensions
     * @param \Traversable $entityIds
     * @return void
     */
    public function aroundExecuteByDimensions(
        \Magento\Downloadable\Model\ResourceModel\Indexer\Price $subject,
        \Closure $proceed,
        array $dimensions,
        \Traversable $entityIds
    ): void
    {
        $this->finalPrice->execute($dimensions, iterator_to_array($entityIds));
    }
    
}