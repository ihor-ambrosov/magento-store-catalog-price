<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\CatalogSearch\Search\FilterMapper;

/**
 * Catalog search filter mapper exclusion strategy plugin
 */
class ExclusionStrategy extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price index table resolver
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver
     */
    protected $priceIndexTableResolver;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope 
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceIndexTableResolver = $priceIndexTableResolver;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Apply price filter
     *
     * @param \Magento\Framework\Search\Request\FilterInterface $filter
     * @param \Magento\Framework\DB\Select $select
     * @return bool
     * @throws \DomainException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function applyPriceFilter(
        \Magento\Framework\Search\Request\FilterInterface $filter,
        \Magento\Framework\DB\Select $select
    )
    {
        $aliasResolver = $this->getSubjectPropertyValue('aliasResolver');
        $resourceConnection = $this->getSubjectPropertyValue('resourceConnection');
        $connection = $resourceConnection->getConnection();
        $storeId = $this->priceScope->getStoreId();
        $select->joinInner(
            [ $aliasResolver->getAlias($filter) => $this->priceIndexTableResolver->resolve($storeId) ],
            $connection->quoteInto(
                $this->invokeSubjectMethod('extractTableAliasFromSelect', $select).'.entity_id = price_index.entity_id AND price_index.store_id = ?',
                $storeId
            ),
            []
        );
        return true;
    }
    
    /**
     * Around apply
     * 
     * @param \Magento\CatalogSearch\Model\Search\FilterMapper\ExclusionStrategy $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Search\Request\FilterInterface $filter
     * @param \Magento\Framework\DB\Select $select
     * @return bool
     */
    public function aroundApply(
        $subject,
        \Closure $proceed,
        \Magento\Framework\Search\Request\FilterInterface $filter,
        \Magento\Framework\DB\Select $select
    )
    {
        $this->setSubject($subject);
        if (!in_array($filter->getField(), $this->getSubjectPropertyValue('validFields'), true)) {
            return false;
        }
        if ($filter->getField() === 'price') {
            return $this->applyPriceFilter($filter, $select);
        } elseif ($filter->getField() === 'category_ids') {
            return $this->invokeSubjectMethod('applyCategoryFilter', $filter, $select);
        }
   }
    
}