<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Ui\Catalog\DataProvider\Product\Form\Modifier;

/**
 * Currency symbol provider
 */
class CurrencySymbolProvider
{
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    private $priceScope;
    
    /**
     * Locator
     *
     * @var \Magento\Catalog\Model\Locator\LocatorInterface
     */
    private $locator;

    /**
     * Locale currency
     *
     * @var \Magento\Framework\Locale\CurrencyInterface
     */
    private $localeCurrency;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * Constructor
     *
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Magento\Catalog\Model\Locator\LocatorInterface $locator
     * @param \Magento\Framework\Locale\CurrencyInterface $localeCurrency
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Magento\Catalog\Model\Locator\LocatorInterface $locator,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->priceScope = $priceScope;
        $this->locator = $locator;
        $this->localeCurrency = $localeCurrency;
        $this->storeManager = $storeManager;
    }

    /**
     * Get currency symbol
     *
     * @param string $code
     * @return string
     */
    private function getCurrencySymbol(string $code): string
    {
        $currency = $this->localeCurrency->getCurrency($code);
        return $currency->getSymbol() ? $currency->getSymbol() : $currency->getShortName();
    }

    /**
     * Get currencies per store
     *
     * @return array
     */
    public function getCurrenciesPerStore(): array
    {
        $baseCurrency = $this->locator->getStore()->getBaseCurrency();
        $storeCurrencySymbol[0] = $baseCurrency->getCurrencySymbol() ?? $baseCurrency->getCurrencyCode();
        if ($this->priceScope->isGlobal()) {
            return $storeCurrencySymbol;
        }
        $product = $this->locator->getProduct();
        $productStoreIds = $product->getStoreIds();
        foreach ($this->storeManager->getStores() as $store) {
            if (!in_array($store->getId(), $productStoreIds)) {
                continue;
            }
            $storeCurrencySymbol[$store->getId()] = $this->getCurrencySymbol($store->getBaseCurrencyCode());
        }
        return $storeCurrencySymbol;
    }

    /**
     * Get default currency
     *
     * @return string
     */
    public function getDefaultCurrency(): string
    {
        $baseCurrency = $this->locator->getStore()->getBaseCurrency();
        return $baseCurrency->getCurrencySymbol() ?? $baseCurrency->getCurrencyCode();
    }
}