<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form synchronizer element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
        $layout = $this->getForm()->getParent()->getLayout();
        $syncBlock = $layout->createBlock(\Sirv\Magento2\Block\Adminhtml\Synchronizer::class);
        $html = $syncBlock->toHtml();

        return $html;
    }
}
