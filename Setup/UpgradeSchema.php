<?php

namespace MagicToolbox\Sirv\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * DB schema upgrades
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 *
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
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
     * Upgrades DB schema
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        //NOTE: 'schema_version' from `setup_module` table
        $schemaVersion = $context->getVersion();

        if (empty($schemaVersion)) {
            //NOTE: skip upgrade when install
            return;
        }

        $setup->startSetup();

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);

        if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            //NOTE: fix for 'schema_version' < 2.0.0
            if (version_compare($schemaVersion, '2.0.0', '<')) {
                //NOTE: remove wrong index
                $indexList = [];
                foreach ($connection->getIndexList($tableName) as $indexData) {
                    if (!is_array($indexData['COLUMNS_LIST'])) {
                        continue;
                    }
                    if (in_array('name', $indexData['COLUMNS_LIST'])) {
                        $indexList[] = $indexData['KEY_NAME'];
                    }
                }
                foreach ($indexList as $indexName) {
                    $connection->dropIndex($tableName, $indexName);
                }
                //NOTE: add right index
                $indexName = $setup->getIdxName(
                    $tableName,
                    ['name'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                );
                $connection->addIndex(
                    $tableName,
                    $indexName,
                    ['name'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                );
            }
        } else {
            //NOTE: create table if it doesn't exist
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
        }

        $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

        $doCreateTable = true;
        if ($setup->tableExists(self::SIRV_CACHE_TABLE)) {
            if ($connection->tableColumnExists($tableName, 'last_checked')) {
                $connection->dropTable($tableName);
            } else {
                $doCreateTable = false;
            }
        }

        if ($doCreateTable) {
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
        } else {
            if ($connection->tableColumnExists($tableName, 'url')) {
                $connection->changeColumn(
                    $tableName,
                    'url',
                    'path',
                    [
                        'type' => Table::TYPE_TEXT,
                        'length' => 255,
                        'nullable'  => false,
                        'default' => '',
                        'comment' => 'Relative file path',
                    ]
                );
                $connection->query(
                    "ALTER TABLE `{$tableName}` MODIFY `path` varchar(255) BINARY NOT NULL DEFAULT '' COMMENT 'Relative file path'"
                );
            }
            if (!$connection->tableColumnExists($tableName, 'path_type')) {
                $connection->addColumn(
                    $tableName,
                    'path_type',
                    [
                        'type' => Table::TYPE_SMALLINT,
                        'unsigned' => true,
                        'nullable'  => false,
                        'default' => 0,
                        'comment' => 'Path type',
                        'after' => 'path'
                    ]
                );
            }
            if (!$connection->tableColumnExists($tableName, 'status')) {
                $connection->addColumn(
                    $tableName,
                    'status',
                    [
                        'type' => Table::TYPE_SMALLINT,
                        'unsigned' => true,
                        'nullable'  => false,
                        'default' => 0,
                        'comment' => 'Sync status',
                        'after' => 'path_type'
                    ]
                );
            }

            //NOTE: fix for 'schema_version' < 2.0.0
            if (version_compare($schemaVersion, '2.0.0', '<')) {
                $indexName = $setup->getIdxName(
                    $tableName,
                    ['url'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                );
                $connection->dropIndex($tableName, $indexName);

                $indexName = $setup->getIdxName(
                    $tableName,
                    ['path'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                );
                $connection->addIndex(
                    $tableName,
                    $indexName,
                    ['path'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                );
            }
        }

        $setup->endSetup();
    }
}
