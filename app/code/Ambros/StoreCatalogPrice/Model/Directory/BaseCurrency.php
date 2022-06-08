<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Directory;

/**
 * Base currency
 */
class BaseCurrency
{
    
    /**
     * Scope configuration
     * 
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * Currency factory
     * 
     * @var \Magento\Directory\Model\CurrencyFactory 
     */
    protected $currencyFactory;
    
    /**
     * Code
     * 
     * @var string
     */
    protected $code;
    
    /**
     * Currency
     * 
     * @var \Magento\Directory\Model\Currency
     */
    protected $currency;
    
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->currencyFactory = $currencyFactory;
    }
    
    
    /**
     * Get code
     * 
     * @return string
     */
    public function getCode(): string
    {
        if ($this->code !== null) {
            return $this->code;
        }
        return $this->code = $this->scopeConfig->getValue(\Magento\Directory\Model\Currency::XML_PATH_CURRENCY_BASE);
    }
    
    /**
     * Get
     * 
     * @return \Magento\Directory\Model\Currency
     */
    public function get(): \Magento\Directory\Model\Currency
    {
        if ($this->currency !== null) {
            return $this->currency;
        }
        return $this->currency = $this->currencyFactory->create()->load($this->getCode());
    }
    
}