<?php

namespace MagicToolbox\Sirv\Observer;

/**
 * Observer that processes the responses
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ResponseProcessingObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Whether auto fetch is enabled
     *
     * @var bool
     */
    protected $isAutoFetchEnabled = false;

    /**
     * Whether lazy load is enabled
     *
     * @var bool
     */
    protected $isLazyLoadEnabled = false;

    /**
     * Sirv host
     *
     * @var string
     */
    protected $sirvHost = '';

    /**
     * URL prefix
     *
     * @var string
     */
    protected $urlPrefix = '';

    /**
     * Sync helper
     *
     * @var \MagicToolbox\Sirv\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager = null;

    /**
     * Constructor
     *
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @param \MagicToolbox\Sirv\Helper\Sync $syncHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \MagicToolbox\Sirv\Helper\Data $dataHelper,
        \MagicToolbox\Sirv\Helper\Sync $syncHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        if ($dataHelper->isSirvEnabled()) {
            $bucket = $dataHelper->getConfig('bucket') ?: $dataHelper->getConfig('account');
            $this->sirvHost = $bucket . '.sirv.com';
            $cdn = $dataHelper->getConfig('cdn_url');
            $cdn = is_string($cdn) ? trim($cdn) : '';
            if (!empty($cdn)) {
                $this->sirvHost = $cdn;
            }
            $this->urlPrefix = $dataHelper->getConfig('url_prefix');
            $this->urlPrefix = is_string($this->urlPrefix) ? trim($this->urlPrefix) : '';
            if ($this->urlPrefix) {
                //$this->urlPrefix = preg_replace('#^(?:https?\:)?//#', '', $this->urlPrefix);
                $autoFetch = $dataHelper->getConfig('auto_fetch');
                $this->isAutoFetchEnabled = $autoFetch == 'custom' || $autoFetch == 'all';
                $this->isLazyLoadEnabled = $dataHelper->getConfig('lazy_load') == 'true';
                $this->syncHelper = $syncHelper;
                $this->storeManager = $storeManager;
            }
        }
    }

    /**
     * Execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Framework\App\Response\Http\Interceptor $response */
        $response = $observer->getResponse();

        if ($response) {
            $html = $response->getBody();

            if ($html) {
                $doSetBody = false;

                if ($this->isAutoFetchEnabled) {
                    $doSetBody = $this->prepareUrls($html);
                }

                if ($this->isLazyLoadEnabled) {
                    $doSetBody = $this->prepareImgTags($html) || $doSetBody;
                }

                if ($doSetBody) {
                    $response->setBody($html);
                }
            }
        }
    }

    /**
     * Prepare URLs for auto fetch
     *
     * @param string $html
     * @return bool
     */
    protected function prepareUrls(&$html)
    {
        $fetchList = $this->syncHelper->getImagesFetchList();
        $fetchList = array_flip($fetchList);

        $store = $this->storeManager->getStore();
        $baseMediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false);

        $baseMediaPath = parse_url($baseMediaUrl, PHP_URL_PATH) ?: '/';
        $baseMediaPath = rtrim($baseMediaPath, '/') . '/';

        $baseMediaUrl = preg_replace('#^(?:https?\:)?//#', '', $baseMediaUrl);
        $baseMediaUrl = rtrim($baseMediaUrl, '/') . '/';

        $baseMediaDir = $store->getBaseMediaDir();/* pub/media */
        $baseMediaDir = trim($baseMediaDir, '/') . '/';

        $baseMediaUrlPattern =
            '(?:' .
            '(?:https?\:)?//' . preg_quote($baseMediaUrl, '#') .
            '|' .
            preg_quote($baseMediaPath, '#') .
            '|' .
            preg_quote($baseMediaDir, '#') .
            ')';
        $searchPattern = '#(?:"|\')' . $baseMediaUrlPattern . '[^"\']*+#';
        $skipPattern = '#^(?:"|\')' . $baseMediaUrlPattern . '(?:catalog/product|magic360)/#';
        $extensionPattern = '#\.(?:css|js|jpe?g|png|gif|ico|woff2|svg|webp|tiff?|bmp)(\?[^\?]*+)?$#i';

        $matches = [];
        $replaced = false;
        if (preg_match_all($searchPattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                if (preg_match($skipPattern, $match[0]) || !preg_match($extensionPattern, $match[0])) {
                    continue;
                }

                $mediaFile = preg_replace('#^(?:"|\')' . $baseMediaUrlPattern . '#', '/', $match[0]);
                if (isset($fetchList[$mediaFile])) {
                    continue;
                }

                $replace = preg_replace(
                    '#^("|\')' . $baseMediaUrlPattern . '#',
                    '\1https://' . $this->sirvHost . $baseMediaPath,
                    $match[0]
                );

                $html = str_replace($match[0], $replace, $html);
                $replaced = true;
            }
        }

        return $replaced;
    }

    /**
     * Prepare IMG tags for lazy load
     *
     * @param string $html
     * @return bool
     */
    protected function prepareImgTags(&$html)
    {
        $backupCode = [];
        $regExp = '<(div|a)\b[^>]*?\bclass\s*+=\s*+\\\\?"[^"]*?' .
            '(?<=\s|")(?:Sirv|Magic(?:Zoom(?:Plus)?|Thumb|360|Scroll|Slideshow))(?=\s|\\\\?")' .
            '[^"]*+"[^>]*+>'.
            '('.
                '(?:'.
                    '[^<]++'.
                    '|'.
                    '<(?!(?:\\\\?/)?\1\b|!--)'.
                    '|'.
                    '<!--.*?-->'.
                    '|'.
                    '<\1\b[^>]*+>'.
                        '(?2)'.
                    '<\\\\?/\1\s*+>'.
                ')*+'.
            ')'.
            '<\\\\?/\1\s*+>';

        $matches = [];
        if (preg_match_all('#' . $regExp . '#is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $count = 0;
                $html = str_replace($match[0], 'SIRV_PLACEHOLDER_MATCH_' . $i, $html, $count);
                if ($count) {
                    $backupCode[$i] = $match[0];
                }
            }
        }

        $matches = [];
        $replaced = false;
        if (preg_match_all('#<img\s[^>]++>#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $tagMatches) {
                $imgTag = $tagMatches[0];
                //NOTE: backslash for escaping quotes (if need it) or empty
                $bs = preg_match('#\\\\(?:"|\')#', $imgTag) ? '\\' : '';

                $classPattern = '#\sclass\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $classMatches = [];
                if (preg_match($classPattern, $imgTag, $classMatches)) {
                    if (!preg_match('#(?:^|\s)Sirv(?:\s|' . $bs . $bs . '$)#', $classMatches[2])) {
                        $imgTag = preg_replace(
                            $classPattern,
                            ' class=' . $bs . $classMatches[1] . rtrim($classMatches[2], $bs) . ' Sirv' . $bs . $classMatches[1],
                            $imgTag
                        );
                    }
                } else {
                    $imgTag = preg_replace('#^<img#', '<img class=' . $bs . '"Sirv' . $bs . '"', $imgTag);
                }

                $imgTag = preg_replace('#\ssrc\s*+=\s*+#', ' data-src=', $imgTag);

                $html = str_replace($tagMatches[0], $imgTag, $html);

                $replaced = true;
            }
        }

        if (!empty($backupCode)) {
            foreach ($backupCode as $i => $code) {
                $html = str_replace('SIRV_PLACEHOLDER_MATCH_' . $i, $code, $html);
            }
        }

        return $replaced;
    }
}
