<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Store;

/**
 * Store plugin
 */
class Store
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     */
    protected $priceScope;
    
    /**
     * Scope configuration
     * 
     * @var \Magento\Framework\App\Config\ReinitableConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Magento\Framework\App\Config\ReinitableConfigInterface $scopeConfig
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Magento\Framework\App\Config\ReinitableConfigInterface $scopeConfig
    )
    {
        $this->priceScope = $priceScope;
        $this->scopeConfig = $scopeConfig;
    }
    
    /**
     * Around get base currency code
     * 
     * @param \Magento\Store\Model\Store $subject
     * @param \Closure $proceed
     * @return string
     */
    public function aroundGetBaseCurrencyCode(
        \Magento\Store\Model\Store $subject,
        \Closure $proceed
    )
    {
        $baseCurrencyConfigPath = \Magento\Directory\Model\Currency::XML_PATH_CURRENCY_BASE;
        if ($this->priceScope->isStore()) {
            return $subject->getConfig($baseCurrencyConfigPath);
        } else if ($this->priceScope->isWebsite()) {
            return $subject->getWebsite()->getConfig($baseCurrencyConfigPath);
        } else {
            return $this->scopeConfig->getValue($baseCurrencyConfigPath);
        }
    }
    
}