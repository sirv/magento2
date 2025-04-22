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
class Edit extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();
        $config = $dataHelper->getConfig();

        $account = isset($config['account']) ? $config['account'] : '';
        if ($account) {
            $unauthorized = false;
            $accConfig = $dataHelper->getAccountConfig(true);
            $newAccount = empty($accConfig) ? '' : $accConfig['alias'];
            if (!empty($newAccount)) {
                if ($account != $newAccount) {
                    $dataHelper->saveConfig('account', $newAccount);
                    $dataHelper->saveConfig('bucket', $newAccount);
                    $dataHelper->getSirvClient()->init([
                        'account' => $newAccount,
                        'bucket' => $newAccount
                    ]);
                    $this->messageManager->addNoticeMessage(__(
                        'The account name "%1" has been changed to "%2".',
                        $account,
                        $newAccount
                    ));
                }

                $cdnUrl = isset($config['cdn_url']) ? $config['cdn_url'] : '';
                $newCdnUrl = $accConfig['cdn_url'];
                if ($cdnUrl != $newCdnUrl) {
                    $dataHelper->saveConfig('cdn_url', $newCdnUrl);
                    if (!empty($cdnUrl)) {
                        $this->messageManager->addNoticeMessage(__(
                            'Sirv domain "%1" has been changed to "%2".',
                            $cdnUrl,
                            $newCdnUrl
                        ));
                    }
                }

                $subAlias = isset($config['sub_alias']) ? $config['sub_alias'] : '';
                if (empty($subAlias) ||
                    $subAlias == $newAccount ||
                    !isset($accConfig['aliases']) ||
                    !isset($accConfig['aliases'][$subAlias]) ||
                    count($accConfig['aliases']) < 2
                ) {
                    $dataHelper->deleteConfig('sub_alias');
                    $dataHelper->deleteConfig('sub_alias_domain');
                } else {
                    $subAliasDomain = isset($config['sub_alias_domain']) ? $config['sub_alias_domain'] : '';
                    if (empty($subAliasDomain) ||
                        $subAliasDomain != $accConfig['aliases'][$subAlias]
                    ) {
                        if (empty($accConfig['aliases'][$subAlias])) {
                            $dataHelper->deleteConfig('sub_alias');
                            $dataHelper->deleteConfig('sub_alias_domain');
                        } else {
                            $dataHelper->saveConfig('sub_alias_domain', $accConfig['aliases'][$subAlias]);
                        }
                    }
                }
            } else {
                $code = $dataHelper->getSirvClient()->getResponseCode();
                if ($code == 401 || $code == 403) {
                    //NOTE: 401 Unauthorized
                    //      403 Forbidden
                    $unauthorized = true;
                    $dataHelper->saveConfig('password', '');
                    $this->messageManager->addWarningMessage(__(
                        'Unable to connect the account: "%1". The password may have been changed!',
                        $account
                    ));
                } else {
                    $this->messageManager->addWarningMessage(__(
                        'Unexpected error occurred. Code: "%1".',
                        $code
                    ));
                }
            }

            if ($unauthorized) {
                $dataHelper->saveConfig('account', '');
                $dataHelper->saveConfig('bucket', '');
                $dataHelper->saveConfig('token', '');
                $dataHelper->saveConfig('client_id', '');
                $dataHelper->saveConfig('client_secret', '');

                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('sirv/*/edit');
                return $resultRedirect;
            }
        }

        $outdatedModules = $this->getOutdatedModules();
        foreach ($outdatedModules as $name => $version) {
            $this->messageManager->addWarning(__(
                'Your extension %1 v%2 should be updated to work with the Sirv extension. <a class="sirv-open-in-new-window" target="_blank" href="%3">Download here</a>.',
                $name,
                $version,
                /*'https://www.magictoolbox.com/my-account/'*/
                'https://www.magictoolbox.com/' . strtolower(substr($name, 13)) .'/modules/magento/'
            ));
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        //$resultPage->setActiveMenu('Magento_Backend::system');
        $resultPage->setActiveMenu('Sirv_Magento2::sirv_settings');
        $title = $resultPage->getConfig()->getTitle();
        $title->prepend('Sirv CDN');
        $title->prepend('Configuration');

        return $resultPage;
    }

    /**
     * Check for outdated modules
     *
     * @return array
     */
    protected function getOutdatedModules()
    {
        $requiredVersions = [
            'MagicToolbox_Magic360' => '1.7.0',
            'MagicToolbox_MagicZoomPlus' => '1.6.11',
            'MagicToolbox_MagicZoom' => '1.6.11',
            'MagicToolbox_MagicThumb' => '1.6.11',
            'MagicToolbox_MagicScroll' => '1.6.11',
            'MagicToolbox_MagicSlideshow' => '1.6.11',
        ];
        $modulesData = $this->getModulesData();
        $outdatedModules = [];

        foreach ($requiredVersions as $module => $requiredVersion) {
            if (isset($modulesData[$module]) && $modulesData[$module]) {
                if (version_compare($modulesData[$module], $requiredVersion, '<')) {
                    $outdatedModules[$module] = $modulesData[$module];
                }
            }
        }

        return $outdatedModules;
    }

    /**
     * Get enabled module's data (name and version)
     *
     * @return array
     */
    protected function getModulesData()
    {
        static $data = null;

        if ($data !== null) {
            return $data;
        }

        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $cache = $dataHelper->getAppCache();
        $cacheId = 'cache_id_modules_version';

        $data = $cache->load($cacheId);
        if (false !== $data) {
            $data = $dataHelper->getUnserializer()->unserialize($data);
            return $data;
        }

        $data = [];

        $mtModules = [
            'MagicToolbox_Magic360',
            'MagicToolbox_MagicZoomPlus',
            'MagicToolbox_MagicZoom',
            'MagicToolbox_MagicThumb',
            'MagicToolbox_MagicScroll',
            'MagicToolbox_MagicSlideshow',
        ];

        $enabledModules = \Magento\Framework\App\ObjectManager::getInstance()->get(
            \Magento\Framework\Module\ModuleList::class
        )->getNames();

        foreach ($mtModules as $name) {
            if (in_array($name, $enabledModules)) {
                $data[$name] = $dataHelper->getModuleVersion($name);
            }
        }

        $serializer = $dataHelper->getSerializer();
        //NOTE: cache lifetime (in seconds)
        $cache->save($serializer->serialize($data), $cacheId, [], 600);

        return $data;
    }
}
