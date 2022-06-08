<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Bundle;

/**
 * Bundle link management plugin
 */
class LinkManagement
{
    
    /**
     * Product repository
     * 
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;
    
    /**
     * Bundle resource
     * 
     * @var \Magento\Bundle\Model\ResourceModel\Bundle 
     */
    protected $bundleResource;
    
    /**
     * Option collection factory
     * 
     * @var \Magento\Bundle\Model\ResourceModel\Option\CollectionFactory
     */
    protected $optionCollectionFactory;
    
    /**
     * Selection factory
     * 
     * @var \Magento\Bundle\Model\SelectionFactory
     */
    protected $selectionFactory;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Bundle\Model\ResourceModel\Bundle $bundleResource
     * @param \Magento\Bundle\Model\ResourceModel\Option\CollectionFactory $optionCollectionFactory
     * @param \Magento\Bundle\Model\SelectionFactory $selectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Bundle\Model\ResourceModel\Bundle $bundleResource,
        \Magento\Bundle\Model\ResourceModel\Option\CollectionFactory $optionCollectionFactory,
        \Magento\Bundle\Model\SelectionFactory $selectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->productRepository = $productRepository;
        $this->bundleResource = $bundleResource;
        $this->optionCollectionFactory = $optionCollectionFactory;
        $this->selectionFactory = $selectionFactory;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Validate parent product
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $parentProduct
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     */
    protected function validateParentProduct(\Magento\Catalog\Api\Data\ProductInterface $parentProduct)
    {
        if ($parentProduct->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {
            throw new \Magento\Framework\Exception\InputException(__('The product with the "%1" SKU isn\'t a bundle product.', [$parentProduct->getSku()]));
        }
        return $this;
    }
    
    /**
     * Validate product
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     */
    protected function validateProduct(\Magento\Catalog\Api\Data\ProductInterface $product)
    {
        if ($product->isComposite()) {
            throw new \Magento\Framework\Exception\InputException(__('The bundle product can\'t contain another composite product.'));
        }
        return $this;
    }
    
    /**
     * Map link to selection
     * 
     * @param \Magento\Bundle\Api\Data\LinkInterface $link
     * @param \Magento\Bundle\Model\Selection $selection
     * @param \Magento\Catalog\Api\Data\ProductInterface $parentProduct
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return $this
     */
    protected function mapLinkToSelection(
        \Magento\Bundle\Api\Data\LinkInterface $link,
        \Magento\Bundle\Model\Selection $selection,
        \Magento\Catalog\Api\Data\ProductInterface $parentProduct,
        \Magento\Catalog\Api\Data\ProductInterface $product
    )
    {
        $selection->setProductId($product->getId());
        $selection->setParentProductId($parentProduct->getId());
        $selection->setSelectionId($link->getSelectionId());
        $selection->setOptionId($link->getOptionId());
        $selection->setPosition($link->getPosition());
        $selection->setSelectionQty($link->getQty());
        $selection->setSelectionPriceType($link->getPriceType());
        $selection->setSelectionPriceValue($link->getPrice());
        $selection->setSelectionCanChangeQty($link->getCanChangeQuantity());
        $selection->setIsDefault($link->getIsDefault());
        $storeId = $parentProduct->getStoreId();
        $selection->setStoreId($storeId);
        $selection->setWebsiteId($storeId ? $this->storeManager->getStore($storeId)->getWebsiteId() : 0);
        $selection->setDefaultPriceScope(false);
        return $this;
    }
    
    /**
     * Around save child
     * 
     * @param \Magento\Bundle\Model\LinkManagement $subject
     * @param \Closure $proceed
     * @param string $sku
     * @param \Magento\Bundle\Api\Data\LinkInterface $link
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @return bool
     */
    public function aroundSaveChild(
        \Magento\Bundle\Model\LinkManagement $subject,
        \Closure $proceed,
        $sku,
        \Magento\Bundle\Api\Data\LinkInterface $link
    )
    {
        $parentProduct = $this->productRepository->get($sku, true);
        $this->validateParentProduct($parentProduct);
        $product = $this->productRepository->get($link->getSku());
        $this->validateProduct($product);
        if (!$link->getId()) {
            throw new \Magento\Framework\Exception\InputException(__('The product link needs an ID field entered. Enter and try again.'));
        }
        $selection = $this->selectionFactory->create();
        $selection->load($link->getId());
        if (!$selection->getId()) {
            throw new \Magento\Framework\Exception\InputException(__('The product link with the "%1" ID field wasn\'t found. Verify the ID and try again.', [$link->getId()]));
        }
        $this->mapLinkToSelection($link, $selection, $parentProduct, $product);
        try {
            $selection->save();
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('Could not save child: "%1"', $exception->getMessage()), $exception);
        }
        return true;
    }
    
    /**
     * Around add child
     * 
     * @param \Magento\Bundle\Model\LinkManagement $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Api\Data\ProductInterface $parentProduct
     * @param int $optionId
     * @param \Magento\Bundle\Api\Data\LinkInterface $link
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @return int
     */
    public function aroundAddChild(
        \Magento\Bundle\Model\LinkManagement $subject,
        \Closure $proceed,
        \Magento\Catalog\Api\Data\ProductInterface $parentProduct,
        $optionId,
        \Magento\Bundle\Api\Data\LinkInterface $link
    )
    {
        $this->validateParentProduct($parentProduct);
        $optionCollection = $this->optionCollectionFactory->create();
        $optionCollection->setIdFilter($optionId);
        $optionCollection->setProductLinkFilter($parentProduct->getId());
        $option = $optionCollection->getFirstItem();
        if (!$option->getId()) {
            $this->throwInputException(__('Product with specified sku: "%1" does not contain option: "%2"', [$parentProduct->getSku(), $optionId]));
        }
        $selectionsData = $this->bundleResource->getSelectionsData($parentProduct->getId());
        $product = $this->productRepository->get($link->getSku());
        $this->validateProduct($product);
        if ($selectionsData) {
            foreach ($selectionsData as $selectionData) {
                if (
                    $selectionData['option_id'] == $optionId &&
                    $selectionData['product_id'] == $product->getId() &&
                    $selectionData['parent_product_id'] == $parentProduct->getId()
                ) {
                    if (!$parentProduct->getCopyFromView()) {
                        throw new \Magento\Framework\Exception\CouldNotSaveException(
                            __(
                                'Child with specified sku: "%1" already assigned to product: "%2"',
                                [$link->getSku(), $parentProduct->getSku()]
                            )
                        );
                    }
                    return $this->selectionFactory->create()->load($product->getId());
                }
            }
        }
        $selection = $this->selectionFactory->create();
        $this->mapLinkToSelection($link, $selection, $parentProduct, $product);
        $selection->setOptionId($optionId);
        try {
            $selection->save();
            $this->bundleResource->addProductRelation($parentProduct->getId(), $product->getId());
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('Could not save child: "%1"', $exception->getMessage()), $exception);
        }
        return $selection->getId();
    }
    
}