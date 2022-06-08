<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Price\Validation;

/**
 * Tier price validator plugin
 */
class TierPriceValidator extends \Ambros\Common\Plugin\Plugin
{
    /**
     * @var \Magento\Catalog\Model\ProductIdLocatorInterface
     */
    private $productIdLocator;
    
    /**
     * @var \Magento\Catalog\Model\Product\Price\Validation\InvalidSkuProcessor
     */
    private $invalidSkuProcessor;
    
    /**
     * @var \Magento\Catalog\Model\Product\Price\Validation\Result
     */
    private $validationResult;
    
    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;
    
    /**
     * @var string
     */
    private $allGroupsValue = 'all groups';
    
    /**
     * @var string
     */
    private $allStoresValue = '0';

    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Magento\Catalog\Model\ProductIdLocatorInterface $productIdLocator
     * @param \Magento\Catalog\Model\Product\Price\Validation\InvalidSkuProcessor $invalidSkuProcessor
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @param \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Magento\Catalog\Model\ProductIdLocatorInterface $productIdLocator,
        \Magento\Catalog\Model\Product\Price\Validation\InvalidSkuProcessor $invalidSkuProcessor,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult,
        \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository
    ) {
        parent::__construct($wrapperFactory);
        $this->productIdLocator = $productIdLocator;
        $this->invalidSkuProcessor = $invalidSkuProcessor;
        $this->validationResult = $validationResult;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->storeRepository = $storeRepository;
    }

    /**
     * @param \Magento\Catalog\Model\Product\Price\Validation\TierPriceValidator $subject
     * @param \Closure $proceed
     * @param array $prices
     * @param array $existingPrices
     * @return \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     */
    public function aroundRetrieveValidationResult(
        \Magento\Catalog\Model\Product\Price\Validation\TierPriceValidator $subject,
        \Closure $proceed,
        array $prices,
        array $existingPrices = []
    )
    {
        $this->setSubject($subject);
        $allowedProductTypes = $this->getSubjectPropertyValue('allowedProductTypes');
        $validationResult = clone $this->validationResult;
        $skus = array_unique(array_map(function ($price) { return $price->getSku(); }, $prices));
        $skuDiff = $this->invalidSkuProcessor->retrieveInvalidSkuList($skus, $allowedProductTypes);
        $idsBySku = $this->productIdLocator->retrieveProductIdsBySkus($skus);
        $pricesBySku = [];
        foreach ($prices as $price) {
            $pricesBySku[$price->getSku()][] = $price;
        }
        foreach ($prices as $key => $price) {
            $this->checkSku($price, $key, $skuDiff, $validationResult);
            $this->checkPrice($price, $key, $validationResult);
            $ids = isset($idsBySku[$price->getSku()]) ? $idsBySku[$price->getSku()] : [];
            $this->checkPriceType($price, $ids, $key, $validationResult);
            $this->checkQuantity($price, $key, $validationResult);
            $this->checkStore($price, $key, $validationResult);
            if (isset($pricesBySku[$price->getSku()])) {
                $this->checkUnique($price, $pricesBySku, $key, $validationResult);
            }
            $this->checkUnique($price, $existingPrices, $key, $validationResult);
            $this->checkGroup($price, $key, $validationResult);
        }
        return $validationResult;
    }

    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param int $key
     * @param array $invalidSkus
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkSku(
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        $key,
        array $invalidSkus,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    ) {
        if (!$price->getSku() || in_array($price->getSku(), $invalidSkus)) {
            $validationResult->addFailedItem(
                $key,
                __(
                    'Invalid attribute SKU = %SKU. Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                    [
                        'SKU' => '%SKU',
                        'storeId' => '%storeId',
                        'customerGroup' => '%customerGroup',
                        'qty' => '%qty'
                    ]
                ),
                [
                    'SKU' => $price->getSku(),
                    'storeId' => $price->getExtensionAttributes()->getStoreId(),
                    'customerGroup' => $price->getCustomerGroup(),
                    'qty' => $price->getQuantity()
                ]
            );
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param int $key
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkPrice(
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        $key,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    )
    {
        if (
            null === $price->getPrice() || 
            $price->getPrice() < 0 || 
            ($price->getPriceType() === \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_DISCOUNT && $price->getPrice() > 100)
        ) {
            $validationResult->addFailedItem(
                $key,
                __(
                    'Invalid attribute Price = %price. Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                    [
                        'price' => '%price',
                        'SKU' => '%SKU',
                        'storeId' => '%storeId',
                        'customerGroup' => '%customerGroup',
                        'qty' => '%qty'
                    ]
                ),
                [
                    'price' => $price->getPrice(),
                    'SKU' => $price->getSku(),
                    'storeId' => $price->getExtensionAttributes()->getStoreId(),
                    'customerGroup' => $price->getCustomerGroup(),
                    'qty' => $price->getQuantity()
                ]
            );
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param array $ids
     * @param int $key
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkPriceType(
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        array $ids,
        $key,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    )
    {
        if (
            !in_array($price->getPriceType(), [\Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_FIXED, \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_DISCOUNT]) || 
            (array_search(\Magento\Catalog\Model\Product\Type::TYPE_BUNDLE, $ids) && $price->getPriceType() !== \Magento\Catalog\Api\Data\TierPriceInterface::PRICE_TYPE_DISCOUNT)
        ) {
            $validationResult->addFailedItem(
                $key,
                __(
                    'Invalid attribute Price Type = %priceType. Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                    [
                        'price' => '%price',
                        'SKU' => '%SKU',
                        'storeId' => '%storeId',
                        'customerGroup' => '%customerGroup',
                        'qty' => '%qty'
                    ]
                ),
                [
                    'priceType' => $price->getPriceType(),
                    'SKU' => $price->getSku(),
                    'storeId' => $price->getExtensionAttributes()->getStoreId(),
                    'customerGroup' => $price->getCustomerGroup(),
                    'qty' => $price->getQuantity()
                ]
            );
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param int $key
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkQuantity(
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        $key,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    )
    {
        if ($price->getQuantity() < 1) {
            $validationResult->addFailedItem(
                $key,
                __(
                    'Invalid attribute Quantity = %qty. Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                    [
                        'SKU' => '%SKU',
                        'storeId' => '%storeId',
                        'customerGroup' => '%customerGroup',
                        'qty' => '%qty'
                    ]
                ),
                [
                    'SKU' => $price->getSku(),
                    'storeId' => $price->getExtensionAttributes()->getStoreId(),
                    'customerGroup' => $price->getCustomerGroup(),
                    'qty' => $price->getQuantity()
                ]
            );
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param int $key
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkStore(
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        $key,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    )
    {
        try {
            $this->storeRepository->getById($price->getExtensionAttributes()->getStoreId());
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $validationResult->addFailedItem(
                $key,
                __(
                    'Invalid attribute Store ID = %storeId. Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                    [
                        'SKU' => '%SKU',
                        'storeId' => '%storeId',
                        'customerGroup' => '%customerGroup',
                        'qty' => '%qty',
                    ]
                ),
                [
                    'SKU' => $price->getSku(),
                    'storeId' => $price->getExtensionAttributes()->getStoreId(),
                    'customerGroup' => $price->getCustomerGroup(),
                    'qty' => $price->getQuantity(),
                ]
            );
        }
    }
    
    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $price
     * @param int $key
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkGroup(
        \Magento\Catalog\Api\Data\TierPriceInterface $price,
        $key,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    )
    {
        $customerGroup = strtolower($price->getCustomerGroup());
        if ($customerGroup != $this->allGroupsValue && false === $this->invokeSubjectMethod('retrieveGroupValue', $customerGroup)) {
            $validationResult->addFailedItem(
                $key,
                __(
                    'No such entity with Customer Group = %customerGroup. Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                    [
                        'SKU' => '%SKU',
                        'storeId' => '%storeId',
                        'customerGroup' => '%customerGroup',
                        'qty' => '%qty'
                    ]
                ),
                [
                    'SKU' => $price->getSku(),
                    'storeId' => $price->getExtensionAttributes()->getStoreId(),
                    'customerGroup' => $price->getCustomerGroup(),
                    'qty' => $price->getQuantity()
                ]
            );
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\TierPriceInterface $tierPrice
     * @param array $prices
     * @param int $key
     * @param \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
     * @return void
     */
    private function checkUnique(
        \Magento\Catalog\Api\Data\TierPriceInterface $tierPrice,
        array $prices,
        $key,
        \Magento\Catalog\Model\Product\Price\Validation\Result $validationResult
    )
    {
        if (isset($prices[$tierPrice->getSku()])) {
            foreach ($prices[$tierPrice->getSku()] as $price) {
                if (
                    strtolower($price->getCustomerGroup()) === strtolower($tierPrice->getCustomerGroup()) && 
                    $price->getQuantity() == $tierPrice->getQuantity() && 
                    (
                        (
                            $price->getExtensionAttributes()->getStoreId() == $this->allStoresValue || 
                            $tierPrice->getExtensionAttributes()->getStoreId() == $this->allStoresValue
                        ) && $price->getExtensionAttributes()->getStoreId() != $tierPrice->getExtensionAttributes()->getStoreId()
                    )
                ) {
                    $validationResult->addFailedItem(
                        $key,
                        __(
                            'We found a duplicate website, tier price, customer group and quantity: Customer Group = %customerGroup, Store ID = %storeId, Quantity = %qty. '.
                            'Row ID: SKU = %SKU, Store ID: %storeId, Customer Group: %customerGroup, Quantity: %qty.',
                            [
                                'SKU' => '%SKU',
                                'storeId' => '%storeId',
                                'customerGroup' => '%customerGroup',
                                'qty' => '%qty'
                            ]
                        ),
                        [
                            'SKU' => $price->getSku(),
                            'storeId' => $price->getExtensionAttributes()->getStoreId(),
                            'customerGroup' => $price->getCustomerGroup(),
                            'qty' => $price->getQuantity()
                        ]
                    );
                }
            }
        }
    }
}