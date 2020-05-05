<?php

namespace MagicToolbox\Sirv\Block\Adminhtml;

/**
 * Synchronizer block
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
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
