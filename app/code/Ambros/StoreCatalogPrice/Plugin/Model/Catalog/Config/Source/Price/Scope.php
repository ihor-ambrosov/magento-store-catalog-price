<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Config\Source\Price;

/**
 * Price scope configuration source plugin
 */
class Scope
{
    
    /**
     * After to option array
     * 
     * @param \Magento\Catalog\Model\Config\Source\Price\Scope $subject
     * @param array $result
     * @return array
     */
    public function afterToOptionArray(
        \Magento\Catalog\Model\Config\Source\Price\Scope $subject,
        $result
    )
    {
        $result[] = [
            'value' => (string) \Ambros\StoreCommon\Model\Catalog\Product\PriceScope::PRICE_SCOPE_STORE,
            'label' => __('Store View'),
        ];
        return $result;
    }
    
}