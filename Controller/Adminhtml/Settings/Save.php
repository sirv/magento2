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
class Save extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Execute action
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

        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $configScope = $dataHelper->getConfigScope();
        $configScopeId = $dataHelper->getConfigScopeId();

        $params = [];
        if (isset($data['current_tab_id']) && !empty($data['current_tab_id'])) {
            $params['tabId'] = $data['current_tab_id'];
        }
        if ($configScope != 'default') {
            $params[$configScope] = $configScopeId;
        }

        if (empty($config) && empty($scopeData)) {
            $this->messageManager->addWarningMessage(__('There is nothing to save!'));
            $resultRedirect->setPath('sirv/*/edit', $params);
            return $resultRedirect;
        }

        if (isset($config['js_modules'])) {
            $config['js_modules'] = implode(',', $config['js_modules']);
        }

        if (isset($config['pinned_items'])) {
            $config['pinned_items'] = json_encode($config['pinned_items']);
        }

        if (isset($config['http_auth'])) {
            $config['http_auth'] = 'yes';
        } else {
            $config['http_auth'] = 'no';
            $config['http_auth_user'] = '';
            $config['http_auth_pass'] = '';
        }

        $configTmp = $config;
        foreach ($scopeData as $name => $v) {
            if ($v == 'on') {
                $dataHelper->saveConfig($name, $config[$name]);
            } elseif ($v == 'off') {
                $dataHelper->deleteConfig($name);
            }
            unset($configTmp[$name]);
        }

        foreach ($configTmp as $name => $value) {
            $dataHelper->saveConfig($name, $value);
        }
        unset($configTmp);

        $addSuccessMessage = true;
        $doGetCredentials = false;

        $email = isset($config['email']) ? $config['email'] : null;
        $password = isset($config['password']) ? $config['password'] : null;
        $otpCode = isset($config['otp_code']) ? $config['otp_code'] : null;
        $account = isset($config['account']) ? $config['account'] : null;
        $isNewAccount = isset($config['account_exists']) ? ($config['account_exists'] == 'no') : false;

        if ($isNewAccount) {
            $valid = true;

            if (preg_match('#\p{Lu}#u', $email)) {
                $this->messageManager->addWarningMessage(
                    __('Email address is invalid. Email address can\'t contain uppercase characters.')
                );
                $valid = false;
            }

            if (strpos($email, 'mail.ru') !== false) {
                $this->messageManager->addWarningMessage(
                    __('Please use a company email address (not mail.ru).')
                );
                $valid = false;
            }

            $passLen = strlen($password);
            if ($passLen < 8 || $passLen > 64) {
                $this->messageManager->addWarningMessage(
                    __('Password is invalid. It must be at least 8 characters and at most 64 characters.')
                );
                $valid = false;
            }

            $firstName = isset($config['first_name']) ? $config['first_name'] : '';
            $firstName = preg_replace('#\s{2,}#', ' ', trim($firstName));
            if (!preg_match('#^\p{L}\p{L}(?:\p{L}|\-| |\.(?= )){0,33}$#u', $firstName)) {
                $this->messageManager->addWarningMessage(__(
                    'First name is invalid. It must be 2-35 characters. First name cannot contain punctuation, digits or mathematical symbols.'
                ));
                $valid = false;
            }
            $lastName = isset($config['last_name']) ? $config['last_name'] : '';
            $lastName = preg_replace('#\s{2,}#', ' ', trim($lastName));
            if (!preg_match('#^\p{L}\p{L}(?:\p{L}|\-| |\.(?= )){0,33}$#u', $lastName)) {
                $this->messageManager->addWarningMessage(__(
                    'Last name is invalid. It must be 2-35 characters. Last name cannot contain punctuation, digits or mathematical symbols.'
                ));
                $valid = false;
            }

            $alias = isset($config['alias']) ? trim($config['alias']) : '';
            $matches = [];
            if (!preg_match('#^[a-z0-9-]{6,30}$#', $alias)) {
                $this->messageManager->addWarningMessage(
                    __('Account name is invalid. Account name must be 6-30 characters. It can contain lowercase characters, numbers and hyphens (no spaces).')
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
                /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
                $apiClient = $dataHelper->getSirvClient();
                $registered = $apiClient->registerAccount($email, $password, $firstName, $lastName, $alias);
                if ($registered) {
                    $account = $alias;
                    $dataHelper->saveConfig('account', $account);
                    $dataHelper->deleteConfig('account_exists');
                    $dataHelper->deleteConfig('alias');
                    $dataHelper->deleteConfig('first_name');
                    $dataHelper->deleteConfig('last_name');
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
                $account = null;
                $addSuccessMessage = false;
            }
        }

        //NOTE: check email and password
        if (($email && $password) || $otpCode) {
            /*
            $dataHelper->getAppCache()->remove(
                'sirv_accounts_data_' . hash('md5', $email)
            );
            */
            $accounts = $dataHelper->getSirvAccounts(true);

            if (empty($accounts)) {
                $responseCode = $dataHelper->getSirvClient()->getResponseCode();

                if ($responseCode == 417) {
                    $dataHelper->saveConfig('need_otp_code', 'true');
                } elseif ($responseCode == 200) {
                    $errorMsg = __(
                        'Sirv user %1 does not have permission to connect. Your role must be either Admin or Owner.',
                        $email
                    );
                    $this->messageManager->addWarning($errorMsg);
                    $dataHelper->saveConfig('password', '');
                } else {
                    if ($otpCode) {
                        $dataHelper->saveConfig('display_otp_code_rejected_message', 'true');
                        $dataHelper->deleteConfig('otp_code');
                    } else {
                        $dataHelper->saveConfig('display_credentials_rejected_message', 'true');
                        $dataHelper->saveConfig('password', '');
                    }
                }
            } else {
                $dataHelper->deleteConfig('need_otp_code');
                $dataHelper->deleteConfig('otp_code');
                if (count($accounts) == 1) {
                    $account = array_key_first($accounts);
                    $dataHelper->saveConfig('account', $account);

                    /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
                    $apiClient = $dataHelper->getSirvClient();
                    $apiClient->init(['account' => $account]);
                }
            }

            $addSuccessMessage = false;
        }

        if ($account) {
            $accounts = $dataHelper->getSirvAccounts();

            if (isset($accounts[$account])) {
                $doGetCredentials = true;
                $dataHelper->deleteConfig('account_exists');
                $dataHelper->deleteConfig('first_name');
                $dataHelper->deleteConfig('last_name');
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
            /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
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

                $email = $dataHelper->getConfig('email') ?: '';
                $cache = $dataHelper->getAppCache()->remove(
                    'sirv_accounts_data_' . hash('md5', $email)
                );
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
                    $dataHelper->saveConfig('auto_fetch', 'custom');
                }
                if ($urlPrefix == '') {
                    $domains = $dataHelper->getDomains();
                    $urlPrefix = reset($domains);
                }
            }

            if ($autoFetch !== null) {
                $auth = $config['http_auth'] == 'no' ? [] : [
                    'user' => $config['http_auth_user'],
                    'pass' => $config['http_auth_pass']
                ];
                $dataHelper->setAccountConfig(
                    $autoFetch == 'all' || $autoFetch == 'custom',
                    $urlPrefix,
                    $auth
                );
            }

            $imageFolder = isset($config['image_folder']) ? $config['image_folder'] : null;
            if ($imageFolder !== null) {
                $dataHelper->disableSpinScanning($imageFolder);
            }
        }

        foreach (['excluded_pages', 'excluded_files', 'excluded_from_lazy_load'] as $optionId) {
            $excludedList = isset($config[$optionId]) ? $config[$optionId] : null;
            if ($excludedList !== null) {
                $excludedList = trim($excludedList);
                if (!empty($excludedList)) {
                    $excludedList = explode("\r\n", $excludedList);
                    $newExcludedList = [];
                    foreach ($excludedList as &$excludedUrl) {
                        if (empty($excludedUrl)) {
                            continue;
                        }
                        $excludedUrl = preg_replace('#^(?:https?\:)?//[^/]+/#', '/', $excludedUrl);
                        $excludedUrl = preg_replace('#\*++#', '*', $excludedUrl);
                        //$excludedUrl = '/' . preg_replace('#^/#', '', $excludedUrl);
                        $newExcludedList[] = $excludedUrl;
                    }
                    $excludedList = array_unique($newExcludedList);
                    $excludedList = implode("\n", $excludedList);
                }
                $dataHelper->saveConfig($optionId, $excludedList);
            }
        }

        $smvJsOptions = isset($config['smv_js_options']) ? $config['smv_js_options'] : '';
        if (preg_match('#</?script[^>]*+>#', $smvJsOptions)) {
            $smvJsOptions = preg_replace('#</?script[^>]*+>#', '', $smvJsOptions);
            $dataHelper->saveConfig('smv_js_options', $smvJsOptions);
        }
        $css = isset($config['custom_css']) ? $config['custom_css'] : '';
        if (preg_match('#</?style[^>]*+>#', $css)) {
            $css = preg_replace('#</?style[^>]*+>#', '', $css);
            $dataHelper->saveConfig('custom_css', $css);
        }
        $smvCss = isset($config['smv_custom_css']) ? $config['smv_custom_css'] : '';
        if (preg_match('#</?style[^>]*+>#', $smvCss)) {
            $smvCss = preg_replace('#</?style[^>]*+>#', '', $smvCss);
            $dataHelper->saveConfig('smv_custom_css', $smvCss);
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

        $resultRedirect->setPath('sirv/*/edit', $params);

        return $resultRedirect;
    }
}
