<?php

namespace Sirv\Magento2\Block\Adminhtml;

/**
 * Module version block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Version extends \Magento\Framework\View\Element\Template
{
    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Class constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->dataHelper = $this->objectManager->get(\Sirv\Magento2\Helper\Data\Backend::class);
    }

    /**
     * Get the current version of the module
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        return $this->dataHelper->getModuleVersion('Sirv_Magento2');
    }

    /**
     * Get changelog URL
     *
     * @return string
     */
    public function getChangelogUrl()
    {
        return $this->getUrl('sirv/ajax/changelog');
    }
}
