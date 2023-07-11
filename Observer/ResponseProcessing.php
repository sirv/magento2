<?php

namespace Sirv\Magento2\Observer;

/**
 * Observer that processes the responses
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ResponseProcessing implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager = null;

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
     * Is Sirv Media Viewer used
     *
     * @var bool
     */
    protected $isSirvMediaViewerUsed = false;

    /**
     * Sirv JS modules
     *
     * @var string
     */
    protected $sirvJsModules = '';

    /**
     * Add width/height for img tags
     *
     * @var bool
     */
    protected $addImgWidthHeight = false;

    /**
     * Folder name on Sirv
     *
     * @var string
     */
    protected $imageFolder = '';

    /**
     * Use placeholders
     *
     * @var bool
     */
    protected $usePlaceholders = false;

    /**
     * Sirv options
     *
     * @var array
     */
    protected $dataOptions = [];

    /**
     * Constructor
     *
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Helper\Sync $syncHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Helper\Sync $syncHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();
        if ($this->isSirvEnabled) {
            $this->dataHelper = $dataHelper;
            $this->syncHelper = $syncHelper;
            $this->storeManager = $storeManager;

            $this->sirvHost = $dataHelper->getSirvDomain(false);
            $this->urlPrefix = $dataHelper->getConfig('url_prefix');
            $this->urlPrefix = is_string($this->urlPrefix) ? trim($this->urlPrefix) : '';

            if ($this->urlPrefix) {
                //$this->urlPrefix = preg_replace('#^(?:https?\:)?//#', '', $this->urlPrefix);
                $autoFetch = $dataHelper->getConfig('auto_fetch');
                $this->isAutoFetchEnabled = $autoFetch == 'custom' || $autoFetch == 'all';
                $this->isLazyLoadEnabled = $dataHelper->getConfig('lazy_load') == 'true';
            }

            $imageScaling = $dataHelper->getConfig('image_scaling');
            in_array($imageScaling, ['contain', 'cover', 'crop']) || ($imageScaling = 'contain');
            $this->dataOptions['fit'] = $imageScaling;

            $this->isSirvMediaViewerUsed = $dataHelper->useSirvMediaViewer();
            $this->sirvJsModules = $dataHelper->getConfig('js_modules');

            $this->addImgWidthHeight = $dataHelper->getConfig('add_img_width_height') == 'true';
            $imageFolder = $dataHelper->getConfig('image_folder');
            if (is_string($imageFolder)) {
                $imageFolder = trim($imageFolder);
                $imageFolder = trim($imageFolder, '\\/');
                if (!empty($imageFolder)) {
                    $this->imageFolder = '/' . $imageFolder;
                }
            }

            $this->usePlaceholders = $dataHelper->getConfig('use_placeholders') == 'true';
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
        if ($this->isSirvEnabled) {
            /** @var \Magento\Framework\App\Response\Http\Interceptor $response */
            $response = $observer->getResponse();

            if ($response) {
                $html = $response->getBody();
                if ($html) {

                    //NOTE: if we do not have a HEAD tag, this may be contents received via AJAX
                    if (preg_match('#<head[^>]++>#', $html)) {
                        $this->addHeadContent($html);
                    }

                    $this->processImageUrls($html);

                    if ($this->isAutoFetchEnabled) {
                        $this->processResourceUrls($html);
                    }

                    if ($this->isLazyLoadEnabled) {
                        $this->processImageTags($html);
                    }

                    if ($this->addImgWidthHeight) {
                        $this->addImageWidthHeight($html);
                    }

                    $response->setBody($html);
                }
            }

            //NOTE: fetch files with API
            $this->syncHelper->doFetch();
        }
    }

    /**
     * Add HEAD content
     *
     * @param string $html
     * @return void
     */
    protected function addHeadContent(&$html)
    {
        $sirvUrl = $this->syncHelper->getBaseUrl();
        $replace = "<link rel=\"preconnect\" href=\"" . $sirvUrl . "\" crossorigin/>\n";
        $replace .= "<link rel=\"dns-prefetch\" href=\"" . $sirvUrl . "\"/>\n";
        $html = preg_replace(
            '#<link[^>]++>#',
            $replace . '$0',
            $html,
            1
        );

        if ($this->isSirvMediaViewerUsed || $this->isLazyLoadEnabled) {
            $replace = "<link rel=\"preconnect\" href=\"https://scripts.sirv.com\" crossorigin/>\n";
            $replace .= "<link rel=\"dns-prefetch\" href=\"https://scripts.sirv.com\"/>\n";
            $html = preg_replace(
                '#<link[^>]++>#',
                $replace . '$0',
                $html,
                1
            );

            $src = 'https://scripts.sirv.com/sirvjs/v3/sirv.js';

            //NOTE: if SMV is off and Lazy is on, use this v2 script:
            /*
            if (!$this->isSirvMediaViewerUsed) {
                $src = 'https://scripts.sirv.com/sirv.nospin.js';
            }
            */

            if (!empty($this->sirvJsModules) && strpos($this->sirvJsModules, 'all') === false) {
                $src = 'https://scripts.sirv.com/sirvjs/v3/sirv.js?modules=' . $this->sirvJsModules;
            }

            $replace = "<script type=\"text/javascript\" src=\"{$src}\"></script>\n";
            $html = preg_replace(
                '#<script[^>]++>#',
                $replace . '$0',
                $html,
                1
            );
        }
    }

    /**
     * Process image URLs for fetching the rest of the images
     *
     * @param string $html
     * @return void
     */
    protected function processImageUrls(&$html)
    {
        $fetchList = $this->syncHelper->getImagesFetchList();
        $fetchList = array_flip($fetchList);
        $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;

        $mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
        //NOTE: /abs_path_to_magento/pub/media

        $store = $this->storeManager->getStore();
        $baseMediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false);
        //NOTE: protocol://host/path_to_magento/pub/media/
        //      protocol://host/path_to_magento/media/

        $baseMediaPath = parse_url($baseMediaUrl, PHP_URL_PATH) ?: '/';
        $baseMediaPath = rtrim($baseMediaPath, '/') . '/';
        //NOTE: /path_to_magento/pub/media/
        //      /path_to_magento/media/

        $baseMediaUrl = preg_replace('#^(?:https?\:)?//#', '', $baseMediaUrl);
        $baseMediaUrl = rtrim($baseMediaUrl, '/') . '/';
        //NOTE: host/path_to_magento/pub/media/
        //      host/path_to_magento/media/

        $baseMediaDir = $store->getBaseMediaDir();
        $baseMediaDir = trim($baseMediaDir, '/') . '/';
        //NOTE: pub/media/
        //      media/

        $baseMediaUrlPattern =
            '(?:' .
            '(?:https?\:)?//' . preg_quote($baseMediaUrl, '#') .
            '|' .
            preg_quote($baseMediaPath, '#') .
            '|' .
            preg_quote($baseMediaDir, '#') .
            ')';
        $searchPattern = '#("|\')' . $baseMediaUrlPattern . '[^"\'\?]*+#';
        $extensionPattern = '#\.(?:jpe?g|png|gif|webp|tiff?|bmp)$#i';

        $matches = [];
        if (preg_match_all($searchPattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                if (!preg_match($extensionPattern, $match[0])) {
                    continue;
                }

                $relPath = preg_replace(
                    '#^(?:"|\')' . $baseMediaUrlPattern . '#',
                    '/',
                    $match[0]
                );
                if (isset($fetchList[$relPath])) {
                    continue;
                }

                if (preg_match('#/cache/#', $relPath)) {
                    //NOTE: to skip cached images
                    continue;
                    $origPath = preg_replace(
                        //NOTE: does not work for cached images that were not placed in "magento" way
                        //'#/cache/[^/]++(/[^/]/[^/]/[^/]++)$#',
                        '#/cache/[^/]++(/.++)$#',
                        '\1',
                        $relPath
                    );
                    if (isset($fetchList[$origPath])) {
                        continue;
                    }
                    $syncStatus = $this->syncHelper->getSyncStatus($origPath);
                    if ($syncStatus == \Sirv\Magento2\Helper\Sync::IS_NEW ||
                        $syncStatus == \Sirv\Magento2\Helper\Sync::IS_PROCESSING
                    ) {
                        continue;
                    }
                }

                $doReplace = false;
                $absPath = $mediaDirAbsPath . $relPath;
                if ($this->syncHelper->isNotExcluded($absPath)) {
                    if ($this->syncHelper->isSynced($relPath)) {
                        $doReplace = true;
                    } elseif (!$this->syncHelper->isCached($relPath)) {
                        if ($this->syncHelper->save($absPath, $pathType)) {
                            $doReplace = true;
                        }
                    }
                }

                if ($doReplace) {
                    $imageUrl = $this->syncHelper->getUrl($relPath);
                    $html = str_replace($match[0], $match[1] . $imageUrl, $html);
                    $fetchList[$relPath] = true;
                }
            }

        }
    }

    /**
     * Process resource URLs for auto fetching
     *
     * @param string $html
     * @return void
     */
    protected function processResourceUrls(&$html)
    {
        $store = $this->storeManager->getStore();

        $baseStaticUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_STATIC, false);
        //NOTE: protocol://host/path_to_magento/static/version{id}/

        $baseStaticUrl = preg_replace('#^(?:https?\:)?//#', '', $baseStaticUrl);
        $baseStaticUrl = rtrim($baseStaticUrl, '/') . '/';
        //NOTE: host/path_to_magento/static/version{id}/

        $baseMediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false);
        //NOTE: protocol://host/path_to_magento/pub/media/
        //      protocol://host/path_to_magento/media/

        $baseMediaPath = parse_url($baseMediaUrl, PHP_URL_PATH) ?: '/';
        $baseMediaPath = rtrim($baseMediaPath, '/') . '/';
        //NOTE: /path_to_magento/pub/media/
        //      /path_to_magento/media/

        $baseMediaUrl = preg_replace('#^(?:https?\:)?//#', '', $baseMediaUrl);
        $baseMediaUrl = rtrim($baseMediaUrl, '/') . '/';
        //NOTE: host/path_to_magento/pub/media/
        //      host/path_to_magento/media/

        $baseMediaDir = $store->getBaseMediaDir();
        $baseMediaDir = trim($baseMediaDir, '/') . '/';
        //NOTE: pub/media/
        //      media/

        $searchPattern =
            '(?:' .
            '(?:https?\:)?//' . preg_quote($baseMediaUrl, '#') .
            '|' .
            preg_quote($baseMediaPath, '#') .
            '|' .
            preg_quote($baseMediaDir, '#') .
            ')';
        $extensionPattern = '#\.(?:css|js|ico|woff2|svg)(\?[^\?]*+)?$#i';

        $matches = [];
        if (preg_match_all('#("|\')' . $searchPattern . '[^"\'\?]*+#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                if (!preg_match($extensionPattern, $match[0])) {
                    continue;
                }

                $relPath = preg_replace(
                    '#^(?:"|\')' . $searchPattern . '#',
                    '',
                    $match[0]
                );

                if (preg_match('#/cache/#', $relPath)) {
                    //NOTE: skip cached images
                    continue;
                }

                if ($this->syncHelper->isNotExcluded($baseMediaPath . $relPath)) {
                    $html = str_replace(
                        $match[0],
                        $match[1] . 'https://' . $this->sirvHost . $baseMediaPath . $relPath,
                        $html
                    );
                }
            }
        }

        $searchPattern = '(?:https?\:)?//' . preg_quote($baseStaticUrl, '#');
        $matches = [];
        if (preg_match_all('#("|\')' . $searchPattern . '[^"\'\?]*+#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                $relPath = preg_replace(
                    '#^(?:"|\')(?:https?\:)?//[^/]++/#',
                    '/',
                    $match[0]
                );

                if ($this->syncHelper->isNotExcluded($relPath)) {
                    $html = str_replace(
                        $match[0],
                        $match[1] . 'https://' . $this->sirvHost . $relPath,
                        $html
                    );
                }
            }
        }
    }

    /**
     * Prepare IMG tags for lazy load
     *
     * @param string $html
     * @return void
     */
    protected function processImageTags(&$html)
    {
        $backupCode = [];
        $regExp = '<(div|a)\b[^>]*?' .
            '(?:' .
                '\bclass\s*+=\s*+\\\\?"' .
                '[^"]*?' .
                '(?<=\s|")(?:Sirv|Magic(?:Zoom(?:Plus)?|Thumb|360|Scroll|Slideshow))(?=\s|\\\\?")' .
                '[^"]*+"' .
                '|' .
                '\bdata-(?:zoom|thumb)-id\s*+=\s*+\\\\?"' .
            ')' .
            '[^>]*+>' .
            '(' .
                '(?:' .
                    '[^<]++' .
                    '|' .
                    '<(?!(?:\\\\?/)?\1\b|!--)' .
                    '|' .
                    '<!--.*?-->' .
                    '|' .
                    '<\1\b[^>]*+>' .
                        '(?2)' .
                    '<\\\\?/\1\s*+>' .
                ')*+' .
            ')' .
            '<\\\\?/\1\s*+>';
        $regExp .= '|<!-- Facebook Pixel Code -->.*?<!-- End Facebook Pixel Code -->';
        $regExp .= '|<img\s[^>]*?\bclass\s*+=\s*+\\\\?"pdp-gallery-placeholder\\\\?"[^>]++>';

        $matches = [];
        if (preg_match_all('#' . $regExp . '#is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $count = 0;
                $html = str_replace($match[0], 'SIRV_PLACEHOLDER_' . $i . '_MATCH', $html, $count);
                if ($count) {
                    $backupCode[$i] = $match[0];
                }
            }
        }

        $matches = [];
        if (preg_match_all('#<img\s[^>]++>#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $tagMatches) {
                $imgTag = $tagMatches[0];
                //NOTE: backslash for escaping quotes (if need it) or empty
                $bs = preg_match('#\\\\(?:"|\')#', $imgTag) ? '\\' : '';

                $srcPattern = '#\ssrc\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $srcMatches = [];
                if (!preg_match($srcPattern, $imgTag, $srcMatches)) {
                    continue;
                }

                if ($this->isExcludedFromLazyLoad($imgTag)) {
                    continue;
                }

                $classPattern = '#\sclass\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $classMatches = [];
                if (preg_match($classPattern, $imgTag, $classMatches)) {
                    if (preg_match('#(?:^|\s)Sirv(?:\s|' . $bs . $bs . '$)#', $classMatches[2])) {
                        continue;
                    } else {
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

                $optionsPattern = '#\sdata-options\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $optionsMatches = [];
                if (preg_match($optionsPattern, $imgTag, $optionsMatches)) {
                    if (!preg_match('#(?:^|\s|;)fit\s*+\:\s*+[a-z]++(?:;|\s|' . $bs . $bs . '$)#', $optionsMatches[2])) {
                        $imgTag = preg_replace(
                            $optionsPattern,
                            ' data-options=' . $bs . $optionsMatches[1] . 'fit:' . $this->dataOptions['fit'] . ';' . $optionsMatches[2] . $optionsMatches[1],
                            $imgTag
                        );
                    }
                } else {
                    $imgTag = preg_replace('#^<img#', '<img data-options=' . $bs . '"fit:' . $this->dataOptions['fit'] . ';' . $bs . '"', $imgTag);
                }

                $srcHost = parse_url($srcMatches[2], PHP_URL_HOST) ?: '';
                if (strpos($srcHost, $this->sirvHost) === false) {
                    $imgTag = preg_replace('#^<img#', '<img data-type=' . $bs . '"static' . $bs . '"', $imgTag);
                } else {
                    if ($this->usePlaceholders) {
                        if (preg_match('#(?:\?|&)q=\d++#', $srcMatches[2])) {
                            $src = preg_replace('#(?<=\?|&)q=\d++#', 'q=30', $srcMatches[2]);
                        } else {
                            $src = strpos($srcMatches[2], '?') === false ? $srcMatches[2] . '?q=30' : $srcMatches[2] . '&q=30';
                        }
                        $imgTag = preg_replace('#^<img#', '<img src=' . $bs . '"' . $src . $bs . '"', $imgTag);
                    }
                }

                $html = str_replace($tagMatches[0], $imgTag, $html);
            }
        }

        if (!empty($backupCode)) {
            foreach ($backupCode as $i => $code) {
                $html = str_replace('SIRV_PLACEHOLDER_' . $i . '_MATCH', $code, $html);
            }
        }
    }

    /**
     * Check the file is excluded from lazy-load
     *
     * @param string $tagHtml
     * @return bool
     */
    public function isExcludedFromLazyLoad($tagHtml)
    {
        static $regExp = null, $list = [];

        if ($regExp === null) {
            $excludedFiles = $this->dataHelper->getConfig('excluded_from_lazy_load') ?: '';
            if (empty($excludedFiles)) {
                $regExp = '';
            } else {
                $excludedFiles = explode("\n", $excludedFiles);
                foreach ($excludedFiles as &$pattern) {
                    $pattern = str_replace(
                        '__ASTERISK__',
                        '.*',
                        preg_quote(
                            str_replace('*', '__ASTERISK__', $pattern),
                            '#'
                        )
                    );
                    if (!preg_match('#^\*#', $pattern)) {
                        $pattern = '\b' . $pattern;
                    }
                    if (!preg_match('#\*$#', $pattern)) {
                        $pattern = $pattern . '\b';
                    }
                }
                $regExp = '#' . implode('|', $excludedFiles) . '#';
            }
        }

        if (empty($regExp)) {
            return false;
        }

        if (!isset($list[$tagHtml])) {
            $list[$tagHtml] = false;
            $matches = [];
            $attrRegExp = '#\b[^\s="\'>/]++\s*+=\s*+("|\')((?:.(?!\1))*+(?:.(?=\1))?)\1#';
            if (preg_match_all($attrRegExp, $tagHtml, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $key => $attrMatches) {
                    $list[$tagHtml] = preg_match($regExp, $attrMatches[2]);
                    if ($list[$tagHtml]) {
                        break;
                    }
                }
            }
        }

        return $list[$tagHtml];
    }

    /**
     * Add width/height attributes for <img> tags
     *
     * @param string $html
     * @return void
     */
    protected function addImageWidthHeight(&$html)
    {
        $backupCode = [];
        $regExp = '<(div|a)\b[^>]*?' .
            '(?:' .
                '\bclass\s*+=\s*+\\\\?"' .
                '[^"]*?' .
                '(?<=\s|")(?:Sirv|Magic(?:Zoom(?:Plus)?|Thumb|360|Scroll|Slideshow))(?=\s|\\\\?")' .
                '[^"]*+"' .
                '|' .
                '\bdata-(?:zoom|thumb)-id\s*+=\s*+\\\\?"' .
            ')' .
            '[^>]*+>' .
            '(' .
                '(?:' .
                    '[^<]++' .
                    '|' .
                    '<(?!(?:\\\\?/)?\1\b|!--)' .
                    '|' .
                    '<!--.*?-->' .
                    '|' .
                    '<\1\b[^>]*+>' .
                        '(?2)' .
                    '<\\\\?/\1\s*+>' .
                ')*+' .
            ')' .
            '<\\\\?/\1\s*+>';
        $regExp .= '|<!-- Facebook Pixel Code -->.*?<!-- End Facebook Pixel Code -->';
        $regExp .= '|<img\s[^>]*?\bclass\s*+=\s*+\\\\?"pdp-gallery-placeholder\\\\?"[^>]++>';

        $matches = [];
        if (preg_match_all('#' . $regExp . '#is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $count = 0;
                $html = str_replace($match[0], 'SIRV_PLACEHOLDER_' . $i . '_MATCH', $html, $count);
                if ($count) {
                    $backupCode[$i] = $match[0];
                }
            }
        }

        $mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
        //NOTE: /abs_path_to_magento/pub/media

        $store = $this->storeManager->getStore();
        $baseMediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false);
        //NOTE: protocol://host/path_to_magento/pub/media/
        //      protocol://host/path_to_magento/media/

        $baseMediaPath = parse_url($baseMediaUrl, PHP_URL_PATH) ?: '/';
        $baseMediaPath = rtrim($baseMediaPath, '/') . '/';
        //NOTE: /path_to_magento/pub/media/
        //      /path_to_magento/media/

        $baseMediaUrl = preg_replace('#^(?:https?\:)?//#', '', $baseMediaUrl);
        $baseMediaUrl = rtrim($baseMediaUrl, '/') . '/';
        //NOTE: host/path_to_magento/pub/media/
        //      host/path_to_magento/media/

        $baseMediaDir = $store->getBaseMediaDir();
        $baseMediaDir = trim($baseMediaDir, '/') . '/';
        //NOTE: pub/media/
        //      media/

        $baseMediaUrlPattern =
            '(?:' .
            '(?:https?\:)?//' . preg_quote($baseMediaUrl, '#') .
            '|' .
            preg_quote($baseMediaPath, '#') .
            '|' .
            preg_quote($baseMediaDir, '#') .
            ')';

        $matches = [];
        if (preg_match_all('#<img\s[^>]++>#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $tagMatches) {
                $imgTag = $tagMatches[0];
                //NOTE: backslash for escaping quotes (if need it) or empty
                $bs = preg_match('#\\\\(?:"|\')#', $imgTag) ? '\\' : '';

                $srcPattern = '#\ssrc\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $srcMatches = [];
                if (!preg_match($srcPattern, $imgTag, $srcMatches)) {
                    $srcPattern = '#\sdata-src\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                    if (!preg_match($srcPattern, $imgTag, $srcMatches)) {
                        continue;
                    }
                }

                /*
                $srcHost = parse_url($srcMatches[2], PHP_URL_HOST) ?: '';
                if (strpos($srcHost, $this->sirvHost) === false) {
                    continue;
                }
                */

                $widthPattern = '#\swidth\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $widthMatches = [];
                if (!preg_match($widthPattern, $imgTag, $widthMatches)) {
                    $width = 0;
                    if (preg_match('#canvas.width=(\d++)#', $srcMatches[2], $widthMatches)) {
                        $width = $widthMatches[1];
                    } elseif (preg_match('#w=(\d++)#', $srcMatches[2], $widthMatches)) {
                        $width = $widthMatches[1];
                    } elseif (preg_match('#^https?://' . $this->sirvHost . $this->imageFolder . '#', $srcMatches[2])) {
                        $filePath = preg_replace('#^https?://' . $this->sirvHost . $this->imageFolder . '/#', '', $srcMatches[2]);
                        $filePath = preg_replace('#\?[^?]++$#', '', $filePath);
                        $filePath = $mediaDirAbsPath . '/'. $filePath;
                        $data = $this->getImageWidthHeight($filePath);
                        $width = empty($data) ? 0 : $data['width'];
                    } elseif (preg_match('#^' . $baseMediaUrlPattern . '#', $srcMatches[2])) {
                        $filePath = preg_replace('#^' . $baseMediaUrlPattern . '#', '', $srcMatches[2]);
                        $filePath = preg_replace('#\?[^?]++$#', '', $filePath);
                        $filePath = $mediaDirAbsPath . '/'. $filePath;
                        $data = $this->getImageWidthHeight($filePath);
                        $width = empty($data) ? 0 : $data['width'];
                    }

                    if ($width) {
                        $imgTag = preg_replace('#^<img#', '<img width=' . $bs . '"' . $width . $bs . '"', $imgTag);
                    }
                }

                $heightPattern = '#\sheight\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $heightMatches = [];
                if (!preg_match($heightPattern, $imgTag, $heightMatches)) {
                    $height = 0;
                    if (preg_match('#canvas.height=(\d++)#', $srcMatches[2], $heightMatches)) {
                        $height = $heightMatches[1];
                    } elseif (preg_match('#h=(\d++)#', $srcMatches[2], $heightMatches)) {
                        $height = $heightMatches[1];
                    } elseif (preg_match('#^https?://' . $this->sirvHost . $this->imageFolder . '#', $srcMatches[2])) {
                        $filePath = preg_replace('#^https?://' . $this->sirvHost . $this->imageFolder . '/#', '', $srcMatches[2]);
                        $filePath = preg_replace('#\?[^?]++$#', '', $filePath);
                        $filePath = $mediaDirAbsPath . '/' . $filePath;
                        $data = $this->getImageWidthHeight($filePath);
                        $height = empty($data) ? 0 : $data['height'];
                    } elseif (preg_match('#^' . $baseMediaUrlPattern . '#', $srcMatches[2])) {
                        $filePath = preg_replace('#^' . $baseMediaUrlPattern . '#', '', $srcMatches[2]);
                        $filePath = preg_replace('#\?[^?]++$#', '', $filePath);
                        $filePath = $mediaDirAbsPath . '/'. $filePath;
                        $data = $this->getImageWidthHeight($filePath);
                        $height = empty($data) ? 0 : $data['height'];
                    }

                    if ($height) {
                        $imgTag = preg_replace('#^<img#', '<img height=' . $bs . '"' . $height . $bs . '"', $imgTag);
                    }
                }

                $html = str_replace($tagMatches[0], $imgTag, $html);
            }
        }

        if (!empty($backupCode)) {
            foreach ($backupCode as $i => $code) {
                $html = str_replace('SIRV_PLACEHOLDER_' . $i . '_MATCH', $code, $html);
            }
        }
    }

    /**
     * Get image width/height
     *
     * @param string $filePath
     * @return array
     */
    protected function getImageWidthHeight($filePath)
    {
        static $data = [];

        if (!isset($data[$filePath])) {
            $data[$filePath] = [];
            if (is_file($filePath)) {
                list($fileWidth, $fileHeight,) = getimagesize($filePath);
                $data[$filePath]['width'] = $fileWidth;
                $data[$filePath]['height'] = $fileHeight;
            }
        }

        return $data[$filePath];
    }
}
