<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Block\Catalog\Adminhtml\Product\Edit\Tab\Alerts;

/**
 * Product price alert tab plugin
 */
class Price extends \Ambros\Common\Plugin\Block\Backend\Widget\Grid\Extended
{
    
    /**
     * Prepare collection
     *
     * @return \Magento\Catalog\Block\Adminhtml\Product\Edit\Tab\Alerts\Price
     */
    protected function prepareCollection()
    {
        $subject = $this->getSubject();
        $request = $subject->getRequest();
        $storeManager = $this->getSubjectPropertyValue('_storeManager');
        $moduleManager = $this->getSubjectPropertyValue('moduleManager');
        $priceFactory = $this->getSubjectPropertyValue('_priceFactory');
        $productId = (int) $request->getParam('id');
        $requestStoreId = (int) $request->getParam('store');
        if ($requestStoreId) {
            $storeId = (int) $storeManager->getStore($requestStoreId)->getId();
        } else {
            $storeId = 0;
        }
        if ($moduleManager->isEnabled('Magento_ProductAlert')) {
            $subject->setCollection($priceFactory->create()->getCustomerCollection()->join($productId, $storeId));
        }
        parent::prepareCollection();
        return $subject;
    }
    
}