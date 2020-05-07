<?php

namespace MagicToolbox\Sirv\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * DB schema installs
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
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
     * Assets table name
     */
    const SIRV_ASSETS_TABLE = 'sirv_assets';

    /**
     * Installs DB schema
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);

        if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'ID'
        )->addColumn(
            'name',
            Table::TYPE_TEXT,
            64,
            ['nullable'  => false],
            'Name'
        )->addColumn(
            'value',
            Table::TYPE_TEXT,
            null,
            ['nullable'  => false],
            'Value'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['name'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['name'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv configuration'
        );

        $connection->createTable($table);

        $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

        if ($setup->tableExists(self::SIRV_CACHE_TABLE)) {
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Primary key'
        )->addColumn(
            'path',
            Table::TYPE_TEXT,
            255,
            ['nullable'  => false, 'default' => ''],
            'Relative file path'
        )->addColumn(
            'path_type',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable'  => false, 'default' => 0],
            'Path type'
        )->addColumn(
            'status',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable'  => false, 'default' => 0],
            'Sync status'
        )->addColumn(
            'modification_time',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable'  => false, 'default' => 0],
            'Modification time'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['path'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['path'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv cache'
        );

        $connection->createTable($table);
        $connection->query(
            "ALTER TABLE `{$tableName}` MODIFY `path` varchar(255) BINARY NOT NULL DEFAULT '' COMMENT 'Relative file path'"
        );

        $tableName = $setup->getTable(self::SIRV_ASSETS_TABLE);

        if ($setup->tableExists(self::SIRV_ASSETS_TABLE)) {
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'product_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false, 'primary' => true],
            'Product ID'
        )->addColumn(
            'contents',
            Table::TYPE_TEXT,
            null,
            ['nullable'  => false],
            'Contents'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['product_id'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['product_id'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv assets'
        );

        $connection->createTable($table);

        $setup->endSetup();
    }
}
