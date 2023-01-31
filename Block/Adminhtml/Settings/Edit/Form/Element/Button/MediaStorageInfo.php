<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Button;

/**
 * Form button element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class MediaStorageInfo extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Button
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $mediaStorageInfo = $this->getData('media_storage_info');

        $spinnerHtml = '<div class="media-storage-info-spinner spinner hidden"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>';

        $progressHtml = '<span class="media-storage-info-progress hidden"> Analysing... <span class="media-storage-info-progress-counter" data-count="0">0</span>%</span>';

        if ($mediaStorageInfo['timestamp']) {
            $this->setValue('Recalculate');
            $infoHtml = '<span class="media-storage-info-size">' . ceil($mediaStorageInfo['size'] / 1000000) . ' MB</span>';
            $infoHtml .= ' (<span class="media-storage-info-count">' . $mediaStorageInfo['count'] . ' image' . ($mediaStorageInfo['count'] == 1 ? '' : 's') . '</span>)';
            $infoHtml .= ' on <span class="media-storage-info-timestamp">' .  date('F j, Y', $mediaStorageInfo['timestamp']) . '</span>';
        } else {
            $infoHtml = 'No infomation yet';
        }

        $beforeElementHtml = $spinnerHtml . $progressHtml .
            '<span class="media-storage-info">' . $infoHtml . '</span>';

        $this->setBeforeElementHtml($beforeElementHtml);

        return parent::getElementHtml();
    }
}
