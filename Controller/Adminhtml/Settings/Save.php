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
class Save extends \MagicToolbox\Sirv\Controller\Adminhtml\Settings
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

        $addSuccessMessage = true;
        $doGetCredentials = false;

        $email = isset($config['email']) ? $config['email'] : null;
        $password = isset($config['password']) ? $config['password'] : null;
        $account = isset($config['account']) ? $config['account'] : null;
        $isNewAccount = isset($config['account_exists']) ? ($config['account_exists'] == 'no') : false;

        if ($isNewAccount) {
            $valid = true;
            $words = isset($config['first_and_last_name']) ? $config['first_and_last_name'] : '';
            $words = preg_replace('#\s{2,}#', ' ', trim($words));
            $words = explode(' ', $words);
            if (count($words) < 2) {
                $this->messageManager->addWarningMessage(
                    __('Please specify first and last name separated by a space.')
                );
                $valid = false;
            } else {
                $lastName = array_pop($words);
                $firstName = implode(' ', $words);
            }

            $alias = isset($config['alias']) ? trim($config['alias']) : '';
            if (!preg_match('#^[a-z0-9_]{6,32}$#i', $alias)) {
                $this->messageManager->addWarningMessage(
                    __('Wrong account name. Account name must be 6-32 characters. It can contain letters, numbers and hyphens (no spaces).')
                );
                $valid = false;
            }

            if ($valid) {
                /** @var MagicToolbox_Sirv_Model_Api_Sirv $apiClient */
                $apiClient = $this->dataHelper->getSirvClient();
                $registered = $apiClient->registerAccount($email, $password, $firstName, $lastName, $alias);
                if ($registered) {
                    $account = $alias;
                    $this->dataHelper->saveConfig('account', $account);
                    $this->dataHelper->deleteConfig('account_exists');
                    $this->dataHelper->deleteConfig('alias');
                    $this->dataHelper->deleteConfig('first_and_last_name');
                    $apiClient->init(['account' => $account]);
                } else {
                    $errorMsg = $apiClient->getErrorMsg();
                    if (preg_match('#must be a valid email#', $errorMsg)) {
                        $errorMsg = 'Wrong email address. Please check it and try again.';
                    }
                    if (preg_match('#Duplicate entry#', $errorMsg)) {
                        $errorMsg = 'Specified email address is already registered or account name is already taken.';
                    }
                    $this->messageManager->addWarningMessage(__($errorMsg));
                    $valid = false;
                }
            }

            if (!$valid) {
                $this->dataHelper->saveConfig('password', '');
                $password = null;
                $addSuccessMessage = false;
            }
        }

        //NOTE: check email and password
        if ($email && $password) {
            $accounts = $this->dataHelper->getSirvUsersList(true);

            if (empty($accounts)) {
                $this->dataHelper->saveConfig('password', '');
                $this->messageManager->addWarningMessage(
                    __('Your Sirv access credentials were rejected. Please check and try again.')
                );
            }
            $addSuccessMessage = false;
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
            }
            $addSuccessMessage = false;
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
                    $addSuccessMessage = false;
                }
            } else {
                $this->messageManager->addWarningMessage(__('Unable to receive client credentials.'));
                $addSuccessMessage = false;
            }
        }

        $network = isset($config['network']) ? $config['network'] : null;
        $autoFetch = isset($config['auto_fetch']) ? $config['auto_fetch'] : null;
        $urlPrefix = isset($config['url_prefix']) ? $config['url_prefix'] : '';
        if ($network !== null || $autoFetch !== null) {
            $this->dataHelper->setAccountConfig(
                $network == 'cdn',
                $autoFetch == 'all' || $autoFetch == 'custom',
                $urlPrefix
            );
        }

        $imageFolder = isset($config['image_folder']) ? $config['image_folder'] : null;
        if ($imageFolder !== null) {
            $this->dataHelper->disableSpinScanning($imageFolder);
        }

        $smvJsOptions = isset($config['smv_js_options']) ? $config['smv_js_options'] : '';
        if (preg_match('#</?script[^>]*+>#', $smvJsOptions, $matches)) {
            $smvJsOptions = preg_replace('#</?script[^>]*+>#', '', $smvJsOptions);
            $this->dataHelper->saveConfig('smv_js_options', $smvJsOptions);
        }

        $smvMaxHeight = isset($config['smv_max_height']) ? $config['smv_max_height'] : '';
        $smvMaxHeightValid = preg_replace('#[^0-9]#', '', $smvMaxHeight);
        if ($smvMaxHeight != $smvMaxHeightValid) {
            $this->dataHelper->saveConfig('smv_max_height', $smvMaxHeightValid);
        }

        if ($addSuccessMessage) {
            $message = __('The settings have been saved.') . ' ';
            $url = $this->getUrl('adminhtml/cache');
            $message .= __('<a class="save-message" href="%1">Clear your page cache</a> to see the changes.', $url);
            $this->messageManager->addSuccess($message);
        }

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
