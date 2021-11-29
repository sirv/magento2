<?php

namespace Sirv\Magento2\Block\Adminhtml;

/**
 * Review block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
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
            $accountCreated = $this->dataHelper->getConfig('account_created');
            if ($accountCreated === null) {
                $accountInfo = $this->dataHelper->getAccountConfig();
                $accountCreated = empty($accountInfo) ? '' : $accountInfo['date_created'];
                if (!empty($accountCreated)) {
                    $this->dataHelper->saveConfig('account_created', $accountCreated);
                }
            }
            if (!empty($accountCreated)) {
                if ((time() - strtotime($accountCreated)) >= 5184000/*60 days*/) {
                    $doDisplayBanner = 'true';
                    $this->dataHelper->saveConfig('display_review_banner', $doDisplayBanner);
                }
            }
        }

        return $doDisplayBanner == 'true';
    }
}
