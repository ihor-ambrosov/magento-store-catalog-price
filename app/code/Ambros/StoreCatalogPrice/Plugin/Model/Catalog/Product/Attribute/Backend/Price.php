<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend;

/**
 * Product price attribute backend plugin
 */
class Price
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
     * Around set scope
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\Price $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute
     * @return \Magento\Catalog\Model\Product\Attribute\Backend\Price
     */
    public function aroundSetScope(
        \Magento\Catalog\Model\Product\Attribute\Backend\Price $subject,
        \Closure $proceed,
        $attribute
    )
    {
        if ($this->priceScope->isStore()) {
            $attribute->setIsGlobal(\Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE);
        } else if ($this->priceScope->isWebsite()) {
            $attribute->setIsGlobal(\Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE);
        } else {
            $attribute->setIsGlobal(\Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL);
        }
        return $subject;
    }
    
}