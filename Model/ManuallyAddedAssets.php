<?php

namespace Sirv\Magento2\Model;

/**
 * Manually added assets model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ManuallyAddedAssets extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Asset types
     */
    const UNKNOWN_TYPE = 0;
    const IMAGE_ASSET = 1;
    const VIDEO_ASSET = 2;
    const SPIN_ASSET = 3;
    const MODEL_ASSET = 4;

    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('Sirv\Magento2\Model\ResourceModel\ManuallyAddedAssets');
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
