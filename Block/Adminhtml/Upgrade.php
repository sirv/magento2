<?php

namespace Sirv\Magento2\Block\Adminhtml;

/**
 * Upgrade block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Upgrade extends \Magento\Framework\View\Element\Template
{
    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Class constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->dataHelper = $this->objectManager->get(\Sirv\Magento2\Helper\Data\Backend::class);
    }

    /**
     * Get the current version of the module
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        return $this->dataHelper->getModuleVersion('Sirv_Magento2');
    }

    /**
     * Get the latest version of the module
     *
     * @return string|bool
     */
    public function getLatestVersion()
    {
        static $version = null;

        if ($version === null) {
            $cacheId = 'sirv_module_latest_version';
            $cache = $this->dataHelper->getAppCache();
            $version = $cache->load($cacheId);

            if (false === $version) {
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

                if (is_string($version)) {
                    $cache->save($version, $cacheId, [], 600);
                }
            }
        }

        return $version;
    }

    /**
     * Does the module have updates?
     *
     * @return bool
     */
    public function hasNewVersion()
    {
        $currentVersion = $this->getCurrentVersion();
        if ($currentVersion) {
            $latestVersion = $this->getLatestVersion();
            if ($latestVersion && version_compare($currentVersion, $latestVersion, '<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get changelog URL
     *
     * @return string
     */
    public function getChangelogUrl()
    {
        return $this->getUrl('sirv/ajax/changelog');
    }
}
