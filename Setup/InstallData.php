<?php

namespace MagicToolbox\Sirv\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Interface for data installs
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
