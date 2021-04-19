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
class Edit extends \MagicToolbox\Sirv\Controller\Adminhtml\Settings
{
    /**
     * Edit action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();
        $config = $dataHelper->getConfig();

        $account = isset($config['account']) ? $config['account'] : '';
        if ($account) {
            $unauthorized = false;
            /** @var \MagicToolbox\Sirv\Model\Api\Sirv $apiClient */
            $apiClient = $dataHelper->getSirvClient();
            $accountInfo = $apiClient->getAccountInfo();
            $alias = $accountInfo && $accountInfo->alias ? $accountInfo->alias : false;

            if ($alias) {
                if ($alias != $account) {
                    $unauthorized = true;
                    $message = __(
                        'The account name "%1" has been changed or removed. Please, select your new account name.',
                        $account
                    );
                }
            } else {
                $code = $apiClient->getResponseCode();
                if ($code == 401 || $code == 403) {
                    //NOTE: 401 Unauthorized
                    //      403 Forbidden
                    $unauthorized = true;
                    $dataHelper->saveConfig('password', '');
                    $message = __(
                        'Unable to connect the account: "%1". The password may have been changed!',
                        $account
                    );
                } else {
                    $this->messageManager->addWarningMessage(__('Unexpected error occurred. Code: "%1".', $code));
                }
            }

            if ($unauthorized) {
                $dataHelper->saveConfig('account', '');
                $dataHelper->saveConfig('bucket', '');
                $dataHelper->saveConfig('token', '');
                $dataHelper->saveConfig('client_id', '');
                $dataHelper->saveConfig('client_secret', '');
                $this->messageManager->addWarningMessage($message);

                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('sirv/*/edit');
                return $resultRedirect;
            }
        }

        $requiredVersion = '1.6.6';
        $outdatedModules = $this->getOutdatedModules($requiredVersion);
        if (!empty($outdatedModules)) {
            foreach ($outdatedModules as $name => $version) {
                $this->messageManager->addWarning(__(
                    'Your extension %1 v%2 should be updated to work with the Sirv extension. <a target="_blank" href="%3">Download here</a>.',
                    $name,
                    $version,
                    /*'https://www.magictoolbox.com/my-account/'*/
                    'https://www.magictoolbox.com/magiczoom/modules/magento/'
                ));
            }
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Magento_Backend::system');
        $title = $resultPage->getConfig()->getTitle();
        $title->prepend('Sirv CDN');
        $title->prepend('Configuration');

        return $resultPage;
    }

    /**
     * Check for outdated modules
     *
     * @param string $requiredVersion
     * @return array
     */
    protected function getOutdatedModules($requiredVersion)
    {
        $outdatedModules = [];
        $modules = $this->getModulesData();
        foreach ($modules as $name => $version) {
            if (version_compare($version, $requiredVersion, '<')) {
                $outdatedModules[$name] = $version;
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

        /** @var \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $cache = $dataHelper->getAppCache();
        $cacheId = 'magictoolbox_modules_data';

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
