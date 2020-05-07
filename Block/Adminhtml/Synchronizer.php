<?php

namespace MagicToolbox\Sirv\Block\Adminhtml;

/**
 * Synchronizer block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Synchronizer extends \Magento\Backend\Block\Template
{
    /**
     * Path to template file
     *
     * @var string
     */
    protected $_template = 'MagicToolbox_Sirv::synchronizer.phtml';

    /**
     * Sync data
     *
     * @var array
     */
    protected $_syncData = [
        'total' => 0,
        'synced' => 0,
        'queued' => 0,
        'failed' => 0
    ];

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get sync data
     *
     * @return array
     */
    public function getSyncData()
    {
        return $this->_syncData;
    }

    /**
     * Set sync data
     *
     * @param array $data
     * @return void
     */
    public function setSyncData(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (isset($this->_syncData[$key])) {
                $this->_syncData[$key] = $value;
            }
        }
    }

    /**
     * Get AJAX URL
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('sirv/ajax/synchronize');
    }
}
