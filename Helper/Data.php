<?php

namespace MagicToolbox\Sirv\Helper;

/**
 * Data helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Backend flag
     *
     * @var bool
     */
    protected $isBackend = false;

    /**
     * Config model factory
     *
     * @var \MagicToolbox\Sirv\Model\ConfigFactory
     */
    protected $configModelFactory = null;

    /**
     * Config
     *
     * @var array
     */
    protected $sirvConfig = [];

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Base static URL
     *
     * @var string
     */
    protected $baseStaticUrl = '';

    /**
     * Whether to use Sirv Media Viewer
     *
     * @var bool
     */
    protected $useSirvMediaViewer = false;

    /**
     * Sirv client factory
     *
     * @var \MagicToolbox\Sirv\Model\Api\SirvFactory
     */
    protected $sirvClientFactory = null;

    /**
     * S3 client factory
     *
     * @var \MagicToolbox\Sirv\Model\Api\S3Factory
     */
    protected $s3ClientFactory = null;

    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\State $appState
     * @param \MagicToolbox\Sirv\Model\ConfigFactory $configModelFactory
     * @param \MagicToolbox\Sirv\Model\Api\SirvFactory $sirvClientFactory
     * @param \MagicToolbox\Sirv\Model\Api\S3Factory $s3ClientFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\State $appState,
        \MagicToolbox\Sirv\Model\ConfigFactory $configModelFactory,
        \MagicToolbox\Sirv\Model\Api\SirvFactory $sirvClientFactory,
        \MagicToolbox\Sirv\Model\Api\S3Factory $s3ClientFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        parent::__construct($context);
        $this->isBackend = ($appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML);
        $this->configModelFactory = $configModelFactory;
        $this->sirvClientFactory = $sirvClientFactory;
        $this->s3ClientFactory = $s3ClientFactory;
        $this->loadConfig();
        $this->objectManager = $objectManager;
    }

    /**
     * Load config
     *
     * @return void
     */
    public function loadConfig()
    {
        $this->sirvConfig = [];
        $collection = $this->getConfigModel()->getCollection();
        foreach ($collection->getData() as $data) {
            $this->sirvConfig[$data['name']] = $data['value'];
        }
        $this->isSirvEnabled = isset($this->sirvConfig['enabled']) ? $this->sirvConfig['enabled'] == 'true' : false;
        $this->useSirvMediaViewer = isset($this->sirvConfig['product_gallery_view']) ? $this->sirvConfig['product_gallery_view'] == 'smv' : false;
    }

    /**
     * Get config model
     *
     * @return \MagicToolbox\Sirv\Model\Config
     */
    protected function getConfigModel()
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
        return $name ? (isset($this->sirvConfig[$name]) ? $this->sirvConfig[$name] : null) : $this->sirvConfig;
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
        $model = $this->getConfigModel();
        $model->load($name, 'name');
        $data = $model->getData();
        if (empty($data)) {
            $model->setData('name', $name);
        }
        $model->setData('value', $value);
        $model->save();
        $this->sirvConfig[$name] = $value;
    }

    /**
     * Check for backend area
     *
     * @return bool
     */
    public function isBackend()
    {
        return $this->isBackend;
    }

    /**
     * Is Sirv module enabled in config
     *
     * @return bool
     */
    public function isSirvEnabled()
    {
        return $this->isSirvEnabled;
    }

    /**
     * Whether to use Sirv Media Viewer
     *
     * @return bool
     */
    public function useSirvMediaViewer()
    {
        return $this->useSirvMediaViewer;
    }

    /**
     * Get/set base static URL
     *
     * @param string $url
     * @return string
     */
    public function baseStaticUrl($url = null)
    {
        if (null !== $url) {
            $this->baseStaticUrl = $url;
        }

        return $this->baseStaticUrl;
    }

    /**
     * Get Sirv client
     *
     * @return \MagicToolbox\Sirv\Model\Api\Sirv
     */
    public function getSirvClient()
    {
        /** @var \MagicToolbox\Sirv\Model\Api\Sirv $sirvClient */
        static $sirvClient = null;

        if ($sirvClient === null) {
            $sirvClient = $this->sirvClientFactory->create();

            $data = [];

            $data['email'] = $this->getConfig('email');
            $data['email'] = $data['email'] ? $data['email'] : '';
            $data['password'] = $this->getConfig('password');
            $data['password'] = $data['password'] ? $data['password'] : '';
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

            $data['moduleVersion'] = $this->getModuleVersion('MagicToolbox_Sirv') ?: 'unknown';

            $sirvClient->init($data);
        }

        return $sirvClient;
    }

    /**
     * Get S3 client
     *
     * @return \MagicToolbox\Sirv\Model\Api\S3
     */
    public function getS3Client()
    {
        /** @var \MagicToolbox\Sirv\Model\Api\S3 $s3Client */
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

                $data['moduleVersion'] = $this->getModuleVersion('MagicToolbox_Sirv') ?: 'unknown';

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
}
