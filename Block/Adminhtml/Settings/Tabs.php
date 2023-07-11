<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings;

/**
 * Adminhtml settings tabs
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * Path to template file
     *
     * @var string
     */
    protected $_template = 'Sirv_Magento2::widget/tabs.phtml';

    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sirv_config_tab');
    }

    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $tabsData = $this->_getData('tabs_data');
        foreach ($tabsData as $id => $data) {
            $this->addTab($id, $data);
        }

        return parent::_prepareLayout();
    }
}
