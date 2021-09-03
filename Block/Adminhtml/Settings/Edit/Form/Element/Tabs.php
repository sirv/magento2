<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form tabs element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Tabs extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * Tabs data
     *
     * @var array
     */
    protected $tabsData = [];

    /**
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->setType('tabs');
    }

    /**
     * Get the HTML
     *
     * @return string
     */
    public function getHtml()
    {
        $htmlId = $this->getHtmlId();
        $formWidget = $this->getForm()->getParent();
        $layout = $formWidget->getLayout();
        $tabsBlock = $layout->createBlock(
            \Sirv\Magento2\Block\Adminhtml\Settings\Tabs::class,
            '',
            ['data' => ['tabs_data' => $this->tabsData]]
        );

        return '<div id="' . $htmlId . '">' . $tabsBlock->toHtml() . '</div>';
    }

    /**
     * Set tabs data
     *
     * @param array $data
     * @return void
     */
    public function setTabsData($data)
    {
        $this->tabsData = $data;
    }
}
