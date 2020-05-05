<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form account element
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Account extends \Magento\Framework\Data\Form\Element\Select
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->addClass('select admin__control-select');

        $html = '';
        if ($this->getBeforeElementHtml()) {
            $html .= '<label class="addbefore" for="' .
                $this->getHtmlId() .
                '">' .
                $this->getBeforeElementHtml() .
                '</label>';
        }

        $required = '';
        if ($placeholder = $this->getPlaceholder()) {
            $required = ' required';
            $this->addClass('select-with-placeholder');
        }

        $html .= '<select id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' . $this->serialize(
            $this->getHtmlAttributes()
        ) . $required . $this->_getUiId() . '>' . "\n";

        $value = $this->getValue();
        if (!is_array($value)) {
            $value = [$value];
        }

        if ($placeholder) {
            $html .= $this->_paceholderToHtml(['value' => '', 'label' => $placeholder], $value);
        }

        if ($values = $this->getValues()) {
            foreach ($values as $key => $option) {
                if (!is_array($option)) {
                    $html .= $this->_optionToHtml(['value' => $key, 'label' => $option], $value);
                } elseif (is_array($option['value'])) {
                    $html .= '<optgroup label="' . $option['label'] . '">' . "\n";
                    foreach ($option['value'] as $groupItem) {
                        $html .= $this->_optionToHtml($groupItem, $value);
                    }
                    $html .= '</optgroup>' . "\n";
                } else {
                    $html .= $this->_optionToHtml($option, $value);
                }
            }
        }

        $html .= '</select>' . "\n";

        if ($this->getAfterElementHtml()) {
            $html .= '<label class="addafter" for="' .
                $this->getHtmlId() .
                '">' .
                "\n{$this->getAfterElementHtml()}\n" .
                '</label>' .
                "\n";
        }

        return $html;
    }

    /**
     * Format paceholder option as HTML
     *
     * @param array $option
     * @param array $selected
     * @return string
     */
    protected function _paceholderToHtml($option, $selected)
    {
        $html = '<option value="' . $this->_escape($option['value']) . '"';
        $html .= isset($option['title']) ? 'title="' . $this->_escape($option['title']) . '"' : '';
        $html .= isset($option['style']) ? 'style="' . $option['style'] . '"' : '';
        if (in_array($option['value'], $selected)) {
            $html .= ' selected="selected"';
        }
        $html .= ' disabled hidden';
        $html .= '>' . $this->_escape($option['label']) . '</option>' . "\n";

        return $html;
    }
}
