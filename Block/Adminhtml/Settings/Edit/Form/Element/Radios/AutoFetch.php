<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form element for 'auto_fetch' param
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AutoFetch extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->setData(
            'data-role',
            [
                'custom' => 'auto-fetch-switcher',
                'all' => 'auto-fetch-switcher',
                'none' => 'auto-fetch-switcher'
            ]
        );

        return parent::getElementHtml();
    }

    /**
     * Format an option as HTML
     *
     * @param array $option
     * @param array $selected
     * @return string
     */
    protected function _optionToHtml($option, $selected)
    {
        $html = parent::_optionToHtml($option, $selected);
        if ($option['value'] == $selected) {
            $html .= '<div class="admin__field admin__field-sub-option ' . ('none' == $selected ? ' hidden' : '') . '">';
            $html .= '<div class="note admin__field-note" id="mt-auto_fetch-note">Sirv will fetch files from this domain:</div>';
            $html .= '<div class="admin__field-control control">';
            $html .= $this->getSubOptionHtml();
            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * Get sub option element HTML
     *
     * @return string
     */
    public function getSubOptionHtml()
    {
        $data = $this->getData('url_prefix');
        $value = $data['value'];
        $values = $data['values'] ?: [];
        $hideSelect = (count($values) < 2);
        $html = '';

        if ($hideSelect) {
            $label = $value;
            foreach ($values as $key => $option) {
                if ($option['value'] == $value) {
                    $label = $option['label'];
                    break;
                }
            }
            $html .= '<div class="control-value admin__field-value">' . $label . '</div>';
        }

        $html .= '<select id="mt-url_prefix" name="mt-config[url_prefix]" title="Domain for fetching files" class="mt-option select admin__control-select' . ($hideSelect ? ' hidden' : '') . '">';
        foreach ($values as $key => $option) {
            $html .= '<option value="' . $this->_escape($option['value']) . '"';
            if ($option['value'] == $value) {
                $html .= ' selected="selected"';
            }
            $html .= '>' . $this->_escape($option['label']) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Get the after element HTML
     *
     * @return mixed
     */
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        $html .= '
            <script type="text/x-magento-init">
                {
                    "[data-role=auto-fetch-switcher]": {
                        "Sirv_Magento2/js/auto-fetch-switcher": {}
                    }
                }
            </script>';

        return $html;
    }
}
