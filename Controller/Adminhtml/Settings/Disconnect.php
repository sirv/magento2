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
     * @var \MagicToolbox\Sirv\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MagicToolbox\Sirv\Helper\Data $dataHelper
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
            'email' => '',
            'password' => '',
            'token' => '',
            'token_expire_time' => '',
            'account' => '',
            'client_id' => '',
            'client_secret' => '',
            'key' => '',
            'secret' => '',
            'bucket' => '',
            'cdn_url' => '',
            'sirv_rate_limit_data' => '',
            's3_rate_limit_data' => '',
            'product_gallery_view' => 'original',
            'viewer_contents' => '1',
            'product_assets_folder' => '',
        ];

        foreach ($names as $name => $value) {
            $this->dataHelper->saveConfig($name, $value);
        }

        $this->messageManager->addSuccess(__('The account was disconnected.'));

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
