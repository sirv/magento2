<?php

namespace Sirv\Magento2\Block\Adminhtml;

/**
 * Review block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Review extends \Magento\Framework\View\Element\Template
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
     * Do display banner
     *
     * @return bool
     */
    public function doDisplayBanner()
    {
        $doDisplayBanner = $this->dataHelper->getConfig('display_review_banner');
        if ($doDisplayBanner === null) {
            $installDate = $this->dataHelper->getConfig('installation_date');
            if ($installDate === null) {
                $installDate = time();
                $this->dataHelper->saveConfig('installation_date', $installDate);
            }
            if ((time() - (int)$installDate) >= 5184000/*60 days*/) {
                $doDisplayBanner = 'true';
                $this->dataHelper->saveConfig('display_review_banner', $doDisplayBanner);
            }
        }

        return $doDisplayBanner == 'true';
    }
}
