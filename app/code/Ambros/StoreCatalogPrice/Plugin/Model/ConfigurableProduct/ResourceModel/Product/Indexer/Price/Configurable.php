<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price;

/**
 * Configurable product price indexer plugin
 */
class Configurable extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Final price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price\FinalPrice
     */
    protected $finalPrice;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price\FinalPrice $finalPrice
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Model\ConfigurableProduct\ResourceModel\Product\Indexer\Price\FinalPrice $finalPrice
    )
    {
        parent::__construct($wrapperFactory);
        $this->finalPrice = $finalPrice;
    }
    
    /**
     * Around execute by dimensions
     * 
     * @param \Magento\ConfigurableProduct\Model\ResourceModel\Product\Indexer\Price\Configurable $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Indexer\Dimension[] $dimensions
     * @param \Traversable $entityIds
     * @return void
     */
    public function aroundExecuteByDimensions(
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Indexer\Price\Configurable $subject,
        \Closure $proceed,
        array $dimensions,
        \Traversable $entityIds
    )
    {
        $this->setSubject($subject);
        $this->finalPrice->execute(
            $dimensions,
            iterator_to_array($entityIds),
            $this->getSubjectPropertyValue('fullReindexAction')
        );
    }
    
}