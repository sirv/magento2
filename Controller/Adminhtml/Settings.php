<?php

namespace MagicToolbox\Sirv\Controller\Adminhtml;

/**
 * Settings backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
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
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Check if admin has permissions to visit settings page
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MagicToolbox_Sirv::sirv_settings_edit');
    }

    /**
     * Get data helper
     *
     * @return \MagicToolbox\Sirv\Helper\Data\Backend
     */
    protected function getDataHelper()
    {
        static $helper = null;

        if ($helper == null) {
            $helper = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \MagicToolbox\Sirv\Helper\Data\Backend::class
            );
        }

        return $helper;
    }
}
