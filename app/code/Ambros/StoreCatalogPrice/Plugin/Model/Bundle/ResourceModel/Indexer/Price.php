<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Bundle\ResourceModel\Indexer;

/**
 * Bundle product price indexer plugin
 */
class Price extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Final price
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\FinalPrice
     */
    protected $finalPrice;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\FinalPrice $finalPrice
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Model\Bundle\ResourceModel\Indexer\FinalPrice $finalPrice
    )
    {
        parent::__construct($wrapperFactory);
        $this->finalPrice = $finalPrice;
    }
    
    /**
     * Around execute by dimensions
     * 
     * @param \Magento\Bundle\Model\ResourceModel\Indexer\Price $subject
     * @param \Closure $proceed
     * @param array $dimensions
     * @param \Traversable $entityIds
     * @return void
     */
    public function aroundExecuteByDimensions(
        \Magento\Bundle\Model\ResourceModel\Indexer\Price $subject,
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