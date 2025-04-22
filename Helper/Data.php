<?php

namespace Sirv\Magento2\Helper;

/**
 * Data helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**#@+
     * Config scopes
     */
    const SCOPE_STORE = 'store';
    const SCOPE_WEBSITE = 'website';
    const SCOPE_DEFAULT = 'default';
    const SCOPE_BACKEND = 'backend';
    /**#@-*/

    /**
     * Config model factory
     *
     * @var \Sirv\Magento2\Model\ConfigFactory
     */
    protected $configModelFactory = null;

    /**
     * Sirv client factory
     *
     * @var \Sirv\Magento2\Model\Api\SirvFactory
     */
    protected $sirvClientFactory = null;

    /**
     * S3 client factory
     *
     * @var \Sirv\Magento2\Model\Api\S3Factory
     */
    protected $s3ClientFactory = null;

    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Determine if the data has been initialized or not
     *
     * @var bool
     */
    protected static $isInitialized = false;

    /**
     * Backend flag
     *
     * @var bool
     */
    protected static $isBackend = false;

    /**
     * Config scope
     *
     * @var string
     */
    protected static $configScope = self::SCOPE_DEFAULT;

    /**
     * Config scope id
     *
     * @var integer
     */
    protected static $configScopeId = 0;

    /**
     * Website id
     *
     * @var integer
     */
    protected static $websiteId = 0;

    /**
     * Store id
     *
     * @var integer
     */
    protected static $storeId = 0;

    /**
     * Config
     *
     * @var array
     */
    protected static $fullConfig = [];

    /**
     * Config
     *
     * @var array
     */
    protected static $sirvConfig = [];

    /**
     * Option names for default profile only
     *
     * @var array
     */
    protected $defaultProfileOptions = [
        'account_exists' => true,
        'email' => true,
        'password' => true,
        'first_and_last_name' => true,
        'first_name' => true,
        'last_name' => true,
        'alias' => true,
        'connect' => true,
        'register' => true,
        'otp_code' => true,
        'token' => true,
        'token_expire_time' => true,
        'account' => true,
        'client_id' => true,
        'client_secret' => true,
        'key' => true,
        'secret' => true,
        'bucket' => true,
        'cdn_url' => true,
        'auto_fetch' => true,
        'url_prefix' => true,
        'image_folder' => true,
        'sirv_rate_limit_data' => true,
        's3_rate_limit_data' => true,
        'assets_cache' => true,
        'assets_cache_ttl' => true,
        'display_credentials_rejected_message' => true,
    ];

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected static $isSirvEnabled = false;

    /**
     * Whether to use Sirv Media Viewer
     *
     * @var bool
     */
    protected static $useSirvMediaViewer = false;

    /**
     * cURL resource
     *
     * @var resource
     */
    protected static $curlHandle = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\State $appState
     * @param \Sirv\Magento2\Model\ConfigFactory $configModelFactory
     * @param \Sirv\Magento2\Model\Api\SirvFactory $sirvClientFactory
     * @param \Sirv\Magento2\Model\Api\S3Factory $s3ClientFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\State $appState,
        \Sirv\Magento2\Model\ConfigFactory $configModelFactory,
        \Sirv\Magento2\Model\Api\SirvFactory $sirvClientFactory,
        \Sirv\Magento2\Model\Api\S3Factory $s3ClientFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->configModelFactory = $configModelFactory;
        $this->sirvClientFactory = $sirvClientFactory;
        $this->s3ClientFactory = $s3ClientFactory;
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;

        if (static::$isInitialized === false) {
            static::$isBackend = ($appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML);
            $this->initializeData();
        }
    }

    /**
     * Initialize the data
     *
     * @return void
     */
    protected function initializeData()
    {
        static::$isInitialized = true;

        if (static::$isBackend) {
            $storeId = $this->_request->getParam('store', null);
            $websiteId = $this->_request->getParam('website', null);

            if ($storeId) {
                static::$storeId = (int)$storeId;
                $store = $this->storeManager->getStore(static::$storeId);
                static::$websiteId = $store->getWebsiteId();
                static::$configScope = self::SCOPE_STORE;
                static::$configScopeId = static::$storeId;
            } elseif ($websiteId) {
                //static::$storeId = 0;
                static::$websiteId = (int)$websiteId;
                static::$configScope = self::SCOPE_WEBSITE;
                static::$configScopeId = static::$websiteId;
            }
        } else {
            $store = $this->storeManager->getStore();
            static::$storeId = $store->getId();
            static::$websiteId = $store->getWebsiteId();
            static::$configScope = self::SCOPE_STORE;
            static::$configScopeId = static::$storeId;
        }

        $this->loadConfig();
    }

    /**
     * Load config
     *
     * @return void
     */
    protected function loadConfig()
    {
        static::$sirvConfig = [];

        $collection = $this->getConfigModel()->getCollection();

        if (static::$configScope == self::SCOPE_STORE) {
            $scopeFilter = '(`scope` = \'' . self::SCOPE_DEFAULT . '\' AND `scope_id` = 0) OR ' .
                '(`scope` = \'' . self::SCOPE_WEBSITE . '\' AND `scope_id` = ' . static::$websiteId . ') OR ' .
                '(`scope` = \'' . self::SCOPE_STORE . '\' AND `scope_id` = ' . static::$storeId . ')';
        } elseif (static::$configScope == self::SCOPE_WEBSITE) {
            $scopeFilter = '(`scope` = \'' . self::SCOPE_DEFAULT . '\' AND `scope_id` = 0) OR ' .
                '(`scope` = \'' . self::SCOPE_WEBSITE . '\' AND `scope_id` = ' . static::$websiteId . ')';
        } elseif (static::$configScope == self::SCOPE_DEFAULT) {
            $scopeFilter = '(`scope` = \'' . self::SCOPE_DEFAULT . '\' AND `scope_id` = 0)';
        }

        $config = [
            self::SCOPE_DEFAULT => [],
            self::SCOPE_WEBSITE => [],
            self::SCOPE_STORE => []
        ];

        if (static::$isBackend) {
            $scopeFilter .= ' OR (`scope` = \'' . self::SCOPE_BACKEND . '\' AND `scope_id` = 0)';
            $config[self::SCOPE_BACKEND] = [];
        }

        $collection->addFilter('scope_filter', $scopeFilter, 'string');
        $collection->load();

        foreach ($collection->getData() as $data) {
            $config[$data['scope']][$data['name']] = $data['value'];
        }

        static::$sirvConfig = array_merge($config[self::SCOPE_DEFAULT], $config[self::SCOPE_WEBSITE], $config[self::SCOPE_STORE]);
        static::$fullConfig = $config;

        static::$isSirvEnabled = isset(static::$sirvConfig['enabled']) ? static::$sirvConfig['enabled'] == 'true' : false;
        static::$useSirvMediaViewer = isset(static::$sirvConfig['product_gallery_view']) ? static::$sirvConfig['product_gallery_view'] == 'smv' : false;
    }

    /**
     * Get config model
     *
     * @return \Sirv\Magento2\Model\Config
     */
    public function getConfigModel()
    {
        return $this->configModelFactory->create();
    }

    /**
     * Get config
     *
     * @param string $name
     * @return mixed
     */
    public function getConfig($name = null)
    {
        return $name ? (isset(static::$sirvConfig[$name]) ? static::$sirvConfig[$name] : null) : static::$sirvConfig;
    }

    /**
     * Save config
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function saveConfig($name, $value)
    {
        if (isset($this->defaultProfileOptions[$name])) {
            $scope = self::SCOPE_DEFAULT;
            $scopeId = 0;
        } else {
            $scope = static::$configScope;
            $scopeId = static::$configScopeId;
        }

        $collection = $this->getConfigModel()->getCollection();

        $collection->addFieldToFilter('scope', $scope);
        $collection->addFieldToFilter('scope_id', $scopeId);
        $collection->addFieldToFilter('name', $name);

        $model = $collection->getFirstItem();
        $data = $model->getData();

        if (empty($data)) {
            $model->setData('scope', $scope);
            $model->setData('scope_id', $scopeId);
            $model->setData('name', $name);
        }
        $model->setData('value', $value);
        $model->save();
        static::$sirvConfig[$name] = $value;
        static::$fullConfig[$scope][$name] = $value;
    }

    /**
     * Check for backend area
     *
     * @return bool
     */
    public function isBackend()
    {
        return static::$isBackend;
    }

    /**
     * Is Sirv module enabled
     *
     * @return bool
     */
    public function isSirvEnabled()
    {
        static $isEnabled = null;

        if ($isEnabled === null) {
            if (($isEnabled = static::$isSirvEnabled) && !static::$isBackend) {
                $excludedPages = $this->getConfig('excluded_pages') ?: '';
                if (!empty($excludedPages)) {
                    $excludedPages = preg_replace("#\n++#", "\n", $excludedPages);
                    $excludedPages = explode("\n", $excludedPages);
                    foreach ($excludedPages as &$pattern) {
                        $pattern = str_replace(
                            '__ASTERISK__',
                            '.*',
                            preg_quote(
                                str_replace('*', '__ASTERISK__', $pattern),
                                '#'
                            )
                        );
                    }
                    $requestUri = $this->_request->getRequestUri();
                    if (preg_match('#' . implode('|', $excludedPages) . '#', $requestUri)) {
                        $isEnabled = false;
                    }
                }
            }
        }

        return $isEnabled;
    }

    /**
     * Whether to use Sirv Media Viewer
     *
     * @return bool
     */
    public function useSirvMediaViewer()
    {
        return static::$useSirvMediaViewer;
    }

    /**
     * Get Sirv domain
     *
     * @param bool $direct
     * @return string
     */
    public function getSirvDomain($direct = true)
    {
        static $domains = [];

        if (empty($domains)) {
            $alias = $this->getConfig('bucket') ?: $this->getConfig('account');
            $domains[0] = $alias . '.sirv.com';
            $domains[1] = $this->getConfig('cdn_url');
            $domains[1] = is_string($domains[1]) ? trim($domains[1]) : '';
            if (empty($domains[1])) {
                $domains[1] = $domains[0];
            }

            $subAlias = $this->getConfig('sub_alias') ?: '';
            if (!empty($subAlias) && ($subAlias != $alias)) {
                $subAliasDomain = $this->getConfig('sub_alias_domain') ?: '';
                $domains[1] = empty($subAliasDomain) ? $subAlias . '.sirv.com' : $subAliasDomain;
            }
        }

        return $direct ? $domains[0] : $domains[1];
    }

    /**
     * Get Sirv client
     *
     * @return \Sirv\Magento2\Model\Api\Sirv
     */
    public function getSirvClient()
    {
        /** @var \Sirv\Magento2\Model\Api\Sirv $sirvClient */
        static $sirvClient = null;

        if ($sirvClient === null) {
            $sirvClient = $this->sirvClientFactory->create();

            $data = [];

            $data['account'] = $this->getConfig('account');
            $data['account'] = $data['account'] ? $data['account'] : '';

            $data['token'] = $this->getConfig('token');
            $data['token'] = $data['token'] ? $data['token'] : '';
            $data['tokenExpireTime'] = $this->getConfig('token_expire_time');
            $data['tokenExpireTime'] = $data['tokenExpireTime'] ? (int)$data['tokenExpireTime'] : 0;

            $data['cacheTokenCallback'] = [$this, 'doCacheTokenData'];

            $data['clientId'] = $this->getConfig('client_id');
            $data['clientId'] = $data['clientId'] ? $data['clientId'] : '';
            $data['clientSecret'] = $this->getConfig('client_secret');
            $data['clientSecret'] = $data['clientSecret'] ? $data['clientSecret'] : '';

            $data['bucket'] = $this->getConfig('bucket');
            $data['bucket'] = $data['bucket'] ? $data['bucket'] : '';
            $data['key'] = $this->getConfig('key');
            $data['key'] = $data['key'] ? $data['key'] : '';
            $data['secret'] = $this->getConfig('secret');
            $data['secret'] = $data['secret'] ? $data['secret'] : '';

            $rateLimitData = $this->getConfig('sirv_rate_limit_data');
            if ($rateLimitData) {
                $data['rateLimitData'] = $this->getUnserializer()->unserialize($rateLimitData);
            }
            $data['rateLimitExceededCallback'] = [$this, 'onSirvRateLimitExceeded'];

            $data['moduleVersion'] = $this->getModuleVersion('Sirv_Magento2') ?: 'unknown';

            $sirvClient->init($data);
        }

        return $sirvClient;
    }

    /**
     * Get S3 client
     *
     * @return \Sirv\Magento2\Model\Api\S3
     */
    public function getS3Client()
    {
        /** @var \Sirv\Magento2\Model\Api\S3 $s3Client */
        static $s3Client = null;

        if ($s3Client === null) {
            $bucket = $this->getConfig('bucket');
            $key = $this->getConfig('key');
            $secret = $this->getConfig('secret');

            if (!(empty($bucket) || empty($key) || empty($secret))) {
                $data = [
                    'host' => 's3.sirv.com',
                    'bucket' => $bucket,
                    'key' => $key,
                    'secret' => $secret,
                    'rateLimitExceededCallback' => [$this, 'onS3RateLimitExceeded']
                ];

                $rateLimitData = $this->getConfig('s3_rate_limit_data');
                if ($rateLimitData) {
                    $data['rateLimitData'] = $this->getUnserializer()->unserialize($rateLimitData);
                }

                $data['moduleVersion'] = $this->getModuleVersion('Sirv_Magento2') ?: 'unknown';

                $s3Client = $this->s3ClientFactory->create(['params' => $data]);
            }
        }

        return $s3Client;
    }

    /**
     * Caching token data
     *
     * @param  string $token
     * @param  integer $tokenExpireTime
     * @return void
     */
    public function doCacheTokenData($token, $tokenExpireTime)
    {
        $this->saveConfig('token', $token);
        $this->saveConfig('token_expire_time', $tokenExpireTime);
    }

    /**
     * On Sirv API rate limit exceeded
     *
     * @param  array $rateLimitData
     * @return void
     */
    public function onSirvRateLimitExceeded($rateLimitData)
    {
        $this->saveConfig('sirv_rate_limit_data', $this->getSerializer()->serialize($rateLimitData));
    }

    /**
     * On S3 API rate limit exceeded
     *
     * @param  array $rateLimitData
     * @return void
     */
    public function onS3RateLimitExceeded($rateLimitData)
    {
        $this->saveConfig('s3_rate_limit_data', $this->getSerializer()->serialize($rateLimitData));
    }

    /**
     * Get store manager
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * Get app cache
     *
     * @return \Magento\Framework\App\CacheInterface
     */
    public function getAppCache()
    {
        /** @var \Magento\Framework\App\CacheInterface $cache */
        static $cache = null;

        if ($cache === null) {
            $cache = $this->objectManager->get(\Magento\Framework\App\CacheInterface::class);
        }

        return $cache;
    }

    /**
     * Get unserializer
     *
     * @return \Magento\Framework\Unserialize\Unserialize
     */
    public function getUnserializer()
    {
        static $unserializer = null;

        if ($unserializer === null) {
            $unserializer = $this->objectManager->get(\Magento\Framework\Unserialize\Unserialize::class);
        }

        return $unserializer;
    }

    /**
     * Get serializer
     *
     * @return \Magento\Framework\Serialize\Serializer\Serialize|\Zend\Serializer\Adapter\PhpSerialize
     */
    public function getSerializer()
    {
        static $serializer = null;

        if ($serializer === null) {
            if (class_exists('\Magento\Framework\Serialize\Serializer\Serialize', false)) {
                //NOTE: Magento v2.2.x and v2.3.x
                $serializer = $this->objectManager->get(\Magento\Framework\Serialize\Serializer\Serialize::class);
            } else {
                //NOTE: Magento v2.1.x
                $serializer = $this->objectManager->get(\Zend\Serializer\Adapter\PhpSerialize::class);
            }
        }

        return $serializer;
    }

    /**
     * Get module version
     *
     * @param string $name
     * @return string | bool
     */
    public function getModuleVersion($name)
    {
        static $versions = [];

        if (!isset($versions[$name])) {
            $versions[$name] = false;
            $componentRegistrar = $this->objectManager->get(\Magento\Framework\Component\ComponentRegistrar::class);
            $moduleDir = $componentRegistrar->getPath(
                \Magento\Framework\Component\ComponentRegistrar::MODULE,
                $name
            );
            $moduleInfo = json_decode(file_get_contents($moduleDir . '/composer.json'));
            if (is_object($moduleInfo) && isset($moduleInfo->version)) {
                $versions[$name] = $moduleInfo->version;
            }
        }

        return $versions[$name];
    }

    /**
     * Get the path to the product assets folder
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function getProductAssetsFolderPath($product = null)
    {
        static $folderPattern = null, $paths = [];

        if ($folderPattern === null) {
            $folderPattern = $this->getConfig('product_assets_folder') ?: '';
            $folderPattern = trim(trim($folderPattern), '/');

            //NOTE: product assets folder must contain a unique pattern
            if (!preg_match('#{product-(?:sku|id)}#', $folderPattern)) {
                $folderPattern = $folderPattern . '/{product-sku}';
            }
        }

        if ($product === null) {
            return $folderPattern;
        }

        $productId = $product->getId();

        if (isset($paths[$productId])) {
            return $paths[$productId];
        }

        $productSku = $product->getSku();
        $folderPath = str_replace(
            ['{product-id}', '{product-sku}', '{product-sku-2-char}', '{product-sku-3-char}'],
            [$productId, $productSku, substr($productSku, 0, 2), substr($productSku, 0, 3)],
            $folderPattern
        );

        $matches = [];
        if (preg_match_all('#{attribute:(admin:)?([a-zA-Z0-9_]++)}#', $folderPath, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrValue = $product->getData($match[2]);
                if (is_string($attrValue)) {
                    $attrValue = trim($attrValue);
                    if (empty($attrValue)) {
                        $attrValue = false;
                    } else {
                        if (empty($match[1])) {
                            $attrTextValue = $product->getAttributeText($match[2]);
                        } else {
                            $pAttr = $product->getResource()->getAttribute($match[2]);
                            $storeId = $pAttr->getStoreId();
                            $attrTextValue = $pAttr->setStoreId(0)->getSource()->getOptionText($attrValue);
                            $pAttr->setStoreId($storeId);
                        }
                        if (is_string($attrTextValue)) {
                            $attrTextValue = trim($attrTextValue);
                            if (!empty($attrTextValue)) {
                                $attrValue = $attrTextValue;
                            }
                        }
                    }
                } else {
                    $attrValue = false;
                }

                if ($attrValue) {
                    $folderPath = str_replace(
                        '{attribute:' . $match[1] . $match[2] . '}',
                        $attrValue,
                        $folderPath
                    );
                } else {
                    $pattern = '{attribute:' . $match[1] . $match[2] . '}';
                    $folderPath = preg_replace(
                        [
                            '#/' . $pattern . '/#',
                            '#^' . $pattern . '/|/' . $pattern . '$|' . $pattern . '#'
                        ],
                        [
                            '/',
                            ''
                        ],
                        $folderPath
                    );
                }
            }
        }

        $paths[$productId] = $folderPath;

        return $paths[$productId];
    }

    /**
     * Get assets data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getAssetsData($product)
    {
        static $productAssetsFolder = null, $assetsModelFactory = null, $assetsData = [];

        if ($productAssetsFolder === null) {
            $productAssetsFolder = $this->getConfig('product_assets_folder') ?: '';
            $productAssetsFolder = trim(trim($productAssetsFolder), '/');
        }

        if (empty($productAssetsFolder)) {
            return [];
        }

        $productId = $product->getId();

        if (isset($assetsData[$productId])) {
            return $assetsData[$productId];
        }

        //NOTE: product assets folder must contain a unique pattern
        if (!preg_match('#{product-(?:sku|id)}#', $productAssetsFolder)) {
            $productAssetsFolder = $productAssetsFolder . '/{product-sku}';
        }

        if ($assetsModelFactory === null) {
            $assetsModelFactory = $this->objectManager->get(\Sirv\Magento2\Model\AssetsFactory::class);
        }

        $assetsModel = $assetsModelFactory->create();
        $assetsModel->load($productId, 'product_id');
        $contents = $assetsModel->getData('contents');
        if ($contents !== null) {
            $contents = json_decode($contents, true);
            if (!isset($contents['featured_image'])) {
                //NOTE: update data to get featured image
                $contents = null;
            }
        }

        if ($contents === null) {
            $productSku = $product->getSku();
            $assetsFolder = str_replace(
                ['{product-id}', '{product-sku}', '{product-sku-2-char}', '{product-sku-3-char}'],
                [$productId, $productSku, substr($productSku, 0, 2), substr($productSku, 0, 3)],
                $productAssetsFolder
            );
            $matches = [];
            if (preg_match_all('#{attribute:(admin:)?([a-zA-Z0-9_]++)}#', $assetsFolder, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $attrValue = $product->getData($match[2]);
                    if (is_string($attrValue)) {
                        $attrValue = trim($attrValue);
                        if (empty($attrValue)) {
                            $attrValue = false;
                        } else {
                            if (empty($match[1])) {
                                $attrTextValue = $product->getAttributeText($match[2]);
                            } else {
                                $pAttr = $product->getResource()->getAttribute($match[2]);
                                $storeId = $pAttr->getStoreId();
                                $attrTextValue = $pAttr->setStoreId(0)->getSource()->getOptionText($attrValue);
                                $pAttr->setStoreId($storeId);
                            }
                            if (is_string($attrTextValue)) {
                                $attrTextValue = trim($attrTextValue);
                                if (!empty($attrTextValue)) {
                                    $attrValue = $attrTextValue;
                                }
                            }
                        }
                    } else {
                        $attrValue = false;
                    }

                    if ($attrValue) {
                        $assetsFolder = str_replace('{attribute:' . $match[1] . $match[2] . '}', $attrValue, $assetsFolder);
                    } else {
                        $pattern = '{attribute:' . $match[1] . $match[2] . '}';
                        $assetsFolder = preg_replace(
                            [
                                '#/' . $pattern . '/#',
                                '#^' . $pattern . '/|/' . $pattern . '$|' . $pattern . '#'
                            ],
                            [
                                '/',
                                ''
                            ],
                            $assetsFolder
                        );
                    }
                }
            }

            $contents = [];
            $assetsInfo = $this->downloadAssetsInfo(
                'https://' . $this->getSirvDomain() . '/' . $assetsFolder . '.view?info'
            );
            $assetsInfo = json_decode($assetsInfo, true);
            if (is_array($assetsInfo)) {
                $contents = $this->prepareAssetsInfo($assetsInfo);
            }
            $assetsModel->setData('product_id', $productId);
            $assetsModel->setData('contents', json_encode($contents));
            $assetsModel->setData('timestamp', time());
            $assetsModel->save();
        }

        $assetsData[$productId] = $contents;
        $assetsData[$productId]['timestamp'] = $assetsModel->getData('timestamp');

        return $assetsData[$productId];
    }

    /**
     * Prepare assets info
     *
     * @param array $assetsInfo
     * @return array
     */
    public function prepareAssetsInfo($assetsInfo)
    {
        $contents = [];
        $modified = $assetsInfo['modified'] ?? '';
        $contents['modified'] = empty($modified) ? false : strtotime($modified);
        $contents['dirname'] = $assetsInfo['dirname'] ?? '';
        $contents['assets'] = (isset($assetsInfo['assets']) && is_array($assetsInfo['assets'])) ? $assetsInfo['assets'] : [];
        $contents['curl'] = $assetsInfo['curl'] ?? [];

        $assetsFolder = ltrim($contents['dirname'], '/');
        $folderUrl = 'https://' . $this->getSirvDomain() . '/' . $assetsFolder;
        $f = [];
        foreach ($contents['assets'] as &$asset) {
            switch ($asset['type']) {
                case 'image':
                    isset($f['image']) || ($f['image'] = $assetsFolder . '/' . $asset['name']);
                    break;
                case 'spin':
                    if (!(isset($f['spin']) && isset($asset['width']) && isset($asset['height']))) {
                        $spinInfoUrl = $folderUrl . '/' . $asset['name'] . '?info';
                        $spinInfo = $this->downloadAssetsInfo($spinInfoUrl);
                        $spinInfo = json_decode($spinInfo, true);
                        $layer = is_array($spinInfo) && isset($spinInfo['layers']) && is_array($spinInfo['layers']) ? reset($spinInfo['layers']) : false;
                        $slide = is_array($layer) ? reset($layer) : false;
                        if ($slide) {
                            isset($f['spin']) || ($f['spin'] = preg_replace('#/[^/]++$#', '/', $assetsFolder . '/' . $asset['name']) . $slide);
                            if (!(isset($asset['width']) && isset($asset['height']))) {
                                $slideUrl = preg_replace('#/[^/]++$#', '/', $folderUrl . '/' . $asset['name']) . $slide;
                                $slideInfo = $this->downloadAssetsInfo($slideUrl . '?info');
                                $slideInfo = json_decode($slideInfo);
                                $responseCode = (isset($slideInfo->curl) && isset($slideInfo->curl->code)) ? (int)$slideInfo->curl->code : 0;
                                if (in_array($responseCode, [404, 403])) {
                                    unset($f['spin']);
                                    break;
                                }
                                isset($asset['width']) || $asset['width'] = $slideInfo->width ?? 0;
                                isset($asset['height']) || $asset['height'] = $slideInfo->height ?? 0;
                            }
                            break;
                        }
                    }
                    break;
                case 'video':
                    isset($f['video']) || ($f['video'] = $assetsFolder . '/' . $asset['name'] . '?thumbnail=' . $asset['width']/*height*/);
                    break;
            }
        }
        $contents['featured_image'] = isset($f['image']) ? $f['image'] : (isset($f['spin']) ? $f['spin'] : (isset($f['video']) ? $f['video'] : ''));

        return $contents;
    }

    /**
     * Download assets info
     *
     * @param string $url
     * @return string
     */
    public function downloadAssetsInfo($url)
    {
        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

        $url = str_replace(' ', '%20', $url);

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]
        );

        $contents = curl_exec(self::$curlHandle);
        $error = curl_errno(self::$curlHandle);
        $code = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);

        if ($error || $code != 200) {
            $contents = [
                'curl' => [
                    'code' => $code,
                    'error' => $error
                ]
            ];
            $contents = json_encode($contents);
        }

        return $contents;
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if (isset(self::$curlHandle)) {
            curl_close(self::$curlHandle);
            self::$curlHandle = null;
        }
        if (method_exists(get_parent_class(__CLASS__), '__destruct')) {
            parent::__destruct();
        }
    }
}
