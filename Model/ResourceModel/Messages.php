<?php

namespace Sirv\Magento2\Model\ResourceModel;

/**
 * Messages resource model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Messages extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: resource initialization
        $this->_init('sirv_messages', 'path');
        $this->_isPkAutoIncrement = false;
    }

    /**
     * Delete all rows
     *
     * @return $this
     */
    public function deleteAll()
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->truncateTable($this->getMainTable());

        return $this;
    }
}
