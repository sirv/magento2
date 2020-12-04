<?php

namespace MagicToolbox\Sirv\Controller\Adminhtml\Ajax;

/**
 * Changelog ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Changelog extends \MagicToolbox\Sirv\Controller\Adminhtml\Settings
{
    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Changelog URL
     *
     * @var string
     */
    protected $changelogUrl = 'https://sirv.com/help/articles/magento-cdn-sirv-extension/#changelog';

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper
    ) {
        parent::__construct($context, $resultPageFactory);
        $this->dataHelper = $dataHelper;
    }

    /**
     * Synchronize action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
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
            $currentVersion = $this->dataHelper->getModuleVersion('MagicToolbox_Sirv');
            $list = [];
            foreach ($changelog as $version => $data) {
                $new = version_compare($currentVersion, $version, '<');
                $list[] = [
                    'new' => $new,
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
            $cache = $this->dataHelper->getAppCache();
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
