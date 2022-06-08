<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Elasticsearch\Adapter\FieldMapper\Product\FieldProvider\FieldName\Resolver;

/**
 * Price field name resolver plugin
 */
class Price
{
    
    /**
     * Customer session
     * 
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     * 
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Around get field name
     * 
     * @param \Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldName\Resolver\Price $subject
     * @param \Closure $proceed
     * @param \Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeAdapter $attribute
     * @param array $context
     * @return string
     */
    public function aroundGetFieldName(
        \Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldName\Resolver\Price $subject,
        \Closure $proceed,
        \Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeAdapter $attribute,
        $context = []
    ): ?string
    {
        if ($attribute->getAttributeCode() !== 'price') {
            return null;
        }
        $customerGroupId = !empty($context['customerGroupId']) ? 
            $context['customerGroupId'] : $this->customerSession->getCustomerGroupId();
        $storeId = !empty($context['websiteId']) ? 
            $context['websiteId'] : $this->storeManager->getStore()->getId();
        return 'price_'.$customerGroupId.'_'.$storeId;
    }
    
}