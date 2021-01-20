<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit;

/**
 * Adminhtml settings form
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
     * @var \MagicToolbox\Sirv\Helper\Data\Backend
     */
    protected $dataHelper = null;

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
     * @param \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $data = []
    ) {
        $this->moduleDirReader = $modulesReader;
        $this->dataHelper = $dataHelper;
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
                    'class' => 'mt-config',
                ]
            ]
        );

        /** @var \Magento\Backend\Block\Widget\Form\Renderer\Element $elementRenderer */
        //$elementRenderer = \Magento\Framework\Data\Form::getElementRenderer();

        /** @var \Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element $fieldsetElementRenderer */
        $fieldsetElementRenderer = \Magento\Framework\Data\Form::getFieldsetElementRenderer();
        $fieldsetElementRenderer->setTemplate('MagicToolbox_Sirv::widget/form/renderer/fieldset/element.phtml');

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

        $config = $this->dataHelper->getConfig();

        $currentScope = $this->dataHelper->getConfigScope();
        $currentScopeId = $this->dataHelper->getConfigScopeId();
        $parentScope = $this->dataHelper->getParentConfigScope();

        if (isset($xml->notice)) {
            $fieldset = $form->addFieldset('sirv_group_fieldset_notice', ['legend' => '']);
            $fieldset->addField('mt-config-notice', 'label', [
                'label' => null,
                'after_element_html' => (string)$xml->notice
            ]);
        }

        $email = isset($config['email']) ? $config['email'] : '';
        $password = isset($config['password']) ? $config['password'] : '';
        $account = isset($config['account']) ? $config['account'] : '';
        $isNewAccount = isset($config['account_exists']) ? ($config['account_exists'] == 'no') : false;

        $xpaths = [];//NOTE: do not display these fields

        if (empty($email) || empty($password) || empty($account)) {
            $xpaths[] = '/settings/group[not(@id="user")]';

            if (empty($email) || empty($password)) {
                $xpaths[] = '/settings/group[@id="user"]/fields/field[name="account"]';
            } else {
                $fieldNames = [
                    'email',
                    'password',
                    'account_exists',
                    'first_and_last_name',
                    'alias',
                    'register',
                ];
                $fieldNames = 'name="' . implode('" or name="', $fieldNames) . '"';
                $xpaths[] = '/settings/group[@id="user"]/fields/field[' . $fieldNames . ']';
            }
        } else {
            $xpaths[] = '/settings/group[@id="user"]';
        }

        if ($currentScope != 'default') {
            $defaultProfileOptions = $this->dataHelper->getDefaultProfileOptions();
            $defaultProfileOptions = array_keys($defaultProfileOptions);
            $fieldNames = 'name="' . implode('" or name="', $defaultProfileOptions) . '"';
            $xpaths[] = '/settings/group/fields/field[' . $fieldNames . ']';
            $xpaths[] = '/settings/group[@id="synchronization"]';
            $xpaths[] = '/settings/group[@id="usage"]';
        }

        $support = $this->getRequest()->getParam('support');
        if ($support !== 'true') {
            $xpaths[] = '/settings/group[@id="support"]';
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
            $fieldset = $form->addFieldset(
                'sirv_group_fieldset_' . (string)$group['id'],
                ['legend' => __((string)$group->label)]
            );

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

                $suffix = $type == 'checkboxes' ? '[]' : '';
                $fieldConfig = [
                    'label'    => $label,
                    'title'    => $title,
                    'name'     => 'mt-config[' . $name . ']' . $suffix,
                    'note'     => (string)$field->notice,
                    'class'    => 'mt-option',
                    'required' => $required,
                ];

                if ($value !== null) {
                    if ($type == 'checkboxes') {
                        $fieldConfig['checked'] = explode(',', $value);
                    } else {
                        $fieldConfig['value'] = $value;
                    }
                }

                if (isset($field->tooltip)) {
                    $fieldConfig['tooltip'] = (string)$field->tooltip;
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

                if (isset($field->can_hide_select)) {
                    $fieldConfig['can_hide_select'] = true;
                }

                $typeClass = isset($field->type_class) ? (string)$field->type_class : false;
                if ($typeClass) {
                    $fieldset->addType($type, $typeClass);
                }

                if ($type == 'select' || $type == 'radios' || $type == 'checkboxes') {
                    $fieldConfig['values'] = [];
                    foreach ($field->options->option as $option) {
                        $fieldConfig['values'][] = [
                            'value' => (string)$option->value,
                            'label' => (string)$option->label
                        ];
                    }
                }

                if ($type == 'radios') {
                    $fieldConfig['in_a_row'] = isset($field->in_a_row) ? true : false;
                }

                switch ($name) {
                    case 'first_and_last_name':
                        // no break
                    case 'alias':
                        $fieldConfig['disabled'] = !$isNewAccount;
                        // no break
                    case 'register':
                        $fieldConfig['hidden'] = !$isNewAccount;
                        break;
                    case 'connect':
                        $fieldConfig['hidden'] = $isNewAccount;
                        break;
                    case 'account':
                        $accounts = $this->dataHelper->getSirvUsersList();
                        foreach ($accounts as $account) {
                            $fieldConfig['values'][] = ['value' => $account, 'label' => $account];
                        }
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
                    case 'image_quality':
                        $url = $this->getUrl('adminhtml/system_config/edit', [
                            'section' => 'system'
                        ]);
                        $url .= '#system_upload_configuration-link';
                        $fieldConfig['tooltip'] = str_replace('{{URL}}', $url, $fieldConfig['tooltip']);

                        $fieldConfig['values'][] = [
                            'value' => '',
                            'label' => ' ',
                            'disabled' => 'true',
                        ];
                        $labels = [
                            100 => '100% - Extreme quality (huge filesize)',
                            90 => '90% - Very high quality',
                            80 => '80% - High quality (Sirv recommended default)',
                            65 => '65% - Medium quality',
                            40 => '40% - Low quality',
                            20 => '20% - Very low quality',
                        ];
                        for ($i = 100; $i > 0; $i--) {
                            $fieldConfig['values'][] = [
                                'value' => $i,
                                'label' => isset($labels[$i]) ? $labels[$i] : $i . '%'
                            ];
                        }
                        break;
                    case 'magento_watermark':
                        $url = $this->getUrl('theme/design_config', []);
                        $fieldConfig['tooltip'] = str_replace('{{URL}}', $url, $fieldConfig['tooltip']);
                        break;
                    case 'auto_fetch':
                        $fieldConfig['value'] = $this->dataHelper->syncConfig('auto_fetch');
                        break;
                    case 'url_prefix':
                        $urlPrefix = $this->dataHelper->syncConfig('url_prefix');
                        $domains = $this->dataHelper->getDomains();
                        if (!empty($urlPrefix) && !in_array($urlPrefix, $domains)) {
                            $fieldConfig['values'][] = [
                                'value' => $urlPrefix,
                                'label' => preg_replace('#https?://#i', '', rtrim($urlPrefix, '/'))
                            ];
                        }
                        foreach ($domains as $domain) {
                            $fieldConfig['values'][] = [
                                'value' => $domain,
                                'label' => preg_replace('#https?://#i', '', rtrim($domain, '/'))
                            ];
                        }
                        $fieldConfig['value'] = empty($urlPrefix) ? reset($domains) : $urlPrefix;
                        break;
                    case 'image_folder':
                        //NOTE: for sync 'cdn_url' option
                        $config['cdn_url'] = $this->dataHelper->syncConfig('cdn_url');
                    case 'product_assets_folder':
                        $valuePrefix = isset($config['bucket']) ? $config['bucket'] : $config['account'];
                        $valuePrefix = '//' . $valuePrefix . '.sirv.com/';
                        if (isset($config['cdn_url']) && is_string($config['cdn_url'])) {
                            $cdn = trim($config['cdn_url']);
                        } else {
                            $cdn = '';
                        }
                        if (!empty($cdn)) {
                            $valuePrefix = '//' . preg_replace('#^[^/]*//#', '', $cdn);
                            $valuePrefix = rtrim($valuePrefix, '/') . '/';
                        }
                        /* $fieldConfig['before_element_html'] = $valuePrefix; */
                        $fieldConfig['value_prefix'] = $valuePrefix;
                        break;
                    case 'viewer_contents':
                        $data = $this->dataHelper->getAccountUsageData();
                        if (!isset($data['plan']) ||
                            !isset($data['plan']['name']) ||
                            preg_match('#beta|free|demo#i', $data['plan']['name'])) {
                            $fieldConfig['note'] .= '<span style="color: red;">Sirv assets cannot be used with Free plan!</span>';
                        }
                        break;
                    case 'smv_js_options':
                        $fieldConfig['rows'] = 7;
                        break;
                    case 'smv_max_height':
                        $fieldConfig['after_element_html'] = ' px';
                        break;
                    case 'assets_cache':
                        $fieldConfig['note'] = str_replace(
                            '{{URL}}',
                            $this->getUrl('sirv/ajax/assets', []),
                            $fieldConfig['note']
                        );
                        break;
                    case 'delete_cached_images':
                        $data = $this->dataHelper->getMagentoCatalogImagesCacheData();
                        $fieldConfig['note'] = str_replace(
                            '{{COUNT}}',
                            $data['count'] . ' image' . ($data['count'] != 1 ? 's' : ''),
                            $fieldConfig['note']
                        );
                        $fieldConfig['note'] = str_replace(
                            '{{URL}}',
                            $this->getUrl('*/*/flushmagentoimagescache', []),
                            $fieldConfig['note']
                        );
                        break;
                }

                $fieldConfig['parent_scope'] = $parentScope;
                if ($fieldConfig['parent_scope']) {
                    $currentScopeValue = $this->dataHelper->getConfig($name, $currentScope);
                    if ($currentScopeValue !== null) {
                        $fieldConfig['has_own_value'] = true;
                    } else {
                        $fieldConfig['has_own_value'] = false;
                        $fieldConfig['disabled'] = true;
                    }
                }

                $field = $fieldset->addField('mt-' . $name, $type, $fieldConfig);
            }
        }

        unset($xml);

        $this->setForm($form);

        $this->setChild(
            'form_after',
            $this->getLayout()->createBlock(
                \Magento\Framework\View\Element\Template::class
            )->setTemplate('MagicToolbox_Sirv::widget/form/form_after.phtml')
        );

        return parent::_prepareForm();
    }
}
