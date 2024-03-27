<?php

namespace Sirv\Magento2\Helper\Data;

/**
 * Backend helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Backend extends \Sirv\Magento2\Helper\Data
{
    /**
     * Get config scope
     *
     * @return string
     */
    public function getConfigScope()
    {
        return static::$configScope;
    }

    /**
     * Get config scope id
     *
     * @return integer
     */
    public function getConfigScopeId()
    {
        return static::$configScopeId;
    }

    /**
     * Get config parent scope
     *
     * @return string|false
     */
    public function getParentConfigScope()
    {
        return static::$configScope == self::SCOPE_STORE ? self::SCOPE_WEBSITE : (static::$configScope == self::SCOPE_WEBSITE ? self::SCOPE_DEFAULT : false);
    }

    /**
     * Get default profile option names
     *
     * @return array
     */
    public function getDefaultProfileOptions()
    {
        return $this->defaultProfileOptions;
    }

    /**
     * Get config
     *
     * @param string $name
     * @param string $scope
     * @return mixed
     */
    public function getConfig($name = null, $scope = null)
    {
        if ($scope === null) {
            $config =& static::$sirvConfig;
        } elseif (isset(static::$fullConfig[$scope])) {
            $config =& static::$fullConfig[$scope];
        } else {
            $config = [];
        }

        return $name ? (isset($config[$name]) ? $config[$name] : null) : $config;
    }

    /**
     * Delete config
     *
     * @param string $name
     * @return void
     */
    public function deleteConfig($name)
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
        $id = $model->getId();
        if ($id !== null) {
            $model->delete();
        }

        if (isset(static::$fullConfig[$scope][$name])) {
            unset(static::$fullConfig[$scope][$name]);
            if (isset(static::$sirvConfig[$name])) {
                unset(static::$sirvConfig[$name]);
                /*if (isset(static::$fullConfig[self::SCOPE_STORE][$name])) {
                    static::$sirvConfig[$name] = static::$fullConfig[self::SCOPE_STORE][$name];
                } else */
                if (isset(static::$fullConfig[self::SCOPE_WEBSITE][$name])) {
                    static::$sirvConfig[$name] = static::$fullConfig[self::SCOPE_WEBSITE][$name];
                } elseif (isset(static::$fullConfig[self::SCOPE_DEFAULT][$name])) {
                    static::$sirvConfig[$name] = static::$fullConfig[self::SCOPE_DEFAULT][$name];
                }
            }
        }
    }

    /**
     * Save backend config
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function saveBackendConfig($name, $value)
    {
        $scope = self::SCOPE_BACKEND;
        $scopeId = 0;
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
     * Get Magento Catalog Images Cache data
     *
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getMagentoCatalogImagesCacheData()
    {
        static $data = null;

        if ($data === null) {
            $data = ['count' => 0];

            /** @var \Magento\Framework\Filesystem $filesystem */
            $filesystem = $this->objectManager->get(\Magento\Framework\Filesystem::class);
            /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDirectory */
            $mediaDirectory = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $mediaDirAbsPath = $mediaDirectory->getAbsolutePath();
            $cacheDirAbsPath = rtrim($mediaDirAbsPath, '\\/') . '/catalog/product/cache';

            if (is_dir($cacheDirAbsPath)) {
                /** @var \Magento\Framework\Shell $shell */
                $shell = $this->objectManager->get(\Magento\Framework\Shell::class);
                $command = 'find ' . $cacheDirAbsPath . ' -type f | wc -l';
                try {
                    $output = $shell->execute($command);
                    $data['count'] = $output;
                } catch (\Exception $e) {
                    $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
                    try {
                        $iterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($cacheDirAbsPath, $flags),
                            \RecursiveIteratorIterator::CHILD_FIRST
                        );
                        $count = 0;
                        foreach ($iterator as $item) {
                            if ($item->isFile()) {
                                $count++;
                            }
                        }
                        $data['count'] = $count;
                    } catch (\Exception $e) {
                        throw new \Magento\Framework\Exception\FileSystemException(
                            new \Magento\Framework\Phrase($e->getMessage()),
                            $e
                        );
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get Magento media storage info
     *
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getMediaStorageInfo()
    {
        static $data = null;
        if ($data !== null) {
            return $data;
        }

        $cachedData = $this->getConfig('sirv_media_storage_info', self::SCOPE_BACKEND);
        if ($cachedData) {
            $cachedData = $this->getUnserializer()->unserialize($cachedData);
        }

        $data = is_array($cachedData) ? $cachedData : ['count' => 0, 'size' => 0, 'timestamp' => 0];

        return $data;
    }

    /**
     * Get Sirv client
     *
     * @return \Sirv\Magento2\Model\Api\Sirv
     */
    public function getSirvClient()
    {
        /** @var \Sirv\Magento2\Model\Api\Sirv $client */
        static $client = null;

        if ($client === null) {
            $client = parent::getSirvClient();

            $data = [];
            $data['email'] = $this->getConfig('email');
            $data['email'] = $data['email'] ? $data['email'] : '';
            $data['password'] = $this->getConfig('password');
            $data['password'] = $data['password'] ? $data['password'] : '';
            $data['otpToken'] = $this->getConfig('otp_code');
            $data['otpToken'] = $data['otpToken'] ? $data['otpToken'] : '';

            $cache = $this->getAppCache();
            $cacheId = 'sirv_accounts_data_' . hash('md5', $data['email']);
            $accounts = $cache->load($cacheId);
            if (false !== $accounts) {
                $accounts = $this->getUnserializer()->unserialize($accounts);
                if (is_array($accounts) && !empty($accounts)) {
                    $data['accounts'] = $accounts;
                }
            }

            $client->init($data);
        }

        return $client;
    }

    /**
     * Get accounts with roles
     *
     * @param bool $force
     * @return array
     */
    public function getSirvAccounts($force = false)
    {
        static $accounts = null;

        if ($accounts === null || $force) {
            $email = $this->getConfig('email') ?: '';
            $cacheId = 'sirv_accounts_data_' . hash('md5', $email);
            $cache = $this->getAppCache();

            $data = $force ? false : $cache->load($cacheId);
            if (false !== $data) {
                $data = $this->getUnserializer()->unserialize($data);
            }

            if (!is_array($data)) {
                $apiClient = $this->getSirvClient();
                $data = $apiClient->getUsersAccounts();
                $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 3600);
            }

            $accounts = [];
            if (is_array($data)) {
                foreach ($data as $alias => $aData) {
                    $accounts[$alias] = $aData['role'];
                }
            }
        }

        return $accounts;
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
     * Get list of Sirv folders
     *
     * @param string $path
     * @return array
     */
    public function getSirvDirList($path)
    {
        static $list = [];

        if (!isset($list[$path])) {
            $list[$path] = [];
            $contents = $this->getSirvClient()->getFolderContents($path);
            foreach ($contents as $item) {
                if ($item->isDirectory) {
                    $list[$path][] = $item->filename;
                }
            }
        }

        return $list[$path];
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

        /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
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
     * Create folder
     *
     * @param string $folderPath
     * @return void
     */
    public function createFolder($folderPath)
    {
        if (empty($folderPath)) {
            return;
        }

        $folderPath = '/' . ltrim($folderPath, '/');

        /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
        $apiClient = $this->getSirvClient();
        $apiClient->uploadFile($folderPath . '/sirv_tmp.txt', '', "\n");
        $apiClient->deleteFile($folderPath . '/sirv_tmp.txt');
    }

    /**
     * Get account identifier (hash)
     *
     * @return string
     */
    protected function getAccountId()
    {
        static $hash = null;

        if ($hash === null) {
            $email = $this->getConfig('email') ?: '';
            $account = $this->getConfig('account') ?: '';
            $hash = hash('md5', $email . $account);
        }

        return $hash;
    }

    /**
     * Get account config
     *
     * @param bool $force
     * @return array
     */
    public function getAccountConfig($force = false)
    {
        static $config = null;

        if ($config === null || $force) {
            $cacheId = 'sirv_account_info_' . $this->getAccountId();
            $cache = $this->getAppCache();

            $data = $force ? false : $cache->load($cacheId);
            if (false !== $data) {
                $data = $this->getUnserializer()->unserialize($data);
            }

            if (!is_array($data)) {
                /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
                $apiClient = $this->getSirvClient();

                $data = [];
                $info = $apiClient->getAccountInfo();
                if ($info) {
                    $data['alias'] = $alias = isset($info->alias) ? $info->alias : '';
                    $data['cdn_url'] = isset($info->cdnURL) ? $info->cdnURL : '';
                    $data['aliases'] = [];
                    foreach ($info->aliases as $_alias => $_data) {
                        $data['aliases'][$_alias] = isset($_data->customDomain) ? $_data->customDomain : $_alias . '.sirv.com';
                    }

                    if (isset($info->aliases->{$alias})) {
                        if (isset($info->aliases->{$alias}->customDomain)) {
                            $data['cdn_url'] = $info->aliases->{$alias}->customDomain;
                        }
                    }
                    $data['fetching_enabled'] = false;
                    $data['fetching_url'] = '';
                    if (isset($info->fetching)) {
                        $data['fetching_enabled'] = isset($info->fetching->enabled) ? $info->fetching->enabled : false;
                        $data['fetching_url'] = isset($info->fetching->http, $info->fetching->http->url) ? $info->fetching->http->url : '';
                        if ($data['fetching_url']) {
                            $data['fetching_url'] = rtrim($data['fetching_url'], '/') . '/';
                        }
                    }
                    $data['minify'] = false;
                    if (isset($info->minify)) {
                        $data['minify'] = isset($info->minify->enabled) ? $info->minify->enabled : false;
                    }
                    $data['date_created'] = isset($info->dateCreated) ? $info->dateCreated : '';
                } else {
                    $code = $apiClient->getResponseCode();
                    $message = 'Can\'t get Sirv account info. ' .
                        'Code: ' . $code . ' ' . $apiClient->getErrorMsg();
                    $this->_logger->error($message);
                    if ($code == 401 || $code == 403) {
                        return [];
                    }
                    throw new \Magento\Framework\Exception\LocalizedException(
                        new \Magento\Framework\Phrase($message)
                    );
                }

                $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 60);
            }

            $config = $data;
        }

        return $config;
    }

    /**
     * Set account config
     *
     * @param bool $fetching
     * @param string $url
     * @param array $auth
     * @return void
     */
    public function setAccountConfig($fetching, $url, $auth = [])
    {
        if (!$fetching) {
            //NOTE: this code in order to be able to use two instances of M2 with different option values
            return;
        }

        $config = $this->getAccountConfig();

        $data = [];

        if ($fetching != $config['fetching_enabled'] || $url != $config['fetching_url']) {
            $data['fetching'] = [
                'enabled' => $fetching
            ];

            if (!empty($url)) {
                $data['fetching']['type'] = 'http';
                $data['fetching']['http'] = [
                    'url' => $url
                ];

                if (empty($auth)) {
                    $data['fetching']['http']['auth'] = [
                        'enabled' => false
                    ];
                } else {
                    $data['fetching']['http']['auth'] = [
                        'enabled' => true,
                        'username' => $auth['user'],
                        'password' => $auth['pass']
                    ];
                }
            }
        }

        if ($fetching && $config['minify']) {
            $data['minify'] = [
                'enabled' => false
            ];
        }

        /** @var \Sirv\Magento2\Model\Api\Sirv $apiClient */
        $apiClient = null;

        if ($fetching && ($fetching != $config['fetching_enabled'])) {
            $apiClient = $this->getSirvClient();
            $apiClient->enableJsAndHtmlServing(true);
        }

        if (!empty($data)) {
            if ($apiClient === null) {
                $apiClient = $this->getSirvClient();
            }

            $updated = $apiClient->updateAccountInfo($data);
            if ($updated) {
                $cacheId = 'sirv_account_info_' . $this->getAccountId();
                $cache = $this->getAppCache();
                $config['fetching_enabled'] = $fetching;
                if (!empty($url)) {
                    $config['fetching_url'] = $url;
                }
                $cache->save($this->getSerializer()->serialize($config), $cacheId, [], 60);
            }
        }
    }

    /**
     * Sync config value
     *
     * @param string $name
     * @return string
     */
    public function syncConfig($name)
    {
        $config = $this->getAccountConfig();
        switch ($name) {
            case 'cdn_url':
                $value = $config['cdn_url'];
                $this->saveConfig('cdn_url', $value);
                break;
            case 'auto_fetch':
                //NOTE: auto_fetch: custom|all|none
                //      fetching_enabled: true|false
                if ($config['fetching_enabled']) {
                    $value = $this->getConfig('auto_fetch');
                    /*
                    NOTE: this code is commented out so that two instances of M2 can be used with different option values
                    if ($value != 'custom' && $value != 'all') {
                        $value = 'custom';
                    }
                    */
                } else {
                    $value = 'none';
                }
                $this->saveConfig('auto_fetch', $value);
                break;
            case 'url_prefix':
                $value = $config['fetching_url'];
                $this->saveConfig('url_prefix', $value);
                break;
            default:
                $value = null;
        }

        return $value;
    }

    /**
     * Get a list of domains
     *
     * @return array
     */
    public function getDomains()
    {
        static $list = null;

        if ($list === null) {
            $list = [];
            /** @var \Magento\Store\Model\StoreRepository $repository */
            $repository = $this->objectManager->get(\Magento\Store\Api\StoreRepositoryInterface::class);
            $backendConfig = $this->objectManager->get(\Magento\Backend\App\ConfigInterface::class);
            $isBackendUrlSecure = $backendConfig->isSetFlag(\Magento\Store\Model\Store::XML_PATH_SECURE_IN_ADMINHTML);
            $stores = $repository->getList();
            $adminUrls = [];
            foreach ($stores as $store) {
                /** @var Magento\Store\Model\Store $store */

                $isUrlSecure = $store->getCode() == 'admin' ? $isBackendUrlSecure : $store->isFrontUrlSecure();
                $url = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK, $isUrlSecure);
                if (preg_match('#^(?:(?:https?\:)?//)?[^/]++#', $url, $match)) {
                    $url = $match[0] . '/';
                }

                if ($store->getCode() == 'admin') {
                    $adminUrls[hash('md5', $url)] = $url;
                    continue;
                }

                $list[hash('md5', $url)] = $url;
            }

            $list = array_merge($list, $adminUrls);
        }

        return $list;
    }

    /**
     * Get account usage data
     *
     * @param bool $force
     * @return array
     */
    public function getAccountUsageData($force = false)
    {
        static $data = null;

        if ($data === null || $force) {
            $cacheId = 'sirv_account_usage_' . $this->getAccountId();
            $cache = $this->getAppCache();

            $data = $force ? false : $cache->load($cacheId);
            if (false !== $data) {
                $data = $this->getUnserializer()->unserialize($data);
            }

            if (!is_array($data)) {
                $data = $this->collectAccountUsageData();
                //NOTE: 900 - cache lifetime in seconds (15 minutes)
                $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 900);
            }
        }

        return $data;
    }

    /**
     * Collect account usage data
     *
     * @return array
     */
    protected function collectAccountUsageData()
    {
        $data = [];
        $data['account'] = $this->getConfig('account') ?: 'unknown';
        $data['email'] = $this->getConfig('email') ?: 'unknown';

        $sirvClient = $this->getSirvClient();

        $billingPlanInfo = $sirvClient->getBillingPlanInfo();
        $dataTransferLimit = 0;
        $data['plan'] = [];
        if ($billingPlanInfo) {
            $planName = isset($billingPlanInfo->name) ? $billingPlanInfo->name : 'unknown';
            $storageLimit = isset($billingPlanInfo->storage) ? (int)$billingPlanInfo->storage : 0;
            $dataTransferLimit = isset($billingPlanInfo->dataTransferLimit) ? (int)$billingPlanInfo->dataTransferLimit : 0;
            $data['plan'] = [
                'name' => $planName,
                'storage_limit' => $this->getFormatedSize($storageLimit),
                'data_transfer_limit' => $dataTransferLimit ? $this->getFormatedSize($dataTransferLimit) : '&#8734',
            ];
        }

        $data['storage'] = [];
        $storageInfo = $sirvClient->getStorageInfo();
        if ($storageInfo) {
            $allowance = (int)$storageInfo->plan + (int)$storageInfo->extra;
            $used = (int)$storageInfo->used;
            $available = $allowance - $used;
            $data['storage'] = [
                'allowance' => $this->getFormatedSize($allowance),
                'used' => $this->getFormatedSize($used),
                'used_percent' => number_format($used / $allowance * 100, 2, '.', ''),
                'available' => $this->getFormatedSize($available),
                'available_percent' => number_format($available / $allowance * 100, 2, '.', ''),
                'files' => (int)$storageInfo->files
            ];
        }

        $data['traffic'] = [
            'allowance' => $this->getFormatedSize($dataTransferLimit),
            'traffic' => []
        ];
        $dates = [
            'This month' => [
                date('Y-m-01'),
                date('Y-m-t')
            ],
            date('F Y', strtotime('first day of -1 month')) => [
                date('Y-m-01', strtotime('first day of -1 month')),
                date('Y-m-t', strtotime('last day of -1 month'))
            ],
            date('F Y', strtotime('first day of -2 month')) => [
                date('Y-m-01', strtotime('first day of -2 month')),
                date('Y-m-t', strtotime('last day of -2 month'))
            ],
            date('F Y', strtotime('first day of -3 month')) => [
                date('Y-m-01', strtotime('first day of -3 month')),
                date('Y-m-t', strtotime('last day of -3 month'))
            ]
        ];
        $dataTransferLimit = $dataTransferLimit ? $dataTransferLimit : PHP_INT_MAX;

        foreach ($dates as $label => $date) {
            $traffic = $sirvClient->getHttpStats($date[0], $date[1]);
            if (empty($traffic)) {
                break;
            }

            $traffic = get_object_vars($traffic);
            $size = 0;
            foreach ($traffic as $v) {
                $size += (isset($v->total->size) ? (int)$v->total->size : 0);
            }
            $sizePercent = ($size / $dataTransferLimit) * 100;
            $trafficAttr = $sizePercent > 100 ? 'exceeded' : ($sizePercent ? 'normal' : 'empty');

            $data['traffic']['traffic'][$label] = [
                'size' => $this->getFormatedSize($size),
                'size_percent' => number_format($sizePercent, 2, '.', ''),
                'traffic_attr' => $trafficAttr
            ];
        }

        $limitsData = $this->getApiLimitsData();
        $data['limits'] = empty($limitsData) ? [] : $limitsData['limits'];
        $data['current_time'] = empty($limitsData) ? date('H:i:s e', time()) : $limitsData['current_time'];
        $data['fetch_file_limit'] = isset($limitsData['fetch_file_limit']) ? $limitsData['fetch_file_limit'] : 0;
        $data['upload_file_limit'] = isset($limitsData['upload_file_limit']) ? $limitsData['upload_file_limit'] : 0;

        return $data;
    }

    /**
     * Get API limits data
     *
     * @return array
     */
    public function getApiLimitsData()
    {
        $data = [];
        $limits = $this->getSirvClient()->getAPILimits();
        if ($limits) {
            $currentTime = time();
            $data['limits'] = [];
            foreach ($limits as $type => $limitData) {
                if ($type == 'images:realtime' || $type == 'zips2:create') {
                    continue;
                }
                $remaining = (int)$limitData->remaining;
                $reset = '-';
                if ($remaining <= 0) {
                    $expireTime = (int)$limitData->reset;
                    if ($expireTime >= $currentTime) {
                        $timeIsLeft = $expireTime - $currentTime;
                        if ($timeIsLeft < 60) {
                            $timeIsLeft = $timeIsLeft . ' second' . ($timeIsLeft > 1 ? 's' : '');
                        } else {
                            $timeIsLeft = floor($timeIsLeft / 60);
                            $timeIsLeft = $timeIsLeft . ' minute' . ($timeIsLeft > 1 ? 's' : '');
                        }
                        $reset = $timeIsLeft . ' (' . date('Y-m-d\TH:i:s.v\Z e', $expireTime) . ')';
                    }
                }
                $data['limits'][] = [
                    'type' => $type,
                    'limit' => $limitData->limit,
                    'count' => $limitData->count,
                    'reset' => $reset,
                ];
            }
            $data['current_time'] = date('H:i:s e', $currentTime);
            $data['fetch_file_limit'] = $limits->{'fetch:file'}->limit;
            $data['upload_file_limit'] = $limits->{'rest:post:files:upload'}->limit;
        }

        return $data;
    }

    /**
     * Update API limits cached data
     *
     * @param array $limitsData
     * @return void
     */
    public function updateApiLimitsCachedData($limitsData)
    {
        $cacheId = 'sirv_account_usage_' . $this->getAccountId();
        $cache = $this->getAppCache();
        $data = $cache->load($cacheId);
        if (false !== $data) {
            $data = $this->getUnserializer()->unserialize($data);
            if (is_array($data)) {
                $data['limits'] = empty($limitsData) ? [] : $limitsData['limits'];
                $data['current_time'] = empty($limitsData) ? date('H:i:s e', time()) : $limitsData['current_time'];
                $data['fetch_file_limit'] = isset($limitsData['fetch_file_limit']) ? $limitsData['fetch_file_limit'] : 0;
                $data['upload_file_limit'] = isset($limitsData['upload_file_limit']) ? $limitsData['upload_file_limit'] : 0;
                //NOTE: 900 - cache lifetime in seconds (15 minutes)
                $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 900);
            }
        }
    }

    /**
     * Get formated size
     *
     * @param int $size
     * @param int $precision
     * @return string
     */
    protected function getFormatedSize($size, $precision = 2)
    {
        $sign = ($size >= 0) ? '' : '-';
        $size = abs($size);

        $units = [' Bytes', ' KB', ' MB', ' GB', ' TB'];
        for ($i = 0; $size >= 1000 && $i < 4; $i++) {
            $size /= 1000;
        }

        return $sign . round($size, $precision) . $units[$i];
    }

    /**
     * Get Sirv JS file size
     *
     * @param string $url
     * @return string
     */
    public function getSirvJsFileSize($url)
    {
        $cacheId = 'sirv_js_file_size';
        $cache = $this->getAppCache();

        $data = $cache->load($cacheId);
        if (false !== $data) {
            $data = $this->getUnserializer()->unserialize($data);
        }

        if (!is_array($data)) {
            $data = [];
        }

        if (isset($data[$url]) && is_array($data[$url])) {
            return 'File size: ' . $this->getFormatedSize($data[$url]['download_size']) .
                ' (unzipped ' . $this->getFormatedSize($data[$url]['resource_size']) . ')';
        }

        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

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
                CURLOPT_ENCODING => ''
            ]
        );

        $contents = curl_exec(self::$curlHandle);

        if ($error = curl_errno(self::$curlHandle)) {
            return "cURL Error ($error): " . curl_error(self::$curlHandle);
        }

        $code = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            return "HTTP Error ($code).";
        }

        $downloadSize = curl_getinfo(self::$curlHandle, CURLINFO_SIZE_DOWNLOAD);
        $resourceSize = strlen($contents);

        $data[$url] = [
            'download_size' => (int)$downloadSize,
            'resource_size' => (int)$resourceSize
        ];
        $cache->save($this->getSerializer()->serialize($data), $cacheId, [], 60 * 60 * 24);

        return 'File size: ' . $this->getFormatedSize($data[$url]['download_size']) .
            ' (unzipped ' . $this->getFormatedSize($data[$url]['resource_size']) . ')';
    }
}
