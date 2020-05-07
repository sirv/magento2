<?php

namespace MagicToolbox\Sirv\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Interface for data upgrades
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * Config table name
     */
    const SIRV_CONFIG_TABLE = 'sirv_config';

    /**
     * Cache table name
     */
    const SIRV_CACHE_TABLE = 'sirv_cache';

    /**
     * Upgrades data
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        //NOTE: 'data_version' from `setup_module` table
        $dataVersion = $context->getVersion();

        if (empty($dataVersion)) {
            //NOTE: skip upgrade when install
            return;
        }

        $setup->startSetup();

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        //NOTE: fix for 'data_version' < 2.0.0
        if (version_compare($dataVersion, '2.0.0', '<')) {
            if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
                $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);
                $bind = ['value' => 'false'];
                $where = ['`name` = ?' => 'enabled'];
                $connection->update($tableName, $bind, $where);
            }

            if ($setup->tableExists(self::SIRV_CACHE_TABLE)) {
                $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

                if ($connection->tableColumnExists($tableName, 'path_type')) {
                    $bind = ['path_type' => \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH];
                    $where = [
                        '`path_type` = ?' => \MagicToolbox\Sirv\Helper\Sync::UNKNOWN_PATH,
                        new \Zend_Db_Expr("`path` LIKE '/catalog/category/%' OR `path` LIKE '/catalog/product/%'"),
                    ];
                    $connection->update($tableName, $bind, $where);

                    $bind = ['path_type' => \MagicToolbox\Sirv\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH];
                    $where = [
                        '`path_type` = ?' => \MagicToolbox\Sirv\Helper\Sync::UNKNOWN_PATH,
                        new \Zend_Db_Expr("`path` LIKE '/_/_/%' OR `path` LIKE '/watermark/%' OR `path` LIKE '/placeholder/%'"),
                    ];
                    $connection->update($tableName, $bind, $where);
                }

                if ($connection->tableColumnExists($tableName, 'status')) {
                    $bind = ['status' => \MagicToolbox\Sirv\Helper\Sync::IS_SYNCED];
                    $where = ['status = ?' => \MagicToolbox\Sirv\Helper\Sync::IS_UNDEFINED];
                    $connection->update($tableName, $bind, $where);
                }
            }
        }

        $setup->endSetup();
    }
}
