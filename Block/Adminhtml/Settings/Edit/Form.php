<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit;

/**
 * Adminhtml settings form
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
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
     * @var \Sirv\Magento2\Helper\Data\Backend
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
     * @param \Sirv\Magento2\Helper\Data\Backend $dataHelper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \Sirv\Magento2\Helper\Data\Backend $dataHelper,
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

        /** @var \Magento\Backend\Block\Widget\Form\Renderer\Fieldset $fieldsetRenderer */
        $fieldsetRenderer = \Magento\Framework\Data\Form::getFieldsetRenderer();
        $fieldsetRenderer->setTemplate('Sirv_Magento2::widget/form/renderer/fieldset.phtml');

        /** @var \Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element $fieldsetElementRenderer */
        $fieldsetElementRenderer = \Magento\Framework\Data\Form::getFieldsetElementRenderer();
        $fieldsetElementRenderer->setTemplate('Sirv_Magento2::widget/form/renderer/fieldset/element.phtml');

        $form->setUseContainer(true);//NOTE: to display form tag

        $this->setForm($form);

        $moduleEtcPath = $this->moduleDirReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR, 'Sirv_Magento2');
        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($moduleEtcPath . '/settings.xml');
        libxml_use_internal_errors($useErrors);

        if (!$xml) {
            $fieldset = $form->addFieldset('sirv_group_fieldset_error_notice', ['legend' => '']);
            $fieldset->addField('mt-config-error-notice', 'label', [
                'label' => null,
                'after_element_html' => '<span class="mt-config-error-notice">Error: can\'t get configuration settings! Make sure the module is installed correctly.</span>'
            ]);

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
        $clientId = isset($config['client_id']) ? $config['client_id'] : '';
        $clientSecret = isset($config['client_secret']) ? $config['client_secret'] : '';

        $xpaths = [];//NOTE: do not display these fields

        $passwordRequired = empty($email) || ((empty($clientId) || empty($clientSecret)) && empty($password));

        $comment = '';
        if ($passwordRequired || empty($account)) {
            $xpaths[] = '/settings/group[not(@id="user")]';

            //NOTE: in case of a long period of time between entering the OTP code
            //      and selecting the account
            if (!$passwordRequired && empty($account) && !$this->dataHelper->getConfig('need_otp_code')) {
                $accounts = $this->dataHelper->getSirvAccounts();

                if (empty($accounts)) {
                    $responseCode = $this->dataHelper->getSirvClient()->getResponseCode();
                    if ($responseCode == 417) {
                        $this->dataHelper->saveConfig('need_otp_code', 'true');
                    } elseif ($responseCode != 200) {
                        $passwordRequired = true;
                        $this->dataHelper->saveConfig('display_credentials_rejected_message', 'true');
                        $this->dataHelper->deleteConfig('password');
                    }
                }
            }

            if ($passwordRequired) {
                $xpaths[] = '/settings/group[@id="user"]/fields/field[name="account"]';
                $xpaths[] = '/settings/group[@id="user"]/fields/field[name="otp_code"]';
                $this->dataHelper->deleteConfig('otp_code');
                if ($this->dataHelper->getConfig('display_credentials_rejected_message')) {
                    $comment = __(
                        'Your Sirv email or password were incorrect. Please check and try again or <a class="sirv-open-in-new-window" target="_blank" href="%1">reset your password</a>.',
                        'https://my.sirv.com/#/password/forgot'
                    );
                    $this->dataHelper->deleteConfig('display_credentials_rejected_message');
                }
            } elseif ($this->dataHelper->getConfig('need_otp_code')) {
                $fieldNames = [
                    'email',
                    'password',
                    'account_exists',
                    'first_and_last_name',
                    'alias',
                    'register',
                    'connect',
                    'account'
                ];
                $fieldNames = 'name="' . implode('" or name="', $fieldNames) . '"';
                $xpaths[] = '/settings/group[@id="user"]/fields/field[' . $fieldNames . ']';
                if ($this->dataHelper->getConfig('display_otp_code_rejected_message')) {
                    $comment = __(
                        'Your authentication code was incorrect. Please try again.'
                    );
                    $this->dataHelper->deleteConfig('display_otp_code_rejected_message');
                }
            } else {
                $fieldNames = [
                    'email',
                    'password',
                    'account_exists',
                    'first_and_last_name',
                    'alias',
                    'register',
                    'otp_code'
                ];
                $fieldNames = 'name="' . implode('" or name="', $fieldNames) . '"';
                $xpaths[] = '/settings/group[@id="user"]/fields/field[' . $fieldNames . ']';
            }
        } else {
            $this->dataHelper->deleteConfig('password');
            $this->dataHelper->deleteConfig('otp_code');
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

        $container = $form->addFieldset('sirv_fieldset_container', ['legend' => '']);

        $tabsData = [];
        $groupId = '';
        foreach ($xml->group as $group) {
            $groupId = (string)$group['id'];
            $fieldsetConfig = [];
            if ($groupId == 'user') {
                $fieldsetConfig['legend'] = __((string)$group->label);
                $fieldsetConfig['comment'] = $comment;
            }
            $fieldset = $container->addFieldset(
                'sirv_group_fieldset_' . $groupId,
                $fieldsetConfig
            );

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

                if (isset($field->field_group_legend)) {
                    $fieldConfig['field_group_legend'] = (string)$field->field_group_legend;
                }
                if (isset($field->field_group_comment)) {
                    $fieldConfig['field_group_comment'] = (string)$field->field_group_comment;
                }
                if (isset($field->field_group_separator)) {
                    $fieldConfig['field_group_separator'] = true;
                }

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

                if (isset($field->before_element_html)) {
                    $fieldConfig['before_element_html'] = (string)$field->before_element_html;
                }

                if (isset($field->after_element_html)) {
                    $fieldConfig['after_element_html'] = (string)$field->after_element_html;
                }

                if (isset($field->can_hide_select)) {
                    $fieldConfig['can_hide_select'] = true;
                }

                $typeClass = isset($field->type_class) ? (string)$field->type_class : false;
                if ($typeClass) {
                    $fieldset->addType($type, $typeClass);
                }

                if (in_array($type, ['select', 'radios', 'checkboxes', 'slides_order'])) {
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
                        $fieldConfig['first_name'] = isset($config['first_name']) ? $config['first_name'] : '';
                        $fieldConfig['last_name'] = isset($config['last_name']) ? $config['last_name'] : '';
                        $fieldConfig['value'] = '';
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
                        $accounts = $this->dataHelper->getSirvAccounts();
                        $accountsList = array_keys($accounts);
                        natsort($accountsList);
                        $irAccounts = [];//NOTE: accounts with an insufficient role
                        foreach ($accountsList as $account) {
                            $role = $accounts[$account];
                            if (in_array($role, ['primaryOwner', 'admin', 'owner'])) {
                                $fieldConfig['values'][] = [
                                    'value' => $account,
                                    'label' => $account
                                ];
                            } else {
                                $irAccounts[] = [
                                    'value' => $account,
                                    'label' => $account . ' (' . $role . ')',
                                    'disabled' => 'true'
                                ];
                            }
                        }
                        if (!empty($irAccounts)) {
                            $fieldConfig['values'][] = [
                                'value' => 'empty',
                                'label' => ' ',
                                'disabled' => 'true',
                            ];
                            $fieldConfig['values'][] = [
                                'value' => 'empty',
                                'label' => 'Accounts with an insufficient role:',
                                'disabled' => 'true',
                            ];
                            $fieldConfig['values'] = array_merge($fieldConfig['values'], $irAccounts);
                        }
                        break;
                    case 'js_modules':
                        $src = 'https://scripts.sirv.com/sirvjs/v3/sirv.js';
                        if (!empty($value) && strpos($value, 'all') === false) {
                            $src = 'https://scripts.sirv.com/sirvjs/v3/sirv.js?modules=' . $value;
                        }
                        $size = $this->dataHelper->getSirvJsFileSize($src);
                        $fieldConfig['note'] = str_replace('{{SIZE}}', $size, $fieldConfig['note']);
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
                        $fieldConfig['url_prefix'] = ['value' => '', 'values' => []];
                        $urlPrefix = $this->dataHelper->syncConfig('url_prefix');
                        $domains = $this->dataHelper->getDomains();
                        if (!empty($urlPrefix) && !in_array($urlPrefix, $domains)) {
                            $fieldConfig['url_prefix']['values'][] = [
                                'value' => $urlPrefix,
                                'label' => preg_replace('#https?://#i', '', rtrim($urlPrefix, '/'))
                            ];
                        }
                        foreach ($domains as $domain) {
                            $fieldConfig['url_prefix']['values'][] = [
                                'value' => $domain,
                                'label' => preg_replace('#https?://#i', '', rtrim($domain, '/'))
                            ];
                        }
                        $fieldConfig['url_prefix']['value'] = empty($urlPrefix) ? reset($domains) : $urlPrefix;
                        break;
                    case 'sub_alias':
                        $accountConfig = $this->dataHelper->getAccountConfig();
                        if (isset($accountConfig['aliases'])) {
                            foreach ($accountConfig['aliases'] as $_alias => $domain) {
                                $fieldConfig['values'][] = [
                                    'value' => $_alias,
                                    'label' => $domain
                                ];
                            }
                        }
                        if (count($fieldConfig['values']) < 2) {
                            continue 2;
                        }
                        if (!isset($fieldConfig['value']) || empty($fieldConfig['value'])) {
                            $fieldConfig['value'] = $config['account'];
                        }
                        break;
                    /*
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
                    */
                    case 'image_folder':
                        //NOTE: for sync 'cdn_url' option
                        $config['cdn_url'] = $this->dataHelper->syncConfig('cdn_url');//NOTE: is it still needed here?
                    case 'product_assets_folder':
                        $fieldConfig['value_prefix'] = $this->dataHelper->getSirvDomain(false) . '/';
                        break;
                    case 'copy_primary_images_to_magento':
                        $url = $this->getUrl('sirv/ajax/copyprimaryimages', []);
                        $fieldConfig['data-mage-init'] = '{"sirvCopyPrimaryImages": {"ajaxUrl":"' . $url . '"}}';
                        break;
                    case 'media_storage_info':
                        $fieldConfig['media_storage_info'] = $this->dataHelper->getMediaStorageInfo();
                        $url = $this->getUrl('sirv/ajax/mediastorageinfo', []);
                        $fieldConfig['data-mage-init'] = '{"sirvMediaStorageInfo": {"ajaxUrl":"' . $url . '"}}';
                        break;
                    case 'viewer_contents':
                        $data = $this->dataHelper->getAccountUsageData();
                        if (!isset($data['plan']) ||
                            !isset($data['plan']['name']) ||
                            preg_match('#free#i', $data['plan']['name'])) {
                            $fieldConfig['note'] .= '<span style="color: red;">To use Sirv assets, <a class="sirv-open-in-new-window" target="_blank" href="https://my.sirv.com/#/account/billing/plan">upgrade to a paid plan</a>.</span>';
                        }
                        break;
                    case 'excluded_pages':
                    case 'excluded_files':
                    case 'excluded_from_lazy_load':
                    case 'smv_js_options':
                    case 'custom_css':
                    case 'smv_custom_css':
                        $fieldConfig['rows'] = 7;
                        break;
                    case 'pinned_items':
                        $fieldConfig['value'] = json_decode($fieldConfig['value'], true);
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
                            $data['count'],
                            $fieldConfig['note']
                        );
                        $fieldConfig['note'] = str_replace(
                            '{{SUFFIX}}',
                            $data['count'] != 1 ? 's' : '',
                            $fieldConfig['note']
                        );
                        $fieldConfig['note'] = str_replace(
                            '{{URL}}',
                            $this->getUrl('*/*/flushmagentoimagescache', []),
                            $fieldConfig['note']
                        );
                        $fieldConfig['note'] = preg_replace(
                            "#\n++ *+#",
                            ' ',
                            $fieldConfig['note']
                        );
                        break;
                    case 'alt_text_rule':
                        $fieldConfig['after_element_html'] = str_replace(
                            '{{URL}}',
                            $this->getUrl('sirv/ajax/copyalttext', []),
                            $fieldConfig['after_element_html']
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

            if ($groupId == 'user') {
                continue;
            }

            $tabsData[$groupId] = [
                'label' => __((string)$group->label),
                'active' => false,
                'content' => $fieldset->toHtml()
            ];

            $container->removeField('sirv_group_fieldset_' . $groupId);
        }

        if ($groupId != 'user') {
            $form->removeField('sirv_fieldset_container');

            $currentTabId = $this->getRequest()->getParam('tabId') ?: 'general';
            if (isset($tabsData[$currentTabId])) {
                $tabsData[$currentTabId]['active'] = true;
            } else {
                reset($tabsData);
                $currentTabId = key($tabsData);
                $tabsData[$currentTabId]['active'] = true;
            }

            $form->addType('tabs', '\Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Tabs');
            $tabs = $form->addField('sirv_config_tabs_element', 'tabs', []);
            $tabs->setTabsData($tabsData);

            $form->addField('current-tab-id', 'hidden', [
                'name' => 'current_tab_id',
                'value' => $currentTabId
            ]);
        }

        unset($xml);

        $this->setChild(
            'form_after',
            $this->getLayout()->createBlock(
                \Magento\Framework\View\Element\Template::class
            )->setTemplate('Sirv_Magento2::widget/form/form_after.phtml')
        );

        return parent::_prepareForm();
    }
}
