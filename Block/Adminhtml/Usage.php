<?php

namespace Sirv\Magento2\Block\Adminhtml;

/**
 * Sirv usage block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Usage extends \Magento\Framework\View\Element\Template
{
    /**
     * Path to template file
     *
     * @var string
     */
    protected $_template = 'Sirv_Magento2::usage.phtml';

    /**
     * Usage data
     *
     * @var array
     */
    protected $usageData = [];

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
     * Get usage data
     *
     * @return array
     */
    public function getUsageData()
    {
        if (empty($this->usageData)) {
            $this->setUsageData();
        }

        return $this->usageData;
    }

    /**
     * Set usage data
     *
     * @return void
     */
    protected function setUsageData()
    {
        $this->usageData = $this->dataHelper->getAccountUsageData();
    }

    /**
     * Get AJAX URL
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('sirv/ajax/usage');
    }
}
