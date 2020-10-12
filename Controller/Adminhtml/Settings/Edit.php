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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\Data\Backend::class);
        $config = $dataHelper->getConfig();
        $account = isset($config['account']) ? $config['account'] : '';
        if ($account) {
            /** @var \MagicToolbox\Sirv\Model\Api\Sirv $apiClient */
            $apiClient = $dataHelper->getSirvClient();
            $accountInfo = $apiClient->getAccountInfo();
            $alias = $accountInfo && $accountInfo->alias ? $accountInfo->alias : false;
            //NOTE: if the account name has been changed
            if ($alias && ($alias != $account)) {
                $dataHelper->saveConfig('account', '');
                $dataHelper->saveConfig('bucket', '');
                $dataHelper->saveConfig('token', '');
                $dataHelper->saveConfig('client_id', '');
                $dataHelper->saveConfig('client_secret', '');
                $this->messageManager->addWarningMessage(
                    __(
                        'The account name "%1" has been changed or removed. Please, select your new account name.',
                        $account
                    )
                );

                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('sirv/*/edit');
                return $resultRedirect;
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
}
