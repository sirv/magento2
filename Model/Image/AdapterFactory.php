<?php

namespace Sirv\Magento2\Model\Image;

/**
 * Image adapter factory
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
            'class' => 'Sirv\Magento2\Model\Image\Adapter\Sirv'
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
