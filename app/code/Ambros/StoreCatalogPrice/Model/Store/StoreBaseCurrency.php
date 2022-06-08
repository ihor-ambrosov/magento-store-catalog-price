<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Store;

/**
 * Store base currency
 */
class StoreBaseCurrency
{
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     *
     * @var \Magento\Directory\Model\CurrencyFactory 
     */
    protected $currencyFactory;
    
    /**
     * Base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency
     */
    protected $baseCurrency;
    
    /**
     * Codes
     * 
     * @var array
     */
    protected $codes = [];
    
    /**
     * Currencies
     * 
     * @var array
     */
    protected $currencies = [];
    
    /**
     * Rates
     * 
     * @var array
     */
    protected $rates = [];
    
    /**
     * Constructor
     * 
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \\Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency
    )
    {
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->baseCurrency = $baseCurrency;
    }
    
    /**
     * Get code
     * 
     * @param int $storeId
     * @return string
     */
    public function getCode(int $storeId): string
    {
        if (array_key_exists($storeId, $this->codes)) {
            return $this->codes[$storeId];
        }
        return $this->codes[$storeId] = $this->storeManager->getStore($storeId)->getBaseCurrencyCode();
    }
    
    /**
     * Get
     * 
     * @param int $storeId
     * @return float
     */
    public function get(int $storeId): float
    {
        if (array_key_exists($storeId, $this->currencies)) {
            return $this->currencies[$storeId];
        }
        return $this->currencies[$storeId] = $this->currencyFactory->create()->load($this->getCode($storeId));
    }
    
    /**
     * Get rate
     * 
     * @param int $storeId
     * @return float
     */
    public function getRate(int $storeId): float
    {
        if (array_key_exists($storeId, $this->rates)) {
            return $this->rates[$storeId];
        }
        $baseCurrencyCode = $this->baseCurrency->getCode();
        $storeBaseCurrencyCode = $this->getCode($storeId);
        if ($storeBaseCurrencyCode === $baseCurrencyCode) {
            return $this->rates[$storeId] = 1;
        }
        $this->rates[$storeId] = (float) $this->baseCurrency->get()->getRate($storeBaseCurrencyCode);
        if (!$this->rates[$storeId]) {
            $this->rates[$storeId] = 1;
        }
        return $this->rates[$storeId];
    }
    
}