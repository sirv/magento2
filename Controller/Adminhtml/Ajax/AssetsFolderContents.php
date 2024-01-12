<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Assets folder contents ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AssetsFolderContents extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Excluded paths
     *
     * @var array
     */
    protected $excludedPaths = [
        '.Trash',
        '.processed',
        '.well-known',
        'Profiles',
        'Shared'
    ];

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $queryData = $this->getRequest()->getQueryValue();
        $path = $queryData['path'] ?? '';
        $path = '/' . trim(trim($path), '/');

        $dataHelper = $this->getDataHelper();
        $sirvClient = $dataHelper->getSirvClient();
        $contents = $sirvClient->getFolderContents($path);

        $list['folder'] = $list['file'] = [];

        $path == '/' || ($path .= '/');
        foreach ($contents as $item) {
            if ($item->isDirectory) {
                if (in_array($item->filename, $this->excludedPaths)) {
                    continue;
                }
                $type = 'folder';
            } else {
                $type = 'file';
            }

            $list[$type][] = [
                'name' => $item->filename,
                'type' => $type,
                'path' => $path . $item->filename,
                'size' => $item->size ?? 0,
                'width' => $item->meta->width ?? 0,
                'height' => $item->meta->height ?? 0,
            ];
        }

        uasort($list['folder'], [$this, 'sortItems']);
        uasort($list['file'], [$this, 'sortItems']);

        $list = array_merge($list['folder'], $list['file']);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData(array_values($list));

        return $resultJson;
    }

    /**
     * Sort items
     *
     * @param array $a
     * @param array $b
     * @return integer
     */
    protected function sortItems($a, $b)
    {
        if ($a['name'] == $b['name']) {
            return 0;
        }

        return ($a['name'] < $b['name']) ? -1 : 1;
    }
}
