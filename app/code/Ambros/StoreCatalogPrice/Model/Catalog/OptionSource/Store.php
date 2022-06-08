<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Catalog\OptionSource;

/**
 * Store option source
 */
class Store implements \Magento\Framework\Data\OptionSourceInterface
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope 
     */
    protected $priceScope;
    
    /**
     * Base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency
     */
    protected $baseCurrency;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->priceScope = $priceScope;
        $this->baseCurrency = $baseCurrency;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Get option
     * 
     * @param string $label
     * @param mixed $value
     * @param bool $disableTemplate
     * @return array
     */
    protected function getOption(string $label, $value, bool $disableTemplate = null): array
    {
        $option = ['label' => $label, 'value' => $value];
        if ($disableTemplate !== null) {
            $option['__disableTmpl'] = $disableTemplate;
        }
        return $option;
    }
    
    /**
     * Get store options
     * 
     * @param bool $empty
     * @param bool $all
     * @return array
     */
    protected function getStoreOptions($empty = false, $all = false): array
    {
        $options = [];
        $websites = $this->storeManager->getWebsites();
        $groups = $this->storeManager->getGroups();
        $stores = $this->storeManager->getStores();
        if ($empty) {
            $options[] = $this->getOption('', '');
        }
        if ($all) {
            $options[] = $this->getOption((string) __('All Store Views ['.$this->baseCurrency->getCode().']'), 0);
        }
        $indent = str_repeat(html_entity_decode('&#160;', ENT_NOQUOTES, 'UTF-8'), 4);
        foreach ($websites as $website) {
            $websiteValues = [];
            foreach ($groups as $group) {
                $groupValues = [];
                if ($group->getWebsiteId() != $website->getId()) {
                    continue;
                }
                foreach ($stores as $store) {
                    if ($store->getGroupId() != $group->getId()) {
                        continue;
                    }
                    $groupValues[] = $this->getOption(
                        $indent.$store->getName().' ['.$store->getBaseCurrencyCode().']',
                        (int) $store->getId()
                    );
                }
                if (!empty($groupValues)) {
                    $websiteValues[] = $this->getOption($indent.$group->getName(), $groupValues, true);
                }
            }
            if (!empty($websiteValues)) {
                $options[] = $this->getOption($website->getName(), $websiteValues, true);
            }
        }
        return $options;
    }
    
    /**
     * Get website options
     * 
     * @param bool $empty
     * @param bool $all
     * @return array
     */
    protected function getWebsiteOptions($empty = false, $all = false): array
    {
        $options = [];
        $websites = $this->storeManager->getWebsites();
        if ($empty) {
            $options[] = $this->getOption('', '');
        }
        if ($all) {
            $options[] = $this->getOption((string) __('All Websites ['.$this->baseCurrency->getCode().']'), 0);
        }
        foreach ($websites as $website) {
            $defaultStore = $website->getDefaultStore();
            $options[] = $this->getOption(
                $website->getName().' ['.$defaultStore->getBaseCurrencyCode().']',
                (int) $defaultStore->getId()
            );
        }
        return $options;
    }
    
    /**
     * Get global options
     * 
     * @param bool $empty
     * @param bool $all
     * @return array
     */
    protected function getGlobalOptions($empty = false, $all = false): array
    {
        $options = [];
        if ($empty) {
            $options[] = $this->getOption('', '');
        }
        if ($all) {
            $options[] = $this->getOption((string) __('All Store Views ['.$this->baseCurrency->getCode().']'), 0);
        }
        return $options;
    }
    
    /**
     * Get options
     * 
     * @param bool $empty
     * @param bool $all
     * @return array
     */
    public function getOptions($empty = false, $all = false): array
    {
        if ($this->priceScope->isStore()) {
            return $this->getStoreOptions($empty, $all);
        } elseif ($this->priceScope->isWebsite()) {
            return $this->getWebsiteOptions($empty, $all);
        } else {
            return $this->getGlobalOptions($empty, $all);
        }
    }
    
    /**
     * To option array
     * 
     * @return array
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        return $this->options = $this->getOptions();
    }
    
}