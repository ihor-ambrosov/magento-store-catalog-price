<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Price;

/**
 * Tier price factory plugin
 */
class TierPriceFactory extends \Ambros\Common\Plugin\Plugin
{
    /**
     * @var \Magento\Catalog\Api\Data\TierPriceInterfaceFactory
     */
    private $tierPriceFactory;
    
    /**
     * @var \Magento\Catalog\Model\Product\Price\TierPricePersistence
     */
    private $tierPricePersistence;
    
    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var string
     */
    private $allGroupsValue = 'all groups';

    /**
     * @var int
     */
    private $allGroupsId = 1;

    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Magento\Catalog\Api\Data\TierPriceInterfaceFactory $tierPriceFactory
     * @param \Magento\Catalog\Model\Product\Price\TierPricePersistence $tierPricePersistence
     * @param \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Magento\Catalog\Api\Data\TierPriceInterfaceFactory $tierPriceFactory,
        \Magento\Catalog\Model\Product\Price\TierPricePersistence $tierPricePersistence,
        \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository
    )
    {
        parent::__construct($wrapperFactory);
        $this->tierPriceFactory = $tierPriceFactory;
        $this->tierPricePersistence = $tierPricePersistence;
        $this->customerGroupRepository = $customerGroupRepository;
    }

    /**
     * Around create
     * 
     * @param \Magento\Catalog\Model\Product\Price\TierPriceFactory $subject
     * @param \Closure $proceed
     * @param array $rawPrice
     * @param string $sku
     * @return \Magento\Catalog\Api\Data\TierPriceInterface
     */
    public function aroundCreate(
        \Magento\Catalog\Model\Product\Price\TierPriceFactory $subject,
        \Closure $proceed,
        array $rawPrice,
        $sku
    )
    {
        $this->setSubject($subject);
        $price = $rawPrice['percentage_value'] ?? $rawPrice['value'];
        $priceType = isset($rawPrice['percentage_value']) ? 
                \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_DISCOUNT :
                \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_FIXED;
        $customerGroup = $rawPrice['all_groups'] == $this->allGroupsId ? $this->allGroupsValue : $this->customerGroupRepository->getById($rawPrice['customer_group_id'])->getCode();
        $storeId = (int) $rawPrice['store_id'] ?? 0;
        $tierPrice = $this->tierPriceFactory->create();
        $tierPrice->setPrice($price);
        $tierPrice->setPriceType($priceType);
        $tierPrice->getExtensionAttributes()->setStoreId($storeId);
        $tierPrice->setSku($sku);
        $tierPrice->setCustomerGroup($customerGroup);
        $tierPrice->setQuantity($rawPrice['qty']);
        return $tierPrice;
    }
    
    /**
     * Around create skeleton
     * 
     * @param \Magento\Catalog\Model\Product\Price\TierPriceFactory $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param int $id
     * @return array
     */
    public function aroundCreateSkeleton(
        \Magento\Catalog\Model\Product\Price\TierPriceFactory $subject,
        \Closure $proceed,
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        $id
    )
    {
        $this->setSubject($subject);
        return [
            $this->tierPricePersistence->getEntityLinkField() => $id,
            'all_groups' => $this->invokeSubjectMethod('retrievePriceForAllGroupsValue', $price),
            'customer_group_id' => $this->invokeSubjectMethod('retrievePriceForAllGroupsValue', $price) === $this->allGroupsId ? 
                0: 
                $this->invokeSubjectMethod('retrieveGroupValue', strtolower($price->getCustomerGroup())),
            'qty' => $price->getQuantity(),
            'value' => $price->getPriceType() === \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_FIXED ? $price->getPrice() : 0.00,
            'percentage_value' => $price->getPriceType() === \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_DISCOUNT ? $price->getPrice() : null,
            'store_id' => (int) $price->getExtensionAttributes()->getStoreId()
        ];
    }
}