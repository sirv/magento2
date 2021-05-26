<?php

namespace Sirv\Magento2\Model\System\Message;

/**
 * Notifications
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class GallerySynced implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * Authorization component
     *
     * @var \Magento\Framework\AuthorizationInterface
     */
    protected $authorization;

    /**
     * URL builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync\Backend
     */
    protected $syncHelper = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Sirv\Magento2\Helper\Sync\Backend $syncHelper
     * @return void
     */
    public function __construct(
        \Magento\Framework\AuthorizationInterface $authorization,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Sirv\Magento2\Helper\Sync\Backend $syncHelper
    ) {
        $this->authorization = $authorization;
        $this->urlBuilder = $urlBuilder;
        $this->syncHelper = $syncHelper;
    }

    /**
     * Retrieve unique message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return hash('md5', 'GALLERY_IS_NOT_SYNCED_NOTIFICATION');
    }

    /**
     * Check whether
     *
     * @return bool
     */
    public function isDisplayed()
    {
        if (!$this->syncHelper->isAuth()) {
            return false;
        }

        $data = $this->syncHelper->getSyncData();
        $total = $data['total'] ?: 0;
        if (!$total) {
            return false;
        }

        $synced = $data['synced'] ?: 0;
        $unsynced = $total - $synced;
        $unsyncedPercent = $unsynced * 100 / $total;
        $isDisplayed = $unsynced > 1000 || $unsyncedPercent > 75;

        return $this->authorization->isAllowed('Sirv_Magento2::sirv_settings_edit') && $isDisplayed;
    }

    /**
     * Retrieve message text
     *
     * @return string
     */
    public function getText()
    {
        $data = $this->syncHelper->getSyncData();
        $total = $data['total'] ?: 0;
        $synced = $data['synced'] ?: 0;
        $unsynced = $total - $synced;
        $url = $this->urlBuilder->getUrl('sirv/settings/edit') . '#sirv_group_fieldset_synchronization';

        $message = __(
            'You have %1 unsynced images. Sirv auto-syncs images on first request, typically 1-2 seconds per image. To skip this, <a href="%2">pre-sync your media gallery</a>.',
            $unsynced,
            $url
        );

        return $message;
    }

    /**
     * Retrieve message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return \Magento\Framework\Notification\MessageInterface::SEVERITY_NOTICE;
    }
}
