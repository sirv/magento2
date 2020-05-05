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
class Save extends \MagicToolbox\Sirv\Controller\Adminhtml\Settings
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
     * Save action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $data = $this->getRequest()->getPostValue();
        $config = isset($data['magictoolbox']) && is_array($data['magictoolbox']) ? $data['magictoolbox'] : [];

        if (empty($config)) {
            $this->messageManager->addWarningMessage(__('There is nothing to save!'));
            $resultRedirect->setPath('sirv/*/edit');
            return $resultRedirect;
        }

        foreach ($config as $name => $value) {
            $this->dataHelper->saveConfig($name, $value);
        }

        $success = true;
        $doGetCredentials = false;

        $email = isset($config['email']) ? $config['email'] : null;
        $password = isset($config['password']) ? $config['password'] : null;
        $account = isset($config['account']) ? $config['account'] : null;

        //NOTE: check email and password
        if ($email && $password) {
            $accounts = $this->dataHelper->getSirvUsersList(true);

            if (empty($accounts)) {
                $this->dataHelper->saveConfig('password', '');
                $this->messageManager->addWarningMessage(
                    __('Your Sirv access credentials were rejected. Please check and try again.')
                );
                $success = false;
            }
        }

        if ($account) {
            $accounts = $this->dataHelper->getSirvUsersList();

            if (in_array($account, $accounts)) {
                $doGetCredentials = true;
            } else {
                $this->dataHelper->saveConfig('account', '');
                $this->messageManager->addWarningMessage(
                    __('It seems that your Sirv account "%1" does not exist. Please select an account from the list.', $account)
                );
                $success = false;
            }
        }

        if ($doGetCredentials) {
            /** @var MagicToolbox_Sirv_Model_Api_Sirv $apiClient */
            $apiClient = $this->dataHelper->getSirvClient();

            if ($clientCredentials = $apiClient->getClientCredentials()) {
                foreach ($clientCredentials as $key => $value) {
                    $this->dataHelper->saveConfig($key, $value);
                }

                if ($s3Credentials = $apiClient->getS3Credentials()) {
                    foreach ($s3Credentials as $key => $value) {
                        $this->dataHelper->saveConfig($key, $value);
                    }
                } else {
                    $this->messageManager->addWarningMessage(__('Unable to receive S3 credentials.'));
                    $success = false;
                }
            } else {
                $this->messageManager->addWarningMessage(__('Unable to receive client credentials.'));
                $success = false;
            }
        }

        $network = isset($config['network']) ? $config['network'] : null;
        if ($network !== null) {
            $this->dataHelper->switchNetwork($network == 'cdn');
        }

        $imageFolder = isset($config['image_folder']) ? $config['image_folder'] : null;
        if ($imageFolder !== null) {
            $this->dataHelper->disableSpinScanning($imageFolder);
        }

        if ($success) {
            $this->messageManager->addSuccess(__('You saved the settings.'));
        }

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
