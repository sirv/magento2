<?php

namespace Sirv\Magento2\Helper\Sync;

/**
 * Backend sync helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Backend extends \Sirv\Magento2\Helper\Sync
{
    /**
     * Join data with MySQL
     *
     * @var bool
     */
    protected $joinWithMySQL = false;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig
     * @param \Sirv\Magento2\Helper\Data\Backend $dataHelper
     * @param \Sirv\Magento2\Model\CacheFactory $cacheModelFactory
     * @param \Sirv\Magento2\Model\MessagesFactory $messagesModelFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig,
        \Sirv\Magento2\Helper\Data\Backend $dataHelper,
        \Sirv\Magento2\Model\CacheFactory $cacheModelFactory,
        \Sirv\Magento2\Model\MessagesFactory $messagesModelFactory
    ) {
        parent::__construct(
            $context,
            $filesystem,
            $catalogProductMediaConfig,
            $dataHelper,
            $cacheModelFactory,
            $messagesModelFactory
        );

        $this->joinWithMySQL = $dataHelper->getConfig('join_with_mysql') === 'true' ? true : false;
    }

    /**
     * Remove cache table data
     *
     * @param string $path
     * @return bool
     */
    public function removeCacheData($path)
    {
        try {
            /** @var \Sirv\Magento2\Model\Cache $cacheModel */
            $cacheModel = $this->cacheModel->clearInstance()->load($path, 'path');
            $id = $cacheModel->getId();
            if ($id !== null) {
                $cacheModel->delete();
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }

        return true;
    }

    /**
     * Get message model
     *
     * @return \Sirv\Magento2\Model\Messages
     */
    public function getMessageModel()
    {
        static $messagesModel = null;

        if ($messagesModel === null) {
            $messagesModel = $this->messagesModelFactory->create();
        }

        return $messagesModel;
    }

    /**
     * Remove file from Sirv and database
     *
     * @param string $path
     * @return bool
     */
    public function remove($path)
    {
        if (!$this->isAuth) {
            return false;
        }

        try {
            $result = $this->sirvClient->deleteFile($this->imageFolder . $path);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result = false;
        }

        if ($result) {
            $this->removeCacheData($path);
        }

        return $result;
    }

    /**
     * Get sync data
     *
     * @param bool $force
     * @return array
     */
    public function getSyncData($force = false)
    {
        static $data = null;

        if (!($force || $data === null)) {
            return $data;
        }

        $cacheId = 'sirv_sync_data';
        $appCache = $this->dataHelper->getAppCache();
        $data = $force ? false : $appCache->load($cacheId);
        if (false !== $data) {
            $data = $this->dataHelper->getUnserializer()->unserialize($data);
            if (is_array($data)) {
                return $data;
            }
        }

        /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        $mediaToEntityTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_VALUE_TO_ENTITY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_with_slash' => $this->productMediaRelPath . '/',
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $getData = function () use (
                &$connection,
                &$mtSelect,
                &$mediaTable,
                &$mediaToEntityTable,
                &$ctSelect,
                &$cacheTable,
                &$bind
            ) {
                $mtSelect->reset()
                    ->from(
                        ['mt' => $mediaTable],
                        ['total' => 'COUNT(DISTINCT BINARY(`mt`.`value`))']
                    )
                    ->joinInner(
                        ['mtet' => $mediaToEntityTable],
                        '`mt`.`value_id` = `mtet`.`value_id`',
                        []
                    )
                    ->where('`mt`.`value` IS NOT NULL')
                    ->where('`mt`.`value` != ?', '');

                /** @var int $total */
                $total = (int)$connection->fetchOne($mtSelect);

                $mtSelect->reset()
                    ->distinct()
                    ->from(
                        ['mt' => $mediaTable],
                        ['unique_value' => 'BINARY(`mt`.`value`)']
                    )
                    ->joinInner(
                        ['mtet' => $mediaToEntityTable],
                        '`mt`.`value_id` = `mtet`.`value_id`',
                        []
                    )
                    ->where('`mt`.`value` IS NOT NULL')
                    ->where('`mt`.`value` != ?', '');

                $ctSelect->reset()
                    ->from(
                        ['ct' => $cacheTable],
                        [
                            'ct.status',
                            'sum' => 'COUNT(`ct`.`status`)',
                        ]
                    )
                    ->joinInner(
                        ['tt' => new \Zend_Db_Expr("({$mtSelect})")],
                        '`tt`.`unique_value` = `ct`.`path` OR ' .
                        'CONCAT(:pm_rel_path, `tt`.`unique_value`) = `ct`.`path` OR ' .
                        'CONCAT(:pm_rel_path_with_slash, `tt`.`unique_value`) = `ct`.`path`',
                        []
                    )
                    ->where('(`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp))')
                    ->group('ct.status');

                /** @var array $pairs */
                $pairs = $connection->fetchPairs($ctSelect, $bind);

                $data = [];
                $data['total'] = $total;
                $data['synced'] = isset($pairs[self::IS_SYNCED]) ? (int)$pairs[self::IS_SYNCED] : 0;
                $data['new'] = isset($pairs[self::IS_NEW]) ? (int)$pairs[self::IS_NEW] : 0;
                $data['processing'] = isset($pairs[self::IS_PROCESSING]) ? (int)$pairs[self::IS_PROCESSING] : 0;
                $data['queued'] = $data['new'] + $data['processing'];
                $data['failed'] = isset($pairs[self::IS_FAILED]) ? (int)$pairs[self::IS_FAILED] : 0;

                return $data;
            };
        } else {
            unset($bind[':pm_rel_path_with_slash']);

            $getData = function () use (
                &$connection,
                &$mtSelect,
                &$mediaTable,
                &$mediaToEntityTable,
                &$ctSelect,
                &$cacheTable,
                &$bind
            ) {
                $mtSelect->reset()
                    ->distinct()
                    ->from(
                        ['mt' => $mediaTable],
                        ['unique_value' => 'BINARY(`mt`.`value`)']
                    )
                    ->joinInner(
                        ['mtet' => $mediaToEntityTable],
                        '`mt`.`value_id` = `mtet`.`value_id`',
                        []
                    )
                    ->where('`mt`.`value` IS NOT NULL')
                    ->where('`mt`.`value` != ?', '');

                $mediaPathes = $connection->fetchCol($mtSelect, []);
                foreach ($mediaPathes as &$path) {
                    $path = '/' . ltrim($path, '\\/');
                }

                unset($path);
                $mediaPathes = array_flip($mediaPathes);

                $ctSelect->reset()
                    ->distinct()
                    ->from(
                        ['ct' => $cacheTable],
                        [
                            'short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")',
                            'status'
                        ]
                    )
                    ->where('(`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp))');

                /** @var array $cachedData */
                $cachedData = $connection->fetchAll($ctSelect, $bind);

                $pairs = [
                    self::IS_UNDEFINED => 0,
                    self::IS_NEW => 0,
                    self::IS_PROCESSING => 0,
                    self::IS_SYNCED => 0,
                    self::IS_FAILED => 0,
                ];
                foreach ($cachedData as $row) {
                    if (isset($mediaPathes[$row['short_path']])) {
                        $pairs[$row['status']]++;
                    }
                }

                $data = [];
                $data['total'] = count($mediaPathes);
                $data['synced'] = $pairs[self::IS_SYNCED];
                $data['new'] = $pairs[self::IS_NEW];
                $data['processing'] = $pairs[self::IS_PROCESSING];
                $data['queued'] = $data['new'] + $data['processing'];
                $data['failed'] = $pairs[self::IS_FAILED];

                return $data;
            };
        }

        $data = $getData();
        $cached = $data['synced'] + $data['queued'] + $data['failed'];

        if ($data['total'] < $cached) {
            $this->fixSyncData();
            $data = $getData();
        }

        if (!$data['queued'] && ($data['total'] == $cached)) {
            $data['completed'] = true;
        }

        $appCache->save($this->dataHelper->getSerializer()->serialize($data), $cacheId, [], 120);

        return $data;
    }

    /**
     * Method to get media pathes that are not cached
     *
     * @param int $limit
     * @return array
     */
    protected function getNotCachedPathes($limit = 0)
    {
        /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        $mediaToEntityTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_VALUE_TO_ENTITY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $ctSelect->reset()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)')
                ->where('`ct`.`status` != ?', self::IS_UNDEFINED);

            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinInner(
                    ['mtet' => $mediaToEntityTable],
                    '`mt`.`value_id` = `mtet`.`value_id`',
                    []
                )
                ->joinLeft(
                    ['tt' => new \Zend_Db_Expr("({$ctSelect})")],
                    '`tt`.`short_path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`short_path`) = `mt`.`value`',
                    []
                )
                ->where('`tt`.`short_path` IS NULL')
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            if ($limit) {
                $mtSelect->limit($limit);
            }

            /** @var array $result */
            $result = $connection->fetchCol($mtSelect, $bind);
        } else {
            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinInner(
                    ['mtet' => $mediaToEntityTable],
                    '`mt`.`value_id` = `mtet`.`value_id`',
                    []
                )
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            $mediaPathes = $connection->fetchCol($mtSelect, []);
            foreach ($mediaPathes as &$path) {
                $path = '/' . ltrim($path, '\\/');
            }
            unset($path);

            $ctSelect->reset()
                ->distinct()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('(`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp))')
                ->where('`ct`.`status` != ?', self::IS_UNDEFINED);

            $cachedPathes = $connection->fetchCol($ctSelect, $bind);
            $cachedPathes = array_flip($cachedPathes);

            $result = [];
            $i = 0;
            foreach ($mediaPathes as $path) {
                if (isset($cachedPathes[$path])) {
                    continue;
                }
                $result[] = $path;
                $i++;
                if ($limit && $i == $limit) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Method to get media pathes that are not cached (alternative method using a temporary table)
     *
     * @param int $limit
     * @return array
     */
    public function getNotCachedPathesAlt($limit = 0)
    {
        /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        $mediaToEntityTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_VALUE_TO_ENTITY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $cacheTableTemp = "{$cacheTable}_temp";

        $table = $connection->createTableByDdl($cacheTable, $cacheTableTemp);
        $connection->createTable($table);

        $connection->truncateTable($cacheTableTemp);

        $ctSelect->reset()
            ->from(
                ['ct' => $cacheTable],
                ['path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
            )
            ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)')
            ->where('`ct`.`status` != ?', self::IS_UNDEFINED);

        $query = $ctSelect->insertIgnoreFromSelect($cacheTableTemp, ['path']);

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        $connection->query($query, $bind);

        $mtSelect->reset()
            ->distinct()
            ->from(
                ['mt' => $mediaTable],
                ['unique_value' => 'BINARY(`mt`.`value`)']
            )
            ->joinInner(
                ['mtet' => $mediaToEntityTable],
                '`mt`.`value_id` = `mtet`.`value_id`',
                []
            )
            ->joinLeft(
                ['tt' => "{$cacheTableTemp}"],
                '`tt`.`path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`path`) = `mt`.`value`',
                []
            )
            ->where('`tt`.`path` IS NULL')
            ->where('`mt`.`value` IS NOT NULL')
            ->where('`mt`.`value` != ?', '');

        if ($limit) {
            $mtSelect->limit($limit);
        }

        /** @var array $result */
        $result = $connection->fetchCol($mtSelect, []);

        return $result;
    }

    /**
     * Method to get media pathes that are cached
     *
     * @param int|array $status
     * @param int $limit
     * @return array
     */
    public function getCachedPathes($status, $limit = 0)
    {
        /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        $mediaToEntityTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_VALUE_TO_ENTITY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $ctSelect->reset()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`status` ' . (is_array($status) ? 'IN (?)' : ' = ?'), $status)
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)');

            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinInner(
                    ['mtet' => $mediaToEntityTable],
                    '`mt`.`value_id` = `mtet`.`value_id`',
                    []
                )
                ->joinLeft(
                    ['tt' => new \Zend_Db_Expr("({$ctSelect})")],
                    '`tt`.`short_path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`short_path`) = `mt`.`value`',
                    []
                )
                ->where('`tt`.`short_path` IS NOT NULL')
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            if ($limit) {
                $mtSelect->limit($limit);
            }

            /** @var array $result */
            $result = $connection->fetchCol($mtSelect, $bind);
        } else {
            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinInner(
                    ['mtet' => $mediaToEntityTable],
                    '`mt`.`value_id` = `mtet`.`value_id`',
                    []
                )
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            $mediaPathes = $connection->fetchCol($mtSelect, []);
            foreach ($mediaPathes as &$path) {
                $path = '/' . ltrim($path, '\\/');
            }
            unset($path);
            $mediaPathes = array_flip($mediaPathes);

            $ctSelect->reset()
                ->distinct()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`status` ' . (is_array($status) ? 'IN (?)' : ' = ?'), $status)
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)');

            $pathes = $connection->fetchCol($ctSelect, $bind);

            $result = [];
            $i = 0;
            foreach ($pathes as $path) {
                if (isset($mediaPathes[$path])) {
                    $result[] = $path;
                    $i++;
                    if ($limit && $i == $limit) {
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Fix sync data
     *
     * @return array
     */
    protected function fixSyncData()
    {
        $data = [
            self::IS_UNDEFINED => 0,
            self::IS_NEW => 0,
            self::IS_PROCESSING => 0,
            self::IS_SYNCED => 0,
            self::IS_FAILED => 0,
        ];

        //NOTE: try to clear duplicates

        $duplicates = $this->getDuplicateData();

        $toDelete = [];
        foreach ($duplicates as $pair) {
            if ($pair[1]['status'] == self::IS_SYNCED && $pair[2]['status'] != self::IS_SYNCED) {
                $toDelete[] = $pair[2]['id'];
                $data[$pair[2]['status']]++;
            } elseif ($pair[1]['status'] != self::IS_SYNCED && $pair[2]['status'] == self::IS_SYNCED) {
                $toDelete[] = $pair[1]['id'];
                $data[$pair[1]['status']]++;
            } else {
                if ($pair[1]['path_type'] == self::MAGENTO_MEDIA_PATH) {
                    $toDelete[] = $pair[2]['id'];
                    $data[$pair[2]['status']]++;
                } else {
                    $toDelete[] = $pair[1]['id'];
                    $data[$pair[1]['status']]++;
                }
            }
        }

        if (!empty($toDelete)) {
            /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
            $resource = $this->cacheModel->getResource();
            $resource->deleteByIds($toDelete);
        }

        return $data;
    }

    /**
     * Method to get media data with dublicated pathes
     *
     * @return array
     */
    protected function getDuplicateData()
    {
        /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $ctSelect->reset()
            ->from(
                ['ct1' => $cacheTable],
                [
                    'id_1' => 'ct1.id',
                    'id_2' => 'ct2.id',
                    'path_1' => 'ct1.path',
                    'path_2' => 'ct2.path',
                    'path_type_1' => 'ct1.path_type',
                    'path_type_2' => 'ct2.path_type',
                    'status_1' => 'ct1.status',
                    'status_2' => 'ct2.status',
                    'modification_time_1' => 'ct1.modification_time',
                    'modification_time_2' => 'ct2.modification_time',
                ]
            )
            ->joinInner(
                ['ct2' => $cacheTable],
                '((`ct1`.`id` != `ct2`.`id`) AND (CONCAT(:pm_rel_path, `ct1`.`path`) = `ct2`.`path` OR CONCAT(:cm_rel_path, `ct1`.`path`) = `ct2`.`path`))',
                []
            )
            ->order('ct1.id ASC');
        /*
        SELECT
            `ct1`.`id` AS `id_1`,
            `ct2`.`id` AS `id_2`,
            `ct1`.`path` AS `path_1`,
            `ct2`.`path` AS `path_2`,
            `ct1`.`path_type` AS `path_type_1`,
            `ct2`.`path_type` AS `path_type_2`,
            `ct1`.`status` AS `status_1`,
            `ct2`.`status` AS `status_2`,
            `ct1`.`modification_time` AS `modification_time_1`,
            `ct2`.`modification_time` AS `modification_time_2`
        FROM
            `m2_sirv_cache` AS `ct1`
        INNER JOIN `m2_sirv_cache` AS `ct2` ON
            `ct1`.`id` != `ct2`.`id`
            AND
            (CONCAT('/catalog/product', `ct1`.`path`) = `ct2`.`path` OR CONCAT('/catalog/category/', `ct1`.`path`) = `ct2`.`path`)
        ORDER BY `ct1`.`id` ASC
        */

        $bind = [
            ':pm_rel_path' => $this->productMediaRelPath,
            ':cm_rel_path' => $this->categoryMediaRelPath . '/',
        ];

        /** @var array $result */
        $result = $connection->fetchAll($ctSelect, $bind);

        $duplicates = [];
        foreach ($result as $data) {
            $pair = [1 => [], 2 => []];
            foreach ($data as $key => $value) {
                $i = substr($key, -1);
                $name = substr($key, 0, -2);
                $pair[$i][$name] = $value;
            }
            $duplicates[] = $pair;
        }

        return $duplicates;
    }

    /**
     * Method to synchronize media gallery
     *
     * @param int $stage
     * @param array $options
     * @return array
     */
    public function syncMediaGallery($stage, $options)
    {
        if (!$this->isAuth) {
            return ['error' => 'Not authenticated!'];
        }

        $startTime = time();
        $maxExecutionTime = (int)ini_get('max_execution_time');
        if (!$maxExecutionTime) {
            $maxExecutionTime = 60;
        }

        //NOTE: 10 seconds to complete
        $options['breakTime'] = $maxExecutionTime + $startTime - 10;
        $limit = 100;

        $data = [
            'synced' => 0,
            'queued' => 0,
            'failed' => 0,
            'aborted' => false,
            'completed' => false,
            'ratelimit' => null,
        ];

        if ($stage == 1) {
            $images = $this->getNotCachedPathes($limit);
        } else {
            $images = $this->getCachedPathes([self::IS_NEW, self::IS_PROCESSING], $limit);
        }

        $imagesCount = count($images);
        if ($imagesCount == 0) {
            $data['completed'] = true;
            return $data;
        }

        if ($this->isLocalHost) {
            $result = $this->syncWithUploading($images, $options);
        } else {
            $result = $this->syncWithFetching($images, $options);
        }

        $data = array_merge($data, $result);

        if (!$data['aborted'] && ($imagesCount < $limit)) {
            $data['completed'] = true;
        }

        return $data;
    }

    /**
     * Synchronize images using uploading method
     *
     * @param array $images
     * @param array $options
     * @return array
     */
    protected function syncWithUploading($images, $options)
    {
        $synced = 0;
        $failed = 0;
        $aborted = false;
        $rateLimit = null;

        foreach ($images as $imagePath) {
            $imagePath = '/' . ltrim($imagePath, '\\/');
            $relPath = $this->productMediaRelPath . $imagePath;
            $absPath = $this->mediaDirAbsPath . $relPath;

            if (is_file($absPath)) {
                try {
                    $result = $this->sirvClient->uploadFile($this->imageFolder . $relPath, $absPath);
                    if (!$result) {
                        $errorMessage = $this->sirvClient->getErrorMsg();
                        if (empty($errorMessage)) {
                            $errorMessage = 'Unknown error.';
                        }
                        $this->logger->info(sprintf('"%s" was not uploaded. %s', $absPath, $errorMessage));
                        $expireTime = $this->sirvClient->getRateLimitExpireTime('POST', 'v2/files/upload');
                        if ($expireTime) {
                            $rateLimit = [
                                'expireTime' => $expireTime,
                                'currentTime' => time(),
                                'message' => $errorMessage,
                            ];
                            $aborted = true;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    $this->logger->critical('Exception on API upload:', ['exception' => $e]);
                    $result = false;
                }
            } else {
                $errorMessage = 'The file does not exist or is not readable.';
                $this->logger->info(sprintf(
                    '"%s" was not uploaded. %s',
                    $absPath,
                    $errorMessage
                ));
                $result = false;
            }

            if ($this->isCached($imagePath)) {
                $this->removeCacheData($imagePath);
            }

            if ($result) {
                $modificationTime = filemtime($absPath);
                $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_SYNCED, $modificationTime);
                $synced++;

                if ($options['doClean']) {
                    $this->cleanMagentoImagesCache($imagePath);
                }
            } else {
                $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_FAILED, 0);
                $this->updateMessageData($relPath, $errorMessage);
                $failed++;
            }

            if ($options['breakTime'] - time() <= 0) {
                $aborted = true;
                break;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'aborted' => $aborted,
            'ratelimit' => $rateLimit,
        ];
    }

    /**
     * Synchronize images using fetching method
     *
     * @param array $images
     * @param array $options
     * @return array
     */
    protected function syncWithFetching($images, $options)
    {
        $synced = 0;
        $failed = 0;
        $aborted = false;
        $rateLimit = null;
        $error = false;

        //NOTE: less than or equal to 20 items
        $chunks = array_chunk($images, 20);
        foreach ($chunks as $chunk) {
            $fetchData = [];
            foreach ($chunk as $imagePath) {
                $imagePath = '/' . ltrim($imagePath, '\\/');
                $relPath = $this->productMediaRelPath . $imagePath;
                $absPath = $this->mediaDirAbsPath . $relPath;
                if (is_file($absPath)) {
                    $fetchData[] = [
                        //NOTE: source link
                        'url' => $this->mediaBaseUrl . str_replace('%2F', '/', rawurlencode($relPath)),
                        //NOTE: destination path
                        'filename' => $this->imageFolder . $relPath,
                        //NOTE: wait flag
                        'wait' => true
                    ];
                } else {
                    $errorMessage = 'The file does not exist or is not readable.';
                    $this->logger->info(sprintf(
                        '"%s" was not fetched. %s',
                        $absPath,
                        $errorMessage
                    ));
                    $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_FAILED, 0);
                    $this->updateMessageData($relPath, $errorMessage);
                    $failed++;
                }
            }

            if (empty($fetchData)) {
                continue;
            }

            if (!(empty($options['httpAuthUser']) || empty($options['httpAuthPass']))) {
                foreach ($fetchData as &$imageData) {
                    $imageData['auth'] = [
                        'username' => $options['httpAuthUser'],
                        'password' => $options['httpAuthPass']
                    ];
                }
            }

            try {
                $result = $this->sirvClient->fetchImages($fetchData);
                if (!$result) {
                    $expireTime = $this->sirvClient->getRateLimitExpireTime('POST', 'v2/files/fetch');
                    if ($expireTime) {
                        $rateLimit = [
                            'expireTime' => $expireTime,
                            'currentTime' => time(),
                            'message' => $this->sirvClient->getErrorMsg(),
                        ];
                    } else {
                        $error = $this->sirvClient->getErrorMsg();
                    }
                    $aborted = true;
                    break;
                }
            } catch (\Exception $e) {
                $error = 'Exception on fetching images with Sirv API!';
                $this->logger->critical($error, ['exception' => $e]);
                $aborted = true;
                break;
            }

            if (!is_array($result)) {
                $error = 'Unexpected result on fetching images with Sirv API!';
                $this->logger->critical($error);
                $aborted = true;
                break;
            }

            $totalCounter = 0;
            $timeoutCounter = 0;
            $exampleLink = '';
            foreach ($result as $fileData) {
                $totalCounter++;
                $relPath = preg_replace('#^' . preg_quote($this->imageFolder, '#') . '#', '', $fileData->filename);
                $absPath = $this->mediaDirAbsPath . $relPath;

                $attempt = isset($fileData->attempts) && is_array($fileData->attempts) ? end($fileData->attempts) : false;
                $errorMessage = isset($fileData->error) ? $fileData->error : 'Unknown error.';
                if ($attempt) {
                    if (isset($attempt->error)) {
                        if (isset($attempt->error->httpCode)) {
                            if ((int)$attempt->error->httpCode == 429) {
                                $rateLimit = [
                                    'expireTime' => isset($attempt->error->counter, $attempt->error->counter->reset) ? (int)$attempt->error->counter->reset : 0,
                                    'currentTime' => time(),
                                    'message' => isset($attempt->error->message) ? $attempt->error->message : 'Api rate limit error!',
                                ];
                                continue;
                            }
                        }
                        if (isset($attempt->error->message)) {
                            $errorMessage = $attempt->error->message;
                        }
                        $errorMessage = preg_replace('#(?:\s*+\.)?\s*+$#', '.', $errorMessage);
                        if (strpos($errorMessage, 'Timeout') !== false) {
                            $timeoutCounter++;
                            if (empty($exampleLink)) {
                                $exampleLink = $attempt->url;
                            }
                        }
                    }
                    /*
                    if (isset($attempt->statusCode)) {
                        if ((int)$attempt->statusCode == 404) {
                            $errorMessage = 'The file is not found on the server.';
                        }
                    }
                    */
                }

                if ($fileData->success) {
                    $modificationTime = filemtime($absPath);
                    $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_SYNCED, $modificationTime);
                    $synced++;

                    if ($options['doClean']) {
                        $this->cleanMagentoImagesCache(
                            preg_replace('#^' . preg_quote($this->productMediaRelPath, '#') . '#', '', $relPath)
                        );
                    }
                } else {
                    if ($fileData->error) {
                        if (preg_match('#\brate limit exceeded\b#', $fileData->error)) {
                            if (!$rateLimit) {
                                $currentTime = time();
                                $limits = $this->sirvClient->getAPILimits();
                                $limitData = $limits->{'fetch:file'};
                                $remaining = (int)$limitData->remaining;
                                if ($remaining <= 0) {
                                    $expireTime = (int)$limitData->reset;
                                    if ($expireTime >= $currentTime) {
                                        $errorMessage = 'Rate limit exceeded. Too many requests. Retry after ' .
                                            date('Y-m-d\TH:i:s.v\Z (e)', $expireTime) . '. ' .
                                            'Please visit https://sirv.com/help/resources/api/#API_limits';
                                        $rateLimit = [
                                            'expireTime' => $expireTime,
                                            'currentTime' => $currentTime,
                                            'message' => $errorMessage,
                                        ];
                                    }
                                }
                            }
                            continue;
                        }
                    }
                    $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_FAILED, 0);
                    $this->updateMessageData($relPath, $errorMessage);
                    $failed++;
                    if ($attempt) {
                        $this->logger->info(sprintf(
                            '"%s" was not fetched. %s',
                            $attempt->url,
                            $errorMessage
                        ));
                    }
                }
            }

            if ($totalCounter && ($totalCounter == $timeoutCounter)) {
                $error = 'Some files could not be fetched because the remote server did not release them.' .
                    ' Example:<br/> <a target="_blank" href="' . $exampleLink . '">' . $exampleLink . '</a><br/>' .
                    'Please check if your server is rate-limiting Sirv and if so, add a firewall rule to permit the Sirv user agent named "Sirv Image Service". <a target="_blank" href="https://sirv.com/help/articles/fetch-images/#possible-causes-of-errors">Learn more</a>.';
                $aborted = true;
                break;
            }

            if ($rateLimit) {
                $aborted = true;
                break;
            }

            if ($options['breakTime'] - time() <= 0) {
                $aborted = true;
                break;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'aborted' => $aborted,
            'ratelimit' => $rateLimit,
            'error' => $error,
        ];
    }

    /**
     * Method to clean Magento images cache
     *
     * @param string $imagePath
     * @return bool
     */
    protected function cleanMagentoImagesCache($imagePath)
    {
        $pattern = $this->productMediaRelPath . '/cache/*' . $imagePath;
        $foundFiles = $this->mediaDirectory->search($pattern);
        foreach ($foundFiles as $foundFile) {
            $this->mediaDirectory->delete($foundFile);
        }
    }

    /**
     * Method to flush cache
     *
     * @param string $flushMethod
     * @return bool
     */
    public function flushCache($flushMethod)
    {
        $resource = $this->cacheModel->getResource();
        $result = false;

        switch ($flushMethod) {
            case 'failed':
                //NOTE: clear cached data with failed status from DB table
                $resource->deleteByStatus(self::IS_FAILED);
                $messagesResource = $this->messagesModelFactory->create()->getResource();
                $messagesResource->deleteAll();
                $result = true;
                break;
            case 'queued':
                $resource->deleteByStatus(self::IS_NEW);
                $resource->deleteByStatus(self::IS_PROCESSING);
                $result = true;
                break;
            case 'synced':
                $resource->deleteByStatus(self::IS_SYNCED);
                $result = true;
                break;
            case 'all':
                //NOTE: clear DB cache
                $resource->deleteAll();
                $messagesResource = $this->messagesModelFactory->create()->getResource();
                $messagesResource->deleteAll();
                $result = true;
                break;
            case 'master':
                //NOTE: delete images from Sirv and clear DB cache
                $result = true;
                break;
        }

        return $result;
    }

    /**
     * Get media base URL
     *
     * @return string
     */
    public function getMediaBaseUrl()
    {
        return $this->mediaBaseUrl;
    }

    /**
     * Get fetch file limit
     *
     * @return integer
     */
    public function getFetchFileLimit()
    {
        $data = $this->dataHelper->getAccountUsageData();
        return (int)$data['fetch_file_limit'];
    }
}
