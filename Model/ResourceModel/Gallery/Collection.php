<?php

namespace MagicToolbox\Sirv\Model\ResourceModel\Gallery;

/**
 * Gallery collection
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('MagicToolbox\Sirv\Model\Gallery', 'MagicToolbox\Sirv\Model\ResourceModel\Gallery');
    }
}
