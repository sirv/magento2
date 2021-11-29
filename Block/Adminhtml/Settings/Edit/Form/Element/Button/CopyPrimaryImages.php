<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Button;

/**
 * Form button element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CopyPrimaryImages extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Button
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = '';
        $htmlId = $this->getHtmlId();

        $spinnerHtml = '<div class="spinner">' .
            '<span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>' .
            '</div>';

        $html = '<div class="note admin__field-note-top" id="' . $htmlId . '-note">Save time by uploading all your images to Sirv. Magento needs one image, so use this option to copy the primary image from Sirv to Magento.<br><span class="products_with_images_label">Products with images:</span> <span class="products_with_images_counter">' . $spinnerHtml . '</span><br><span class="products_without_images_label">Products without images:</span> <span class="products_without_images_counter">' . $spinnerHtml . '</span></div>';

        $beforeElementHtml = $this->getBeforeElementHtml();
        if ($beforeElementHtml) {
            $html .= '<label class="addbefore" for="' . $htmlId . '">' . $beforeElementHtml . '</label>';
        }

        $html .= $this->getButtonHtml();

        $afterElementJs = $this->getAfterElementJs();
        if ($afterElementJs) {
            $html .= $afterElementJs;
        }

        $afterElementHtml = $this->getAfterElementHtml();
        if ($afterElementHtml) {
            $html .= '<label class="addafter" for="' . $htmlId . '">' . $afterElementHtml . '</label>';
        }

        return $html;
    }

}
