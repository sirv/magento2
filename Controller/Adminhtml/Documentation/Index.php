<?php

namespace Sirv\Magento2\Controller\Adminhtml\Documentation;

/**
 * Documentation link controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Index extends \Magento\Backend\App\Action
{
    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
        );

        $resultRedirect->setUrl('https://sirv.com/help/articles/magento-cdn-sirv-extension/');

        return $resultRedirect;
    }

    /**
     * Check permissions
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Sirv_Magento2::sirv_documentation_system');
    }
}
