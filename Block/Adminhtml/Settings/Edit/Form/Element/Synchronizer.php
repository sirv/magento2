<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form synchronizer element
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Synchronizer extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        $this->setType('synchronizer');
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
            return '';
        }

        $layout = $this->getForm()->getParent()->getLayout();
        $syncBlock = $layout->createBlock(\MagicToolbox\Sirv\Block\Adminhtml\Synchronizer::class);
        $syncBlock->setSyncData($data);
        $html = $syncBlock->toHtml();

        return $html;
    }
}
