<?php

namespace Sirv\Magento2\Controller\Adminhtml\Settings;

/**
 * Settings backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Disconnect extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Disconnect action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $collection = $dataHelper->getConfigModel()->getCollection();
        $collection->addFieldToFilter('scope_id', ['neq' => 0]);
        foreach ($collection as $item) {
            $item->delete();
        }

        $names = [
            'enabled' => 'false',
            'sub_alias' => null,
            'sub_alias_domain' => null,
            'image_folder' => 'magento',
            'profile' => 'Default',
            'magento_watermark' => 'false',
            'product_gallery_view' => 'smv',
            'viewer_contents' => '3',
            'product_assets_folder' => 'products/{product-sku}',
            'auto_fetch' => 'none',
            'url_prefix' => null,
            'lazy_load' => 'true',
            'image_scaling' => null,
            'add_img_width_height' => null,
            'use_placeholders' => null,
            'js_components' => 'image,spin,zoom,video',
            'account_exists' => null,
            'email' => null,
            'password' => null,
            'alias' => null,
            'first_name' => null,
            'last_name' => null,
            'token' => null,
            'token_expire_time' => null,
            'account' => null,
            'client_id' => null,
            'client_secret' => null,
            'key' => null,
            'secret' => null,
            'bucket' => null,
            'cdn_url' => null,
            'smv_js_options' => null,
            'image_zoom' => null,
            'slides_order' => null,
            'pinned_items' => '{"videos":"no","spins":"no","images":"no","mask":""}',
            'smv_custom_css' => null,
            'assets_cache_ttl' => '1440',
            'excluded_pages' => null,
            'excluded_files' => null,
            'sirv_rate_limit_data' => null,
            's3_rate_limit_data' => null,
        ];

        foreach ($names as $name => $value) {
            if ($value === null) {
                $dataHelper->deleteConfig($name);
            } else {
                $dataHelper->saveConfig($name, $value);
            }
        }

        $this->messageManager->addSuccess(__('The account has been disconnected.'));

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
