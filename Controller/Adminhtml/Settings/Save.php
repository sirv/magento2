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
        $config = isset($data['mt-config']) && is_array($data['mt-config']) ? $data['mt-config'] : [];
        $scopeData = isset($data['scope-switcher']) && is_array($data['scope-switcher']) ? $data['scope-switcher'] : [];

        /** @var \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $configScope = $dataHelper->getConfigScope();
        $configScopeId = $dataHelper->getConfigScopeId();

        if (empty($config) && empty($scopeData)) {
            $this->messageManager->addWarningMessage(__('There is nothing to save!'));
            $resultRedirect->setPath('sirv/*/edit', ($configScope == 'default' ? [] : [$configScope => $configScopeId]));
            return $resultRedirect;
        }

        if (isset($config['js_components'])) {
            $config['js_components'] = implode(',', $config['js_components']);
        }

        foreach ($scopeData as $name => $v) {
            if ($v == 'on') {
                $dataHelper->saveConfig($name, $config[$name]);
            } elseif ($v == 'off') {
                $dataHelper->deleteConfig($name);
            }
            unset($config[$name]);
        }

        foreach ($config as $name => $value) {
            $dataHelper->saveConfig($name, $value);
        }

        $addSuccessMessage = true;
        $doGetCredentials = false;

        $email = isset($config['email']) ? $config['email'] : null;
        $password = isset($config['password']) ? $config['password'] : null;
        $account = isset($config['account']) ? $config['account'] : null;
        $isNewAccount = isset($config['account_exists']) ? ($config['account_exists'] == 'no') : false;

        if ($isNewAccount) {
            $valid = true;

            if (strlen($password) < 8) {
                $this->messageManager->addWarningMessage(
                    __('Password is invalid. It must be at least 8 characters.')
                );
                $valid = false;
            }

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
                if (!preg_match('#^[a-z ]{2,35}$#i', $firstName)) {
                    $this->messageManager->addWarningMessage(
                        __('First name is invalid. It must be 2-35 characters. It can contain letters and spaces.')
                    );
                    $valid = false;
                }
                if (!preg_match('#^[a-z]{2,35}$#i', $lastName)) {
                    $this->messageManager->addWarningMessage(
                        __('Last name is invalid. It must be 2-35 characters. It can contain letters only.')
                    );
                    $valid = false;
                }
            }

            $alias = isset($config['alias']) ? trim($config['alias']) : '';
            $matches = [];
            if (!preg_match('#^[a-z0-9-]{6,32}$#', $alias)) {
                $this->messageManager->addWarningMessage(
                    __('Account name is invalid. Account name must be 6-32 characters. It can contain lowercase characters, numbers and hyphens (no spaces).')
                );
                $valid = false;
            } elseif (preg_match('#\-(?:cdn|direct)$#i', $alias, $matches)) {
                $this->messageManager->addWarningMessage(__(
                    'Account name cannot end with "%1". You could try %2 instead.',
                    $matches[0],
                    preg_replace('#\-(cdn|direct)$#i', '$1', $alias)
                ));
                $valid = false;
            } elseif (preg_match('#\-srv\d+$#i', $alias)) {
                $this->messageManager->addWarningMessage(__(
                    'This name is reserved. Please choose another.'
                ));
                $valid = false;
            }

            if ($valid) {
                /** @var \MagicToolbox\Sirv\Model\Api\Sirv $apiClient */
                $apiClient = $dataHelper->getSirvClient();
                $registered = $apiClient->registerAccount($email, $password, $firstName, $lastName, $alias);
                if ($registered) {
                    $account = $alias;
                    $dataHelper->saveConfig('account', $account);
                    $dataHelper->deleteConfig('account_exists');
                    $dataHelper->deleteConfig('alias');
                    $dataHelper->deleteConfig('first_and_last_name');
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
                $dataHelper->saveConfig('password', '');
                $password = null;
                $addSuccessMessage = false;
            }
        }

        //NOTE: check email and password
        if ($email && $password) {
            $accounts = $dataHelper->getSirvUsersList(true);

            if (empty($accounts)) {
                $responseCode = $dataHelper->getSirvClient()->getResponseCode();
                if ($responseCode == 200) {
                    $errorMsg = __(
                        'Sirv user %1 does not have permission to connect. Your role must be either Admin or Owner.',
                        $email
                    );
                } else {
                    $errorMsg = __('Your Sirv access credentials were rejected. Please check and try again.');
                }

                $dataHelper->saveConfig('password', '');
                $this->messageManager->addWarningMessage($errorMsg);
            }
            $addSuccessMessage = false;
        }

        if ($account) {
            $accounts = $dataHelper->getSirvUsersList();

            if (in_array($account, $accounts)) {
                $doGetCredentials = true;
                $dataHelper->deleteConfig('account_exists');
                $dataHelper->deleteConfig('first_and_last_name');
                $dataHelper->deleteConfig('alias');
            } else {
                $dataHelper->saveConfig('account', '');
                $this->messageManager->addWarningMessage(
                    __('It seems that your Sirv account "%1" does not exist. Please select an account from the list.', $account)
                );
            }
            $addSuccessMessage = false;
        }

        if ($doGetCredentials) {
            /** @var MagicToolbox_Sirv_Model_Api_Sirv $apiClient */
            $apiClient = $dataHelper->getSirvClient();

            if ($clientCredentials = $apiClient->getClientCredentials()) {
                foreach ($clientCredentials as $key => $value) {
                    $dataHelper->saveConfig($key, $value);
                }

                if ($s3Credentials = $apiClient->getS3Credentials()) {
                    foreach ($s3Credentials as $key => $value) {
                        $dataHelper->saveConfig($key, $value);
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

        if ($configScopeId === 0) {
            $autoFetch = isset($config['auto_fetch']) ? $config['auto_fetch'] : null;
            $urlPrefix = isset($config['url_prefix']) ? $config['url_prefix'] : '';

            //NOTE: to set default values for the first time
            if ($doGetCredentials && isset($s3Credentials)) {
                if ($autoFetch == null) {
                    $autoFetch = 'custom';
                }
                if ($urlPrefix == '') {
                    $domains = $dataHelper->getDomains();
                    $urlPrefix = reset($domains);
                }
            }

            if ($autoFetch !== null) {
                $dataHelper->setAccountConfig(
                    $autoFetch == 'all' || $autoFetch == 'custom',
                    $urlPrefix
                );
            }

            $imageFolder = isset($config['image_folder']) ? $config['image_folder'] : null;
            if ($imageFolder !== null) {
                $dataHelper->disableSpinScanning($imageFolder);
            }
        }

        $smvJsOptions = isset($config['smv_js_options']) ? $config['smv_js_options'] : '';
        $matches = [];
        if (preg_match('#</?script[^>]*+>#', $smvJsOptions, $matches)) {
            $smvJsOptions = preg_replace('#</?script[^>]*+>#', '', $smvJsOptions);
            $dataHelper->saveConfig('smv_js_options', $smvJsOptions);
        }

        $smvMaxHeight = isset($config['smv_max_height']) ? $config['smv_max_height'] : '';
        $smvMaxHeightValid = preg_replace('#[^0-9]#', '', $smvMaxHeight);
        if ($smvMaxHeight != $smvMaxHeightValid) {
            $dataHelper->saveConfig('smv_max_height', $smvMaxHeightValid);
        }

        if ($addSuccessMessage) {
            $message = __('The settings have been saved.') . ' ';
            $url = $this->getUrl('adminhtml/cache');
            $message .= __('<a class="save-message" href="%1">Clear your page cache</a> to see the changes.', $url);
            $this->messageManager->addSuccess($message);
        }

        $resultRedirect->setPath('sirv/*/edit', ($configScope == 'default' ? [] : [$configScope => $configScopeId]));

        return $resultRedirect;
    }
}
