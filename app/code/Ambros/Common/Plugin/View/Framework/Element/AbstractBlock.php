<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\View\Framework\Element;

/**
 * Abstract block plugin
 */
class AbstractBlock extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function prepareLayout()
    {
        $this->invokeSubjectMethod('_prepareLayout');
        return $this;
    }
    
    /**
     * Around set layout
     * 
     * @param \Magento\Framework\View\Element\AbstractBlock $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\View\LayoutInterface $layout
     * @return \Magento\Framework\View\Element\AbstractBlock
     */
    public function aroundSetLayout(
        \Magento\Framework\View\Element\AbstractBlock $subject,
        \Closure $proceed,
        \Magento\Framework\View\LayoutInterface $layout
    )
    {
        $this->setSubject($subject);
        $this->setSubjectPropertyValue('_layout', $layout);
        $this->prepareLayout();
        return $this;
    }
    
    /**
     * Before to HTML
     *
     * @return $this
     */
    protected function beforeToHtml()
    {
        $this->invokeSubjectMethod('_beforeToHtml');
        return $this;
    }
    
    /**
     * After to HTML
     *
     * @param string $html
     * @return string
     */
    protected function afterToHtml($html)
    {
        return $this->invokeSubjectMethod('_afterToHtml', $html);
    }
    
    /**
     * Load cache
     *
     * @return string
     */
    protected function loadCache()
    {
        $subject = $this->getSubject();
        $inlineTranslation = $this->getSubjectPropertyValue('inlineTranslation');
        $cacheState = $this->getSubjectPropertyValue('_cacheState');
        $cache = $this->getSubjectPropertyValue('_cache');
        $sidResolver = $this->getSubjectPropertyValue('_sidResolver');
        $session = $this->getSubjectPropertyValue('_session');
        $lockQuery = $this->getSubjectPropertyValue('lockQuery');
        $collectAction = function () use ($subject, $inlineTranslation) {
            if ($subject->hasData('translate_inline')) {
                $inlineTranslation->suspend($subject->getData('translate_inline'));
            }
            $this->beforeToHtml();
            return $this->invokeSubjectMethod('_toHtml');
        };
        if (
            $this->invokeSubjectMethod('getCacheLifetime') === null || 
            !$cacheState->isEnabled(\Magento\Framework\View\Element\AbstractBlock::CACHE_GROUP)
        ) {
            $html = $collectAction();
            if ($subject->hasData('translate_inline')) {
                $inlineTranslation->resume();
            }
            return $html;
        }
        $loadAction = function () use ($subject, $cache, $sidResolver, $session) {
            $cacheKey = $subject->getCacheKey();
            $cacheData = $cache->load($cacheKey);
            if ($cacheData) {
                $cacheData = str_replace(
                    $this->invokeSubjectMethod('_getSidPlaceholder', $cacheKey),
                    $sidResolver->getSessionIdQueryParam($session).'='.$session->getSessionId(),
                    $cacheData
                );
            }
            return $cacheData;
        };
        $saveAction = function ($data) use ($subject, $inlineTranslation) {
            $this->invokeSubjectMethod('_saveCache', $data);
            if ($subject->hasData('translate_inline')) {
                $inlineTranslation->resume();
            }
        };
        return (string) $lockQuery->lockedLoadData($subject->getCacheKey(), $loadAction, $collectAction, $saveAction);
    }
    
    /**
     * Around to HTML
     * 
     * @param \Magento\Framework\View\Element\AbstractBlock $subject
     * @param \Closure $proceed
     * @return string
     */
    public function aroundToHtml(
        \Magento\Framework\View\Element\AbstractBlock $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $eventManager = $this->getSubjectPropertyValue('_eventManager');
        $scopeConfig = $this->getSubjectPropertyValue('_scopeConfig');
        $eventManager->dispatch('view_block_abstract_to_html_before', ['block' => $subject]);
        $isModuleOutputDisabled = $scopeConfig->getValue(
            'advanced/modules_disable_output/'.$subject->getModuleName(),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($isModuleOutputDisabled) {
            return '';
        }
        $transportObject = new \Magento\Framework\DataObject(['html' => $this->afterToHtml($this->loadCache())]);
        $eventManager->dispatch('view_block_abstract_to_html_after', ['block' => $this, 'transport' => $transportObject]);
        return $transportObject->getHtml();
    }
    
}