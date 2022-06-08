<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Controller\ProductAlert\Unsubscribe;

/**
 * Product price alert unsubscribe all controller plugin
 */
class PriceAll extends \Ambros\Common\Plugin\Plugin
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
     * @param \Magento\ProductAlert\Controller\Unsubscribe\PriceAll $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute(
        \Magento\ProductAlert\Controller\Unsubscribe\PriceAll $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $customerSession = $this->getSubjectPropertyValue('customerSession');
        $messageManager = $this->getSubjectPropertyValue('messageManager');
        $resultFactory = $this->getSubjectPropertyValue('resultFactory');
        $storeId = (int) $this->storeManager->getStore()->getId();
        try {
            $this->priceAlertFactory->create()->deleteCustomer($customerSession->getCustomerId(), $storeId);
            $messageManager->addSuccess(__('You will no longer receive price alerts for this product.'));
        } catch (\Exception $exception) {
            $messageManager->addException($exception, __('Unable to update the alert subscription.'));
        }
        return $resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT)
            ->setPath('customer/account/');
    }
    
}