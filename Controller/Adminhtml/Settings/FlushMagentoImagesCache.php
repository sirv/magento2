<?php

namespace Sirv\Magento2\Controller\Adminhtml\Settings;

/**
 * Settings backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FlushMagentoImagesCache extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        try {
            $this->_objectManager->create(\Magento\Catalog\Model\Product\Image::class)->clearCache();
            $this->_eventManager->dispatch('clean_catalog_images_cache_after');
            $this->messageManager->addSuccessMessage(__('The image cache was cleaned.'));

            /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
            $dataHelper = $this->getDataHelper();
            $dataHelper->saveBackendConfig(
                'sirv_catalog_images_cache_info',
                ''
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('An error occurred while clearing the image cache.'));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
