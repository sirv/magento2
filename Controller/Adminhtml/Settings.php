<?php

namespace Sirv\Magento2\Controller\Adminhtml;

/**
 * Settings backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
abstract class Settings extends \Magento\Backend\App\Action
{
    /**
     * Result page factory
     *
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = null;

    /**
     * Data helper factory
     *
     * @var \Sirv\Magento2\Helper\Data\BackendFactory
     */
    protected $dataHelperFactory = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->dataHelperFactory = $dataHelperFactory;
        parent::__construct($context);
    }

    /**
     * Check if admin has permissions to visit settings page
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Sirv_Magento2::sirv_settings_edit');
    }

    /**
     * Get data helper
     *
     * @return \Sirv\Magento2\Helper\Data\Backend
     */
    protected function getDataHelper()
    {
        static $helper = null;

        if ($helper == null) {
            /*
            $helper = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Sirv\Magento2\Helper\Data\Backend::class
            );
            */
            $helper = $this->dataHelperFactory->create();
        }

        return $helper;
    }
}
