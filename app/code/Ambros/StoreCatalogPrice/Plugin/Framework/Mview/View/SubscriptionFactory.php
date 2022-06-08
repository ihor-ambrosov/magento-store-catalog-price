<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Framework\Mview\View;

/**
 * M-view view subscription factory plugin
 */
class SubscriptionFactory
{
    
    /**
     * Before create
     * 
     * @param \Magento\Framework\Mview\View\SubscriptionFactory $subject
     * @param array $data
     */
    public function beforeCreate(
        \Magento\Framework\Mview\View\SubscriptionFactory $subject,
        array $data = []
    )
    {
        if ($data['tableName'] === 'catalog_product_entity_tier_price') {
            $data['tableName'] = 'ambros_store__catalog_product_entity_tier_price';
        }
        return [$data];
    }

}