<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit;

/**
 * Adminhtml settings form
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Form extends \Magento\Backend\Block\Widget\Form\Generic
{
    /**
     * Module configuration file reader
     *
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleDirReader = null;

    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Sync helper factory
     *
     * @var \MagicToolbox\Sirv\Helper\SyncFactory $syncHelperFactory
     */
    protected $syncHelperFactory = null;

    /**
     * Component registrar
     *
     * @var \Magento\Framework\Component\ComponentRegistrar
     */
    protected $componentRegistrar = null;

    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Framework\Module\Dir\Reader $modulesReader
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @param \Magento\Framework\Component\ComponentRegistrar $componentRegistrar
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \MagicToolbox\Sirv\Helper\Data $dataHelper,
        \MagicToolbox\Sirv\Helper\SyncFactory $syncHelperFactory,
        \Magento\Framework\Component\ComponentRegistrar $componentRegistrar,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $data = []
    ) {
        $this->moduleDirReader = $modulesReader;
        $this->dataHelper = $dataHelper;
        $this->syncHelperFactory = $syncHelperFactory;
        $this->componentRegistrar = $componentRegistrar;
        $this->objectManager = $objectManager;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create(
            [
                'data' => [
                    'id' => 'edit_form',
                    'action' => $this->getData('action'),
                    'method' => 'post',
                    'class' => 'magictoolbox-config',
                ]
            ]
        );

        $form->setUseContainer(true);//NOTE: to display form tag

        $moduleEtcPath = $this->moduleDirReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR, 'MagicToolbox_Sirv');
        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($moduleEtcPath . '/settings.xml');
        libxml_use_internal_errors($useErrors);

        if (!$xml) {
            $fieldset = $form->addFieldset('sirv_group_fieldset_notice', ['legend' => '']);
            $fieldset->addField('mt-config-notice', 'label', [
                'label' => null,
                'after_element_html' => '<span class="mt-config-error-notice">Error: can\'t get configuration settings! Make sure the module is installed correctly.</span>'
            ]);
            $this->setForm($form);

            return parent::_prepareForm();
        }

        if (isset($xml->notice)) {
            $fieldset = $form->addFieldset('sirv_group_fieldset_notice', ['legend' => '']);
            $fieldset->addField('mt-config-notice', 'label', [
                'label' => null,
                'after_element_html' => (string)$xml->notice
            ]);
        }

        $version = $this->getModuleVersion('MagicToolbox_Sirv');
        if ($version) {
            $afterElementHtml = 'Version: ' . $version;

            $latestVersion = $this->getModuleLatestVersion();
            if ($latestVersion && version_compare($version, $latestVersion, '<')) {
                $afterElementHtml .= '&nbsp;&nbsp;&nbsp;&nbsp;Latest version: ' . $latestVersion . ' (<a href="https://sirv.com/integration/magento/" target="_blank" style="margin: 0;">download zip</a>)';
            }

            $fieldset = $form->addFieldset('sirv_group_fieldset_version', ['legend' => '']);
            $fieldset->addField('mt-config-version', 'label', [
                'label' => null,
                'after_element_html' => $afterElementHtml
            ]);
        }

        $requiredVersion = '1.6.0';
        $outdatedModules = $this->getOutdatedModules($requiredVersion);
        if (!empty($outdatedModules)) {
            $fieldset = $form->addFieldset('sirv_group_fieldset_outdated_notice', ['legend' => '']);
            $messages = [];
            foreach ($outdatedModules as $name => $version) {
                $messages[] = 'Notice: you have installed ' . $name .' module by version ' . $version . '.' .
                    ' Please, update it at least to version ' . $requiredVersion;
            }
            $messages = '<span class="mt-outdated-error-notice">' . implode('</span><br/><span class="mt-outdated-error-notice">', $messages) . '</span>';
            $fieldset->addField('mt-outdated-notice', 'label', [
                'label' => null,
                'after_element_html' => $messages
            ]);
        }

        $config = $this->dataHelper->getConfig();

        $email = isset($config['email']) ? $config['email'] : '';
        $password = isset($config['password']) ? $config['password'] : '';
        $account = isset($config['account']) ? $config['account'] : '';

        $xpaths = [];//NOTE: do not display these fields

        if (empty($email) || empty($password) || empty($account)) {
            $xpaths[] = '/settings/group[not(@id="user")]';

            if (empty($email) || empty($password)) {
                $xpaths[] = '/settings/group[@id="user"]/fields/field[name="account"]';
            } else {
                $xpaths[] = '/settings/group[@id="user"]/fields/field[name="email" or name="password"]';
            }
        } else {
            $xpaths[] = '/settings/group[@id="user"]';
        }

        $stats = $this->dataHelper->getSirvAccountStats();
        if (!$stats) {
            $xpaths[] = '/settings/group[@id="account_info"]/fields/field[name="plan" or name="allowance"]';
            $xpaths[] = '/settings/group[@id="account_stats"]';
        }

        //NOTE: to hide unnecessary options
        foreach ($xpaths as $xpath) {
            /** @var SimpleXMLElement[] $nodes */
            $nodes = $xml->xpath($xpath);
            foreach ($nodes as $node) {
                unset($node[0]);
            }
        }

        foreach ($xml->group as $group) {
            $fieldset = $form->addFieldset('sirv_group_fieldset_' . (string)$group['id'], ['legend' => __((string)$group->label)]);

            if (isset($group->notice)) {
                $field = $fieldset->addField('mt-' . (string)$group['id'] . '-notice', 'label', [
                    'label' => null,
                    'after_element_html' => (string)$group->notice,
                ]);
            }

            foreach ($group->fields->field as $field) {
                $type = (string)$field->type;
                $name = (string)$field->name;
                $label = isset($field->label) ? (string)$field->label : null;
                $title = isset($field->title) ? (string)$field->title : (empty($label) ? '' : $label);
                $value = isset($config[$name]) ? $config[$name] : (isset($field->value) ? (string)$field->value : null);
                $required = isset($field->required) && ((string)$field->required == 'true') ? true : false;

                $fieldConfig = [
                    'label'     => $label,
                    'title'     => $title,
                    'name'      => 'magictoolbox[' . $name . ']',
                    'note'      => (string)$field->notice,
                    'class'     => 'mt-option',
                    'required'  => $required,
                ];

                if ($value !== null) {
                    $fieldConfig['value'] = $value;
                }

                if (isset($field->autofocus)) {
                    $fieldConfig['autofocus'] = null;
                }

                if (isset($field->autocomplete)) {
                    $fieldConfig['autocomplete'] = (string)$field->autocomplete;
                }

                if (isset($field->placeholder)) {
                    $fieldConfig['placeholder'] = (string)$field->placeholder;
                }

                $typeClass = isset($field->type_class) ? (string)$field->type_class : false;
                if ($typeClass) {
                    $fieldset->addType($type, $typeClass);
                }

                if ($type == 'select') {
                    $fieldConfig['values'] = [];
                    foreach ($field->options->option as $option) {
                        $fieldConfig['values'][] = [
                            'value' => (string)$option->value,
                            'label' => (string)$option->label
                        ];
                    }
                }

                if ($type == 'note') {
                    $fieldConfig['text'] = isset($field->text) ? (string)$field->text : ($value === null ? '' : $value);
                }

                switch ($name) {
                    case 'account':
                        $accounts = $this->dataHelper->getSirvUsersList();
                        foreach ($accounts as $account) {
                            $fieldConfig['values'][] = ['value' => $account, 'label' => $account];
                        }
                        break;
                    case 'network':
                        //NOTE: to update network value
                        $network = $this->dataHelper->syncCdnConfig();

                        $fieldConfig['value'] = $network;
                        break;
                    case 'profile':
                        $profiles = $this->dataHelper->getProfiles();
                        if (!in_array($fieldConfig['value'], $profiles)) {
                            $fieldConfig['value'] = 'Default';
                        }
                        natsort($profiles);
                        //NOTE: Default profile is already specified in settings.xml
                        $key = array_search('Default', $profiles);
                        if ($key !== false) {
                            unset($profiles[$key]);
                        }

                        foreach ($profiles as $profile) {
                            $fieldConfig['values'][] = ['value' => $profile, 'label' => $profile];
                        }
                        break;
                    case 'synchronizer':
                        $syncHelper = $this->syncHelperFactory->create();
                        $fieldConfig['value'] = $syncHelper->getSyncData();
                        break;
                    case 'plan':
                        $fieldConfig['text'] = str_replace('{{name}}', $stats['plan']['name'], $fieldConfig['text']);
                        break;
                    case 'allowance':
                        $fieldConfig['text'] = str_replace('{{storage_limit}}', $stats['plan']['storage_limit'], $fieldConfig['text']);
                        $fieldConfig['text'] = str_replace('{{data_transfer_limit}}', $stats['plan']['data_transfer_limit'], $fieldConfig['text']);
                        break;
                    case 'user':
                        $fieldConfig['text'] = str_replace('{{user}}', $email, $fieldConfig['text']);
                        $url = $this->getUrl('*/*/disconnect');
                        $fieldConfig['text'] = str_replace('{{url}}', $url, $fieldConfig['text']);
                        break;
                    case 'stats':
                        $fieldConfig['value'] = $stats;
                        break;
                }

                $field = $fieldset->addField('mt-' . $name, $type, $fieldConfig);
            }
        }

        unset($xml);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get module version
     *
     * @param string $name
     * @return string | bool
     */
    protected function getModuleVersion($name)
    {
        $version = false;
        $moduleDir = $this->componentRegistrar->getPath(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $name
        );
        $moduleInfo = json_decode(file_get_contents($moduleDir . '/composer.json'));
        if (is_object($moduleInfo) && isset($moduleInfo->version)) {
            $version = $moduleInfo->version;
        }

        return $version;
    }

    /**
     * Get the latest version of the module
     *
     * @return string|bool
     */
    protected function getModuleLatestVersion()
    {
        $version = false;
        $hostname = 'www.magictoolbox.com';
        $errno = 0;
        $errstr = '';
        $path = 'api/platform/sirvmagento2/version/?t=' . time();
        $level = error_reporting(0);
        $handle = fsockopen('ssl://' . $hostname, 443, $errno, $errstr, 30);
        error_reporting($level);
        if ($handle) {
            $response = '';
            $headers  = "GET /{$path} HTTP/1.1\r\n";
            $headers .= "Host: {$hostname}\r\n";
            $headers .= "Connection: Close\r\n\r\n";
            fwrite($handle, $headers);
            while (!feof($handle)) {
                $response .= fgets($handle);
            }
            fclose($handle);
            $response = substr($response, strpos($response, "\r\n\r\n") + 4);
            $responseObj = json_decode($response);
            if (is_object($responseObj) && isset($responseObj->version)) {
                $match = [];
                if (preg_match('#v([0-9]++(?:\.[0-9]++)*+)#is', $responseObj->version, $match)) {
                    $version = $match[1];
                }
            }
        }

        return $version;
    }

    /**
     * Get enabled module's data (name and version)
     *
     * @return array
     */
    protected function getModulesData()
    {
        static $data = null;

        if ($data !== null) {
            return $data;
        }

        $cache = $this->dataHelper->getAppCache();
        $cacheId = 'magictoolbox_modules_data';

        $data = $cache->load($cacheId);
        if (false !== $data) {
            $data = $this->dataHelper->getUnserializer()->unserialize($data);
            return $data;
        }

        $data = [];

        $mtModules = [
            'MagicToolbox_Magic360',
            'MagicToolbox_MagicZoomPlus',
            'MagicToolbox_MagicZoom',
            'MagicToolbox_MagicThumb',
            'MagicToolbox_MagicScroll',
            'MagicToolbox_MagicSlideshow',
        ];

        $enabledModules = $this->objectManager->create(\Magento\Framework\Module\ModuleList::class)->getNames();

        foreach ($mtModules as $name) {
            if (in_array($name, $enabledModules)) {
                $data[$name] = $this->getModuleVersion($name);
            }
        }

        $serializer = $this->dataHelper->getSerializer();
        //NOTE: cache lifetime (in seconds)
        $cache->save($serializer->serialize($data), $cacheId, [], 600);

        return $data;
    }

    /**
     * Check for outdated modules
     *
     * @param string $requiredVersion
     * @return array
     */
    protected function getOutdatedModules($requiredVersion)
    {
        $outdatedModules = [];
        $modules = $this->getModulesData();
        foreach ($modules as $name => $version) {
            if (version_compare($version, $requiredVersion, '<')) {
                $outdatedModules[$name] = $version;
            }
        }
        return $outdatedModules;
    }
}
