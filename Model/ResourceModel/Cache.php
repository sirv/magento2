<?php

namespace MagicToolbox\Sirv\Model\ResourceModel;

/**
 * Cache resource model
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Cache extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: resource initialization
        $this->_init('sirv_cache', 'id');
    }

    /**
     * Delete rows by status
     *
     * @param string $status
     * @return $this
     */
    public function deleteByStatus($status)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['status = ?' => $status]);

        return $this;
    }

    /**
     * Delete rows by ids
     *
     * @param array $ids
     * @return $this
     */
    public function deleteByIds($ids)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['id IN (?)' => $ids]);

        return $this;
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
