<?php

namespace MagicToolbox\Sirv\Plugin;

/**
 * Plugin for \Magento\Store\Model\Store
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Store
{
    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Whether auto fetch is disabled
     *
     * @var bool
     */
    protected $isAutoFetchDisabled = true;

    /**
     * Sirv host
     *
     * @var string
     */
    protected $sirvHost = '';

    /**
     * URL prefix
     *
     * @var string
     */
    protected $urlPrefix = '';

    /**
     * Base URL cache
     *
     * @var array
     */
    protected static $baseUrlCache = [];

    /**
     * Constructor
     *
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @return void
     */
    public function __construct(
        \MagicToolbox\Sirv\Helper\Data $dataHelper
    ) {
        $this->dataHelper = $dataHelper;
        if ($dataHelper->isSirvEnabled()) {
            $bucket = $dataHelper->getConfig('bucket') ?: $dataHelper->getConfig('account');
            $this->sirvHost = $bucket . '.sirv.com';
            $cdn = $dataHelper->getConfig('cdn_url');
            $cdn = is_string($cdn) ? trim($cdn) : '';
            if (!empty($cdn)) {
                $this->sirvHost = $cdn;
            }
            $this->urlPrefix = $dataHelper->getConfig('url_prefix');
            $this->urlPrefix = is_string($this->urlPrefix) ? trim($this->urlPrefix) : '';
            if ($this->urlPrefix) {
                //$this->urlPrefix = preg_replace('#^(?:https?\:)?//#', '', $this->urlPrefix);
                $autoFetch = $dataHelper->getConfig('auto_fetch');
                $this->isAutoFetchDisabled = $autoFetch != 'custom' && $autoFetch != 'all';
            }
        }
    }

    /**
     * Retrieve base URL
     *
     * @param \Magento\Store\Model\Store $store
     * @param string $baseUrl
     * @param string $type
     * @param boolean|null $secure
     * @return string
     */
    public function afterGetBaseUrl($store, $baseUrl, $type = \Magento\Framework\UrlInterface::URL_TYPE_LINK, $secure = null)
    {
        if ($this->isAutoFetchDisabled || $type != \Magento\Framework\UrlInterface::URL_TYPE_STATIC) {
            return $baseUrl;
        }

        $cacheKey = $type . '/' . ($secure === null ? 'null' : ($secure ? 'true' : 'false'));
        if (!isset(self::$baseUrlCache[$cacheKey])) {
            $secure = $secure === null ? $store->isCurrentlySecure() : (bool)$secure;
            $sirvUrl = ($secure ? 'https://' : 'http://') . $this->sirvHost . '/';
            //$baseUrl = preg_replace('#^(?:https?\:)?//' . preg_quote($this->urlPrefix, '#') . '#', $sirvUrl, $baseUrl);
            $this->dataHelper->baseStaticUrl($baseUrl);
            $baseUrl = preg_replace('#^(?:https?\:)?//[^/]++/#', $sirvUrl, $baseUrl);
            self::$baseUrlCache[$cacheKey] = $baseUrl;
        }

        return self::$baseUrlCache[$cacheKey];
    }
}
