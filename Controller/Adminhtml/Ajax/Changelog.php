<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Changelog ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Changelog extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Changelog URL
     *
     * @var string
     */
    protected $changelogUrl = 'https://sirv.com/help/articles/magento-cdn-sirv-extension/#changelog';

    /**
     * Synchronize action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = [
            'error' => false,
            'link' => 'https://sirv.com/help/articles/magento-cdn-sirv-extension/#installation',
            'items' => []
        ];

        $changelog = $this->getChangelog();
        if ($changelog) {
            /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
            $dataHelper = $this->getDataHelper();
            $currentVersion = $dataHelper->getModuleVersion('Sirv_Magento2');
            $list = [];
            foreach ($changelog as $version => $data) {
                $list[] = [
                    'new' => version_compare($currentVersion, $version, '<'),
                    'current' => version_compare($currentVersion, $version, '='),
                    'version' => $version,
                    'date' => $data->date,
                    'changes' => $data->changes
                ];
            }
            $result['items'] = $list;
        } else {
            $result['error'] = __(
                'An error occurred while receiving the %1changelog%2.',
                '<a href="' . $this->changelogUrl . '" target="_blank">',
                '</a>'
            );
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;
    }

    /**
     * Get changelog
     *
     * @return object|bool
     */
    protected function getChangelog()
    {
        static $changelog = null;

        if ($changelog === null) {
            $changelog = false;
            $cacheId = 'sirv_module_changelog_json';
            /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
            $dataHelper = $this->getDataHelper();
            $cache = $dataHelper->getAppCache();
            $json = $cache->load($cacheId);
            if (false === $json) {
                $hostname = 'www.magictoolbox.com';
                $errno = 0;
                $errstr = '';
                $path = 'changelog/sirvmagento2/?t=' . time();
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
                    $changelog = json_decode($response);
                    if (is_object($changelog)) {
                        $cache->save($response, $cacheId, [], 600);
                    }
                }
                $changelog = $changelog ?: null;
            } else {
                $changelog = json_decode($json);
            }
        }

        return $changelog;
    }
}
