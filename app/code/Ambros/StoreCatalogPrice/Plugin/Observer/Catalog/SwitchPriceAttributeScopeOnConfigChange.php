<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Observer\Catalog;

/**
 * Switch price attribute scope on configuration change plugin
 */
class SwitchPriceAttributeScopeOnConfigChange
{
    
    /**
     * Product attribute repository
     * 
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $productAttributeRepository;

    /**
     * Search criteria builder
     * 
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $productAttributeRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $productAttributeRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        $this->productAttributeRepository = $productAttributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Get price scope text
     * 
     * @return string
     */
    protected function getPriceScopeText(): string
    {
        if ($this->priceScope->isStore()) {
            return \Magento\Catalog\Api\Data\ProductAttributeInterface::SCOPE_STORE_TEXT;
        } else if ($this->priceScope->isWebsite()) {
            return \Magento\Catalog\Api\Data\ProductAttributeInterface::SCOPE_WEBSITE_TEXT;
        } else {
            return \Magento\Catalog\Api\Data\ProductAttributeInterface::SCOPE_GLOBAL_TEXT;
        }
    }
    
    /**
     * Around execute
     * 
     * @param \Magento\Catalog\Observer\SwitchPriceAttributeScopeOnConfigChange $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function aroundExecute(
        \Magento\Catalog\Observer\SwitchPriceAttributeScopeOnConfigChange $subject,
        \Closure $proceed,
        \Magento\Framework\Event\Observer $observer
    )
    {
        $this->searchCriteriaBuilder->addFilter('frontend_input', 'price');
        $criteria = $this->searchCriteriaBuilder->create();
        $priceScopeText = $this->getPriceScopeText();
        $priceAttributes = $this->productAttributeRepository->getList($criteria)->getItems();
        foreach ($priceAttributes as $priceAttribute) {
            $priceAttribute->setScope($priceScopeText);
            $this->productAttributeRepository->save($priceAttribute);
        }
    }
    
}