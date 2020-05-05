<?php

namespace MagicToolbox\Sirv\Model\Image;

/**
 * Image adapter factory
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class AdapterFactory extends \Magento\Framework\Image\AdapterFactory
{
    /**
     * Sirv adapter map
     *
     * @var array
     */
    protected $sirvAdapterMap = [
        'SIRV' => [
            'title' => 'Sirv',
            'class' => 'MagicToolbox\Sirv\Model\Image\Adapter\Sirv'
        ]
    ];

    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Image\Adapter\ConfigInterface $config
     * @param array $adapterMap
     * @return void
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Image\Adapter\ConfigInterface $config,
        array $adapterMap = []
    ) {
        parent::__construct($objectManager, $config, $adapterMap);
        $this->adapterMap = array_merge($this->adapterMap, $this->sirvAdapterMap);
    }
}
