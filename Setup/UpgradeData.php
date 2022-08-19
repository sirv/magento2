<?php

namespace Sirv\Magento2\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Data upgrades
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
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

        if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $setup->getConnection();

            $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);
            $names = $connection->fetchCol(
                $connection->select()->from($tableName, 'name')->where('scope_id = ?', 0)
            );
            $names = array_flip($names);

            $moduleEtcPath = $this->moduleDirReader->getModuleDir(
                \Magento\Framework\Module\Dir::MODULE_ETC_DIR,
                'Sirv_Magento2'
            );

            $useErrors = libxml_use_internal_errors(true);
            $xml = simplexml_load_file($moduleEtcPath . '/settings.xml');
            libxml_use_internal_errors($useErrors);

            $data = [];
            if ($xml) {
                $fields = $xml->xpath('/settings/group/fields/field');
                foreach ($fields as $field) {
                    $name = (string)$field->name;
                    if (!isset($field->install) || isset($names[$name])) {
                        continue;
                    }
                    $data[] = [
                        'scope' => 'default',
                        'scope_id' => 0,
                        'name' => $name,
                        'value' => (string)$field->value
                    ];
                }

                if (!isset($names['installation_date'])) {
                    $data[] = [
                        'scope' => 'default',
                        'scope_id' => 0,
                        'name' => 'installation_date',
                        'value' => time()
                    ];
                }

                unset($xml);
            }

            if (!empty($data)) {
                $connection->insertMultiple($tableName, $data);
            }
        }

        $setup->endSetup();
    }
}
