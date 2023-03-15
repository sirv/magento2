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

        $link1 = '<a href="#" data-mage-init=\'{"sirvButton": {"target": "[data-role=copy_primary_images_to_magento]", "event": "sirv-products", "eventData": {"action": "view-items-with-image"}, "isDisabled": true}}\' title="View products with images">Products with images</a>';
        $link2 = '<a href="#" data-mage-init=\'{"sirvButton": {"target": "[data-role=copy_primary_images_to_magento]", "event": "sirv-products", "eventData": {"action": "view-items-without-image"}, "isDisabled": true}}\' title="View products without images">Products without images</a>';

        $html = '<div data-role="copy_primary_images_to_magento" class="note admin__field-note-top" id="' . $htmlId . '-note">Save time by uploading all your images to Sirv, following the folder structure chosen above.<br/><br/>Adobe Commerce needs an image per product, so this option will copy the first image (alphabetically) from Sirv to Adobe Commerce.<br/><br/><span class="products_with_images_label">' . $link1 . ':</span> <span class="products_with_images_counter">' . $spinnerHtml . '</span><br><span class="products_without_images_label">' . $link2 . ':</span> <span class="products_without_images_counter">' . $spinnerHtml . '</span></div>';

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

        $formWidget = $this->getForm()->getParent();
        $ajaxUrl = $formWidget->escapeUrl($formWidget->getUrl('sirv/ajax/products'));

        $html .= '
            <script type="text/x-magento-init">
                {
                    "[data-role=copy_primary_images_to_magento]": {
                        "sirvProductsList": {
                            "ajaxUrl": "' . $ajaxUrl . '"
                        }
                    }
                }
            </script>';

        return $html;
    }
}
