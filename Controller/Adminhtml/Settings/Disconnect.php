<?php

namespace MagicToolbox\Sirv\Controller\Adminhtml\Settings;

/**
 * Settings backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Disconnect extends \MagicToolbox\Sirv\Controller\Adminhtml\Settings
{
    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper
    ) {
        parent::__construct($context, $resultPageFactory);
        $this->dataHelper = $dataHelper;
    }

    /**
     * Disconnect action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $names = [
            'enabled' => 'false',
            'network' => 'cdn',
            'image_folder' => 'magento',
            'profile' => 'Default',
            'magento_watermark' => 'false',
            'product_gallery_view' => 'original',
            'viewer_contents' => '1',
            'product_assets_folder' => '',
            'auto_fetch' => 'none',
            'url_prefix' => null,
            'account_exists' => null,
            'email' => null,
            'password' => null,
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
            'sirv_rate_limit_data' => null,
            's3_rate_limit_data' => null,
        ];

        foreach ($names as $name => $value) {
            if ($value === null) {
                $this->dataHelper->deleteConfig($name);
            } else {
                $this->dataHelper->saveConfig($name, $value);
            }
        }

        $this->messageManager->addSuccess(__('The account has been disconnected.'));

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
