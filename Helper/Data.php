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
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\State $appState
     * @param \MagicToolbox\Sirv\Model\ConfigFactory $configModelFactory
     * @param \MagicToolbox\Sirv\Model\Api\SirvFactory $sirvClientFactory
     * @param \MagicToolbox\Sirv\Model\Api\S3Factory $s3ClientFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\State $appState,
        \MagicToolbox\Sirv\Model\ConfigFactory $configModelFactory,
        \MagicToolbox\Sirv\Model\Api\SirvFactory $sirvClientFactory,
        \MagicToolbox\Sirv\Model\Api\S3Factory $s3ClientFactory
    ) {
        parent::__construct($context);
        $this->isBackend = ($appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML);
        $this->configModelFactory = $configModelFactory;
        $this->sirvClientFactory = $sirvClientFactory;
        $this->s3ClientFactory = $s3ClientFactory;
        $this->loadConfig();
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
     * Delete config
     *
     * @param string $name
     * @return void
     */
    public function deleteConfig($name)
    {
        $model = $this->getConfigModel();
        $model->load($name, 'name');
        $id = $model->getId();
        if ($id !== null) {
            $model->delete();
        }
        if (isset($this->sirvConfig[$name])) {
            unset($this->sirvConfig[$name]);
        }
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
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $cache = $objectManager->get(\Magento\Framework\App\CacheInterface::class);
        }

        return $cache;
    }

    /**
     * Get list of user accounts
     *
     * @param bool $force
     * @return array
     */
    public function getSirvUsersList($force = false)
    {
        static $users = null;

        if ($users === null || $force) {
            $email = $this->getConfig('email') ?: '';
            $password = $this->getConfig('password') ?: '';
            $cacheId = 'sirv_accounts_' . hash('md5', $email . $password);
            $cache = $this->getAppCache();

            $data = $force ? false : $cache->load($cacheId);
            if (false !== $data) {
                $users = $this->getUnserializer()->unserialize($data);
            }

            if (!is_array($users)) {
                $users = $this->getSirvClient()->getUsersList();
                natsort($users);
                $cache->save($this->getSerializer()->serialize($users), $cacheId, [], 600);
            }
        }

        return $users;
    }

    /**
     * Get list of account profiles
     *
     * @return array
     */
    public function getProfiles()
    {
        static $profiles = null;

        if ($profiles === null) {
            $profiles = $this->getSirvClient()->getProfiles();
            if (!is_array($profiles)) {
                $profiles = [];
            }
        }

        return $profiles;
    }

    /**
     * Get Sirv account stats
     *
     * @param bool $force
     * @return array|bool
     */
    public function getSirvAccountStats($force = false)
    {
        static $data = null;

        if ($data === null) {
            $account = $this->getConfig('account');
            $cacheId = 'sirv_account_stats_' . hash('md5', $account);
            $cache = $this->getAppCache();

            $data = $force ? false : $cache->load($cacheId);
            if (false !== $data) {
                $data = $this->getUnserializer()->unserialize($data);
            }

            if (!is_array($data)) {
                $data = $this->getSirvClient()->getStats();
                $data = is_array($data) ? $data : [];
                //NOTE: 900 - cache lifetime (in seconds)
                $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 900);
            }
        }

        return $data;
    }

    /**
     * Disable spin scanning for image folder
     *
     * @param string $imageFolder
     * @return void
     */
    public function disableSpinScanning($imageFolder)
    {
        if (empty($imageFolder)) {
            $imageFolder = 'catalog';
        }

        $imageFolder = '/' . ltrim($imageFolder, '/');

        $disableSpinScanning = false;

        /** @var \MagicToolbox\Sirv\Model\Api\Sirv $apiClient */
        $apiClient = $this->getSirvClient();

        //NOTE: make sure that folder exists and spin scanning is enabled
        $options = $apiClient->getFolderOptions($imageFolder);

        if ($options) {
            $disableSpinScanning = (!isset($options->scanSpins) || $options->scanSpins) ? true : false;
        } else {
            $disableSpinScanning = true;
            $apiClient->uploadFile($imageFolder . '/sirv_tmp.txt', '', "\n");
            $apiClient->deleteFile($imageFolder . '/sirv_tmp.txt');
        }

        if ($disableSpinScanning) {
            $apiClient->setFolderOptions($imageFolder, ['scanSpins' => false]);
        }
    }

    /**
     * Get CDN config
     *
     * @return array
     */
    public function getCdnConfig()
    {
        static $config = null;

        if ($config === null) {
            $account = $this->getConfig('account');
            $cacheId = 'sirv_account_cdn_' . hash('md5', $account);
            $cache = $this->getAppCache();

            $data = $cache->load($cacheId);
            if (false !== $data) {
                $data = $this->getUnserializer()->unserialize($data);
            }

            if (!is_array($data)) {
                /** @var \MagicToolbox\Sirv\Model\Api\Sirv $apiClient */
                $apiClient = $this->getSirvClient();
                $accountInfo = $apiClient->getAccountInfo();
                $alias = '';
                $cdnEnabled = false;
                $cdnURL = '';
                if ($accountInfo) {
                    $alias = $accountInfo->alias;
                    $cdnURL = isset($accountInfo->cdnURL) ? $accountInfo->cdnURL : '';
                    if (isset($accountInfo->aliases->{$alias}) && isset($accountInfo->aliases->{$alias}->cdn)) {
                        $cdnEnabled = $accountInfo->aliases->{$alias}->cdn;
                    }
                }
                $data = [
                    'alias' => $alias,
                    'cdn_enabled' => $cdnEnabled,
                    'cdn_url' => $cdnURL,
                ];
                $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 60);
            }

            $config = $data;
        }

        return $config;
    }

    /**
     * Sync CDN config
     *
     * @return string
     */
    public function syncCdnConfig()
    {
        $config = $this->getCdnConfig();
        $network = $config['cdn_enabled'] ? 'cdn' : 'direct';
        $this->saveConfig('network', $network);
        $this->saveConfig('cdn_url', $config['cdn_url']);

        return $network;
    }

    /**
     * Turn on/off CDN
     *
     * @param bool $useCDN
     * @return void
     */
    public function switchNetwork($useCDN = true)
    {
        $config = $this->getCdnConfig();

        if ($useCDN != $config['cdn_enabled']) {
            /** @var \MagicToolbox\Sirv\Model\Api\Sirv $apiClient */
            $apiClient = $this->getSirvClient();

            $updated = $apiClient->configCDN($useCDN, $config['alias']);
            if ($updated) {
                $account = $this->getConfig('account');
                $cacheId = 'sirv_account_cdn_' . hash('md5', $account);
                $cache = $this->getAppCache();
                $config['cdn_enabled'] = $useCDN;
                $cache->save($this->getSerializer()->serialize($config), $cacheId, [], 600);
            }
        }
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
            $unserializer = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Unserialize\Unserialize::class
            );
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
                $serializer = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    \Magento\Framework\Serialize\Serializer\Serialize::class
                );
            } else {
                //NOTE: Magento v2.1.x
                $serializer = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    \Zend\Serializer\Adapter\PhpSerialize::class
                );
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
            $componentRegistrar = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Component\ComponentRegistrar::class
            );
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
