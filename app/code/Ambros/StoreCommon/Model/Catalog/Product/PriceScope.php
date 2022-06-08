<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Model\Catalog\Product;

/**
 * Product price scope
 */
class PriceScope
{
    
    const PRICE_SCOPE_GLOBAL = 0;
    const PRICE_SCOPE_WEBSITE = 1;
    const PRICE_SCOPE_STORE = 2;
    
    const XML_PATH_PRICE_SCOPE = 'catalog/price/scope';
    
    /**
     * Scope configuration
     * 
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Get scope
     * 
     * @return int|null
     */
    public function get(): ?int
    {
        $scope = $this->scopeConfig->getValue(static::XML_PATH_PRICE_SCOPE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $scope !== null ? (int) $scope : null;
    }
    
    /**
     * Is global scope
     * 
     * @return bool
     */
    public function isGlobal(): bool
    {
        return $this->get() == static::PRICE_SCOPE_GLOBAL;
    }
    
    /**
     * Is website scope
     * 
     * @return bool
     */
    public function isWebsite(): bool
    {
        return $this->get() == static::PRICE_SCOPE_WEBSITE;
    }
    
    /**
     * Is store scope
     * 
     * @return bool
     */
    public function isStore(): bool
    {
        return $this->get() == static::PRICE_SCOPE_STORE;
    }
    
    /**
     * Get by attribute
     * 
     * @param \Magento\Catalog\Api\Data\ProductAttributeInterface $attribute
     * @return int
     */
    public function getByAttribute(\Magento\Catalog\Api\Data\ProductAttributeInterface $attribute): int
    {
        if ($attribute->isScopeStore()) {
            return static::PRICE_SCOPE_STORE;
        } else if ($attribute->isScopeWebsite()) {
            return static::PRICE_SCOPE_WEBSITE;
        } else {
            return static::PRICE_SCOPE_GLOBAL;
        }
    }
    
    /**
     * Get store ID
     * 
     * @param int $storeId
     * @param int $scope
     * @return int
     */
    public function getStoreId(int $storeId = null, int $scope = null): int
    {
        if ($scope === null) {
            $scope = $this->get();
        }
        if ($scope === static::PRICE_SCOPE_STORE) {
            return (int) $this->storeManager->getStore($storeId)->getId();
        } else if ($scope === static::PRICE_SCOPE_WEBSITE) {
            return (int) $this->storeManager->getStore($storeId)->getWebsite()->getDefaultStore()->getId();
        } else {
            return (int) $this->storeManager->getStore($storeId)->getId();
        }
    }
    
    /**
     * Get store ID by attribute
     * 
     * @param \Magento\Catalog\Api\Data\ProductAttributeInterface $attribute
     * @param int $storeId
     * @return int
     */
    public function getStoreIdByAttribute(
        \Magento\Catalog\Api\Data\ProductAttributeInterface $attribute,
        int $storeId = null
    ): int
    {
        return $this->getStoreId($storeId, $this->getByAttribute($attribute));
    }
    
    /**
     * Get store IDs
     * 
     * @param int $storeId
     * @param int $scope
     * @return array
     */
    public function getStoreIds(int $storeId = null, int $scope = null): array
    {
        if ($scope === null) {
            $scope = $this->get();
        }
        if ($scope === static::PRICE_SCOPE_STORE) {
            return [$this->storeManager->getStore($storeId)->getId()];
        } else if ($scope === static::PRICE_SCOPE_WEBSITE) {
            return (array) $this->storeManager->getStore($storeId)->getWebsite()->getStoreIds();
        } else {
            return [];
        }
    }
    
}