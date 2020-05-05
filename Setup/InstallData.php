<?php

namespace MagicToolbox\Sirv\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Interface for data installs
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 *
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
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
     * Module configuration file reader
     *
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleDirReader;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Module\Dir\Reader $modulesReader
     * @return void
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $modulesReader
    ) {
        $this->moduleDirReader = $modulesReader;
    }

    /**
     * Installs data
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /**
         * Install default config
         */

        $moduleEtcPath = $this->moduleDirReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR, 'MagicToolbox_Sirv');
        $fileName = $moduleEtcPath . '/settings.xml';

        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($fileName);
        libxml_use_internal_errors($useErrors);

        $data = [];
        if ($xml) {
            $fields = $xml->xpath('/settings/group/fields/field');
            foreach ($fields as $field) {
                if (!isset($field->install)) {
                    continue;
                }
                $data[] = [
                    'name' => (string)$field->name,
                    'value' => (string)$field->value
                ];
            }
            unset($xml);
        }

        if (!empty($data)) {
            if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
                $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);
                /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
                $connection = $setup->getConnection();
                $connection->truncateTable($tableName);
                $connection->insertMultiple($tableName, $data);
            }
        }

        $setup->endSetup();
    }
}
