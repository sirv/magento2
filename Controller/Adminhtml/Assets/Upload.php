<?php

namespace Sirv\Magento2\Controller\Adminhtml\Assets;

use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;

/**
 * Upload backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Upload extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var array
     */
    protected $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'svg',
        'mpg', 'mpeg', 'm4v', 'mp4', 'avi', 'mov', 'ogv',
        'usdz', 'glb', 'dwg'
    ];

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * Data helper factory
     *
     * @var \Sirv\Magento2\Helper\Data\BackendFactory
     */
    protected $dataHelperFactory = null;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        ?\Magento\Framework\Filesystem $filesystem = null,
        ?\Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory = null
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->filesystem = $filesystem ?: ObjectManager::getInstance()
            ->get(\Magento\Framework\Filesystem::class);
        $this->dataHelperFactory = $dataHelperFactory;
    }

    /**
     * Upload file(s) to Sirv
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        try {
            $data = $this->getRequest()->getPostValue();
            $sirvPath = $data['sirv_path'];
            if (empty($sirvPath)) {
                $sirvPath = '/';
            }

            $uploader = $this->_objectManager->create(
                \Sirv\Magento2\Model\File\Uploader::class,
                ['fileId' => 'sirv-file-upload-input']
            );
            $uploader->setAllowedExtensions($this->getAllowedExtensions());
            $uploader->addValidateCallback('sirv_asset', $this, 'validateUploadFile');
            $uploader->setAllowRenameFiles(false);
            $uploader->setFilesDispersion(false);
            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

            $result = $uploader->save($mediaDirectory->getAbsolutePath('tmp/sirv'));
            if (is_array($result)) {
                $absPath = $result['path'] . '/' . $result['name'];
                $dataHelper = $this->dataHelperFactory->create();
                $sirvClient = $dataHelper->getSirvClient();
                $uploaded = $sirvClient->uploadFile($sirvPath . '/' . $result['name'], $absPath);
                if (!$uploaded) {
                    $errorMessage = $this->sirvClient->getErrorMsg();
                    if (empty($errorMessage)) {
                        $errorMessage = 'Something went wrong while uploading the file(s).';
                    }
                    $result = ['error' => $errorMessage];
                }
                is_file($absPath) && unlink($absPath);
                unset($result['tmp_name']);
                unset($result['path']);
            } else {
                $result = ['error' => 'Something went wrong while saving the file(s).'];
            }
        } catch (LocalizedException $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        } catch (\Throwable $e) {
            $result = ['error' => 'Something went wrong while saving the file(s).', 'errorcode' => 0];
        }

        /** @var \Magento\Framework\Controller\Result\Raw $response */
        $response = $this->resultRawFactory->create();
        $response->setHeader('Content-type', 'text/plain');
        $response->setContents(json_encode($result));
        return $response;
    }

    /**
     * Get the set of allowed file extensions.
     *
     * @return array
     */
    protected function getAllowedExtensions()
    {
        return $this->allowedExtensions;
    }

    /**
     * Validate upload file
     *
     * @param string $filePath
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validateUploadFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('Upload file does not exist.');
        }

        if (filesize($filePath) === 0) {
            throw new \InvalidArgumentException('Wrong file size.');
        }

        return true;
    }
}
