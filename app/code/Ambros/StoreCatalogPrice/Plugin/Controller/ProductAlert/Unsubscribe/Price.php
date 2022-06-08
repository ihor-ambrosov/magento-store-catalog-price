<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Controller\ProductAlert\Unsubscribe;

/**
 * Product price alert unsubscribe controller plugin
 */
class Price extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price alert factory
     * 
     * @var \Magento\ProductAlert\Model\PriceFactory 
     */
    protected $priceAlertFactory;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Magento\ProductAlert\Model\PriceFactory $priceAlertFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Magento\ProductAlert\Model\PriceFactory $priceAlertFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceAlertFactory = $priceAlertFactory;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Around execute
     * 
     * @param \Magento\ProductAlert\Controller\Unsubscribe\Price $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute(
        \Magento\ProductAlert\Controller\Unsubscribe\Price $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $productId = (int) $subject->getRequest()->getParam('product');
        $productRepository = $this->getSubjectPropertyValue('productRepository');
        $customerSession = $this->getSubjectPropertyValue('customerSession');
        $messageManager = $this->getSubjectPropertyValue('messageManager');
        $resultFactory = $this->getSubjectPropertyValue('resultFactory');
        $store = $this->storeManager->getStore();
        $resultRedirect = $resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        if (!$productId) {
            $resultRedirect->setPath('/');
            return $resultRedirect;
        }
        try {
            $product = $productRepository->getById($productId);
            if (!$product->isVisibleInCatalog()) {
                $messageManager->addErrorMessage(__('The product wasn\'t found. Verify the product and try again.'));
                $resultRedirect->setPath('customer/account/');
                return $resultRedirect;
            }
            $priceAlert = $this->priceAlertFactory->create()
                ->setCustomerId((int) $customerSession->getCustomerId())
                ->setProductId((int) $product->getId())
                ->setWebsiteId((int) $store->getWebsiteId())
                ->setStoreId((int) $store->getId())
                ->loadByParam();
            if ($priceAlert->getId()) {
                $priceAlert->delete();
            }
            $messageManager->addSuccessMessage(__('You deleted the alert subscription.'));
        } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
            $messageManager->addErrorMessage(__('The product wasn\'t found. Verify the product and try again.'));
            $resultRedirect->setPath('customer/account/');
            return $resultRedirect;
        } catch (\Exception $exception) {
            $messageManager->addExceptionMessage(
                $exception,
                __('The alert subscription couldn\'t update at this time. Please try again later.')
            );
        }
        $resultRedirect->setUrl($product->getProductUrl());
        return $resultRedirect;
    }
    
}