<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form stats element
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Stats extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * Constructor
     *
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->setType('stats');
    }

    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $data = $this->getValue();

        if (!is_array($data)) {
            return '<a target="_blank" href="https://my.sirv.com/#/account/usage">Usage</a>';
        }

        $html = '<div class="sirv-stats-wraper">' .

            '<div class="row">' .

            '<div class="col-sm-6">' .
            '<h3>Storage</h3>' .

            '<div class="row">' .
            '<div class="col-sm-3"><label>Allowance</label></div><div class="col-sm-9">' . $data['storage']['allowance'] . '</div>' .
            '</div>' .

            '<div class="row">' .
            '<div class="col-sm-3"><label>Used</label></div><div class="col-sm-9">' .
            $data['storage']['used'] . ' <span class="text-muted">(' . $data['storage']['used_percent'] . '%)</span>' .
            '</div></div>' .

            '<div class="row">' .
            '<div class="col-sm-3"><label>Available</label></div><div class="col-sm-9">' .
            $data['storage']['available'] . ' <span class="text-muted">(' . $data['storage']['available_percent'] . '%)</span>' .
            '</div></div>' .

            '<div class="row">' .
            '<div class="col-sm-3"><label>Files</label></div><div class="col-sm-9">' . $data['storage']['files'] . '</div>' .
            '</div>' .

            '</div>' .

            '<div class="col-sm-6">' .
            '<h3>Traffic</h3>' .

            '<div class="row">' .
            '<div class="col-sm-3"><label>Allowance</label></div><div class="col-sm-9">' . $data['traffic']['allowance'] . '</div>' .
            '</div>';

        foreach ($data['traffic']['traffic'] as $label => $sdata) {
            $html .= '<div class="row">' .
                '<div class="col-sm-3"><label>' . $label . '</label></div>' .
                '<div class="col-sm-9"><div class="row">' .
                '<div class="col-sm-3">' . $sdata['size'] . '</div>' .

                '<div class="col-sm-9 sirv-progress-bar-holder">' .
                '<div class="sirv-progress-bar">';

            if ($sdata['size'] != '0 Bytes') {
                $html .= '<div><div style="width:' . $sdata['size_percent_reverse'] . '%;"></div></div>';
            }

            $html .= '</div>' .
                '</div>' .
                '</div></div>' .
                '</div>';
        }

        $html .= '</div></div></div>';

        return $html;
    }
}
