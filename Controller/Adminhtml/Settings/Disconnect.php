<?php

namespace MagicToolbox\Sirv\Controller\Adminhtml\Settings;

/**
 * Settings backend controller
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
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
            'account' => '',
            'client_id' => '',
            'client_secret' => '',
            'token' => '',
            'token_expire_time' => '',
            'key' => '',
            'secret' => '',
            'bucket' => '',
            'cdnURL' => '',
            'sirv_rate_limit_data' => '',
            's3_rate_limit_data' => '',
        ];

        foreach ($names as $name => $value) {
            $this->dataHelper->saveConfig($name, $value);
        }

        $this->messageManager->addSuccess(__('The account was disconnected.'));

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
