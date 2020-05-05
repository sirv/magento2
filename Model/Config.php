<?php

namespace MagicToolbox\Sirv\Model;

/**
 * Config model
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Config extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('MagicToolbox\Sirv\Model\ResourceModel\Config');
    }

    /**
     * Clearing object's data
     *
     * @return $this
     */
    protected function _clearData()
    {
        $this->_data = [];
        return $this;
    }
}
