<?php

namespace Sirv\Magento2\Model\System\Message;

/**
 * Notification about JavaScript settings
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class JavaScriptSettings implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * Authorization component
     *
     * @var \Magento\Framework\AuthorizationInterface
     */
    protected $authorization;

    /**
     * URL builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Scope config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Sirv\Magento2\Helper\Data\Backend $dataHelper
     * @return void
     */
    public function __construct(
        \Magento\Framework\AuthorizationInterface $authorization,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Sirv\Magento2\Helper\Data\Backend $dataHelper
    ) {
        $this->authorization = $authorization;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Retrieve unique message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        $optionsData = $this->getOptionsData();
        return hash('md5', 'SIRV_JAVASCRIPT_SETTINGS_NOTIFICATION' . implode('', $optionsData));
    }

    /**
     * Check whether to display
     *
     * @return bool
     */
    public function isDisplayed()
    {
        static $isDisplayed = null;

        if ($isDisplayed === null) {
            $optionsData = $this->getOptionsData();
            $isDisplayed = $this->authorization->isAllowed('Sirv_Magento2::sirv_settings_edit') && !empty($optionsData);
        }

        return $isDisplayed;
    }

    /**
     * Retrieve message text
     *
     * @return string
     */
    public function getText()
    {
        $optionsData = $this->getOptionsData();
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'dev']);
        $url .= '#dev_js-head';

        $ending = '';
        $article = 'this';
        if (count($optionsData) > 1) {
            $ending = 's';
            $article = 'those';
        }

        $message = __(
            'We noticed that you are not using %1.<br/>When you switch to production mode, we recommend <a href="%3">enabling %4 option%2</a> for better performance.',
            implode(' and ', $optionsData),
            $ending,
            $url,
            $article
        );

        return $message;
    }

    /**
     * Retrieve message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return \Magento\Framework\Notification\MessageInterface::SEVERITY_NOTICE;
    }

    /**
     * Get options data
     *
     * @return array
     */
    public function getOptionsData()
    {
        static $data = null;

        if ($data === null) {
            $data = [];
            $scope = $this->dataHelper->getConfigScope();
            $scopeCode = $this->dataHelper->getConfigScopeId();

            if (!$this->scopeConfig->isSetFlag(
                \Magento\Framework\View\Asset\Config::XML_PATH_MERGE_JS_FILES,
                $scope,
                $scopeCode
            )) {
                $data['merge_files'] = '<b>Merge JavaScript Files</b>';
            }
            if (!$this->scopeConfig->isSetFlag(
                \Magento\Framework\View\Asset\Config::XML_PATH_JS_BUNDLING,
                $scope,
                $scopeCode
            )) {
                $data['enable_js_bundling'] = '<b>Enable JavaScript Bundling</b>';
            }
        }

        return $data;
    }
}
