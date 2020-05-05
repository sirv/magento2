<?php

namespace MagicToolbox\Sirv\Model;

/**
 * Cache model
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Cache extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('MagicToolbox\Sirv\Model\ResourceModel\Cache');
    }

    /**
     * Clearing object's data
     *
     * @return $this
     */
    protected function _clearData()
    {
        $this->_hasDataChanges = false;
        $this->_isDeleted = false;
        $this->_isObjectNew = null;
        $this->_origData = null;
        $this->storedData = [];
        $this->_data = [];
        return $this;
    }
}
