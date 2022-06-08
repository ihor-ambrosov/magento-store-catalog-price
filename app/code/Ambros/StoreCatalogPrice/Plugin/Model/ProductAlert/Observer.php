<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\ProductAlert;

/**
 * Product alert observer plugin
 */
class Observer extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price alert collection wrapper factory
     * 
     * @var \Ambros\StoreCatalogPrice\Wrapper\Model\ProductAlert\ResourceModel\Price\CollectionFactory
     */
    protected $priceAlertCollectionWrapperFactory;
    
    /**
     * Stores
     * 
     * @var array
     */
    protected $stores;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCatalogPrice\Wrapper\Model\ProductAlert\ResourceModel\Price\CollectionFactory $priceAlertCollectionWrapperFactory
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Wrapper\Model\ProductAlert\ResourceModel\Price\CollectionFactory $priceAlertCollectionWrapperFactory
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceAlertCollectionWrapperFactory = $priceAlertCollectionWrapperFactory;
    }
    
    /**
     * Add error
     * 
     * @param string $error
     * @return $this
     */
    protected function addError(string $error)
    {
        $errors = $this->getSubjectPropertyValue('_errors');
        $errors[] = $error;
        $this->setSubjectPropertyValue('_errors', $errors);
        return $this;
    }
    
    /**
     * Add exception error
     * 
     * @param \Exception $exception
     * @return $this
     */
    protected function addExceptionError(\Exception $exception)
    {
        $this->addError($exception->getMessage());
        return $this;
    }
    
    /**
     * Get stores
     *
     * @return array
     * @throws \Exception
     */
    protected function getStores(): array
    {
        if ($this->stores !== null) {
            return $this->stores;
        }
        try {
            $this->stores = $this->getSubjectPropertyValue('_storeManager')->getStores();
        } catch (\Exception $exception) {
            $this->addExceptionError($exception);
            throw $exception;
        }
        return $this->stores;
    }
    
    /**
     * Check if is price alert allowed
     * 
     * @param int $storeId
     * @return bool
     */
    protected function isPriceAlertAllowed(int $storeId): bool
    {
        return $this->getSubjectPropertyValue('_scopeConfig')->getValue(
            \Magento\ProductAlert\Model\Observer::XML_PATH_PRICE_ALLOW,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get price alert collection
     * 
     * @param int $storeId
     * @return \Magento\ProductAlert\Model\ResourceModel\Price\Collection
     * @throws \Exception
     */
    protected function getPriceAlertCollection(int $storeId)
    {
        try {
            $priceAlertCollection = $this->getSubjectPropertyValue('_priceColFactory')->create();
            $priceAlertCollectionWrapper = $priceAlertCollection->wrapper ?? 
                $this->priceAlertCollectionWrapperFactory->create()->setObject($priceAlertCollection);
            $priceAlertCollectionWrapper->addStoreFilter($storeId);
            return $priceAlertCollection->setCustomerOrder();
        } catch (\Exception $exception) {
            $this->addExceptionError($exception);
            throw $exception;
        }
    }
    
    /**
     * Process store price
     * 
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @param \Magento\ProductAlert\Model\Email $email
     * @return $this
     */
    protected function processStorePrice(\Magento\Store\Api\Data\StoreInterface $store, \Magento\ProductAlert\Model\Email $email)
    {
        $storeId = (int) $store->getId();
        if (!$this->isPriceAlertAllowed($storeId)) {
            return $this;
        }
        $priceAlertCollection = $this->getPriceAlertCollection($storeId);
        $email->setWebsite($store->getWebsite());
        $email->setStoreId($storeId);
        $previousCustomer = null;
        $customerRepository = $this->getSubjectPropertyValue('customerRepository');
        $productRepository = $this->getSubjectPropertyValue('productRepository');
        $catalogData = $this->getSubjectPropertyValue('_catalogData');
        $dateFactory = $this->getSubjectPropertyValue('_dateFactory');
        foreach ($priceAlertCollection as $priceAlert) {
            try {
                $customerId = $priceAlert->getCustomerId();
                if (!$previousCustomer || $previousCustomer->getId() != $customerId) {
                    $customer = $customerRepository->getById($customerId);
                    if ($previousCustomer) {
                        $email->send();
                    }
                    if (!$customer) {
                        continue;
                    }
                    $previousCustomer = $customer;
                    $email->clean();
                    $email->setCustomerData($customer);
                } else {
                    $customer = $previousCustomer;
                }
                $product = $productRepository->getById($priceAlert->getProductId(), false, $storeId);
                $product->setCustomerGroupId($customer->getGroupId());
                $finalPrice = $product->getFinalPrice();
                if ($priceAlert->getPrice() > $finalPrice) {
                    $product->setFinalPrice($catalogData->getTaxPrice($product, $finalPrice));
                    $product->setPrice($catalogData->getTaxPrice($product, $product->getPrice()));
                    $email->addPriceProduct($product);
                    $priceAlert->setPrice($finalPrice);
                    $priceAlert->setLastSendDate($dateFactory->create()->gmtDate());
                    $priceAlert->setSendCount($priceAlert->getSendCount() + 1);
                    $priceAlert->setStatus(1);
                    $priceAlert->save();
                }
            } catch (\Exception $exception) {
                $this->addExceptionError($exception);
                throw $exception;
            }
        }
        if ($previousCustomer) {
            try {
                $email->send();
            } catch (\Exception $exception) {
                $this->addExceptionError($exception);
                throw $exception;
            }
        }
        return $this;
    }
    
    /**
     * Process price
     *
     * @param \Magento\ProductAlert\Model\Email $email
     * @return $this
     * @throws \Exception
     */
    protected function processPrice(\Magento\ProductAlert\Model\Email $email)
    {
        $email->setType('price');
        foreach ($this->getStores() as $store) {
            $this->processStorePrice($store, $email);
        }
        return $this;
    }
    
    /**
     * Around process
     * 
     * @param \Magento\ProductAlert\Model\Observer $subject
     * @param \Closure $proceed
     * @return \Magento\ProductAlert\Model\Observer
     */
    public function aroundProcess(
        \Magento\ProductAlert\Model\Observer $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $email = $this->getSubjectPropertyValue('_emailFactory')->create();
        $this->processPrice($email);
        $this->invokeSubjectMethod('_processStock', $email);
        $this->invokeSubjectMethod('_sendErrorEmail');
        return $subject;
    }
    
}