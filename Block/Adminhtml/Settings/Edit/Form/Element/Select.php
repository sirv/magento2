<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form select element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Select extends \Magento\Framework\Data\Form\Element\Select
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $value = $this->getValue();
        $values = $this->getValues();

        $hideSelect = $this->getCanHideSelect() && (count($values) < 2);

        $this->addClass('select admin__control-select' . ($hideSelect ? ' hidden' : ''));

        $html = '';
        if ($this->getBeforeElementHtml()) {
            $html .= '<label class="addbefore" for="' .
                $this->getHtmlId() .
                '">' .
                $this->getBeforeElementHtml() .
                '</label>';
        }

        if ($hideSelect) {
            $label = $value;
            if ($values) {
                foreach ($values as $key => $option) {
                    if (!is_array($option)) {
                        if ($key == $value) {
                            $label = $option;
                            break;
                        }
                    } elseif (is_array($option['value'])) {
                        foreach ($option['value'] as $groupItem) {
                            if ($groupItem['value'] == $value) {
                                $label = $groupItem['label'];
                                break 2;
                            }
                        }
                    } else {
                        if ($option['value'] == $value) {
                            $label = $option['label'];
                            break;
                        }
                    }
                }
            }
            $html .= '<div class="control-value admin__field-value">' . $label . '</div>';
        }

        $required = '';
        if ($placeholder = $this->getPlaceholder()) {
            $required = ' required';
            $this->addClass('select-with-placeholder');
        }

        $html .= '<select id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' . $this->serialize(
            $this->getHtmlAttributes()
        ) . $required . $this->_getUiId() . '>' . "\n";

        if (!is_array($value)) {
            $value = [$value];
        }

        if ($placeholder) {
            $html .= $this->_paceholderToHtml(['value' => '', 'label' => $placeholder], $value);
        }

        if ($values) {
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

        if ($tooltip = $this->getTooltip()) {
            $html .= '<div class="tooltip" data-mage-init=\'{"sirvTooltip":{}}\'><span class="help"><span></span></span>';
            $html .= '<div class="tooltip-content">' . $tooltip . '</div></div>';
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
        $html .= isset($option['title']) ? ' title="' . $this->_escape($option['title']) . '"' : '';
        $html .= isset($option['style']) ? ' style="' . $option['style'] . '"' : '';
        if (in_array($option['value'], $selected)) {
            $html .= ' selected="selected"';
        }
        $html .= ' disabled hidden';
        $html .= '>' . $this->_escape($option['label']) . '</option>' . "\n";

        return $html;
    }

    /**
     * Format an option as Html
     *
     * @param array $option
     * @param array $selected
     * @return string
     */
    protected function _optionToHtml($option, $selected)
    {
        if (is_array($option['value'])) {
            $html = '<optgroup label="' . $option['label'] . '">' . "\n";
            foreach ($option['value'] as $groupItem) {
                $html .= $this->_optionToHtml($groupItem, $selected);
            }
            $html .= '</optgroup>' . "\n";
        } else {
            $html = '<option value="' . $this->_escape($option['value']) . '"';
            $html .= isset($option['title']) ? ' title="' . $this->_escape($option['title']) . '"' : '';
            $html .= isset($option['style']) ? ' style="' . $option['style'] . '"' : '';
            $html .= isset($option['disabled']) ? ' disabled' : '';
            if (in_array($option['value'], $selected)) {
                $html .= ' selected="selected"';
            }
            $html .= '>' . $this->_escape($option['label']) . '</option>' . "\n";
        }
        return $html;
    }
}
