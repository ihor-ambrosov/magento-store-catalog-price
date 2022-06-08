<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Block\Directory\Adminhtml\Frontend\Currency;

/**
 * Base currency configuration field front-end plugin
 */
class Base
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around move data
     * 
     * @param \Magento\Directory\Block\Adminhtml\Frontend\Currency\Base $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function aroundRender(
        \Magento\Directory\Block\Adminhtml\Frontend\Currency\Base $subject,
        \Closure $proceed,
        \Magento\Framework\Data\Form\Element\AbstractElement $element
    )
    {
        $request = $subject->getRequest();
        if (
            ($request->getParam('store') != '' && ($this->priceScope->isGlobal() || $this->priceScope->isWebsite())) || 
            ($request->getParam('website') != '' && $this->priceScope->isGlobal())
        ) {
            return '';
        }
        return $proceed($element);
    }
    
}