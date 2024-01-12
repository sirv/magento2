/**
 * Asset picker scripts
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

class SirvViewer {

    constructor($baseURL, $folderContentURL) {

        this.$contents = {};
        this.$baseURL = $baseURL;
        this.$folderContentURL = $folderContentURL;

        this.$currentPath = '';

        this.$initPathsProcess = false;
        this.$initPathsPosition = 0;

        this.$treeContainer = $('.sv-tree');
        this.$pageContainer = $('.page');
        this.$listContainer = $('.sv-content');

        this.$allowedAssets = {
            'image': ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'svg'],
            'video': ['mpg', 'mpeg', 'm4v', 'mp4', 'avi', 'mov', 'ogv'],
            'model': ['usdz', 'glb', 'dwg'],
            'spin': ['spin']
        };
        this.$allowedAssetsPattern = null;
        var $patterns = {};
        for (var $assetType in this.$allowedAssets) {
            $patterns[$assetType] = this.$allowedAssets[$assetType].join('|');
        }
        this.$allowedAssetsPattern = new RegExp(
            '^(' + Object.keys($patterns).map((key) => ($patterns[key])).join('|') + ')$',
            'i'
        );

        this.$imagePattern = new RegExp('^(' + $patterns['image'] + ')$', 'i');
        this.$videoPattern = new RegExp('^(' + $patterns['video'] + ')$', 'i');
        this.$modelPattern = new RegExp('^(' + $patterns['model'] + ')$', 'i');
        this.$spinPattern = new RegExp('^(' + $patterns['spin'] + ')$', 'i');

        this.$fullImageSize = 1000;

        this.initViewArea();

        var $currentPath = document.location.hash.replace('#', '');

        this.$initPaths = [];
        if ($currentPath != '') {
            var $paths = $currentPath.split('/'), $prevPath = '';
            for (var $i in $paths) {
                if ($paths[$i] != '') {
                    this.$initPaths.push($prevPath + '/' + $paths[$i]);
                    $prevPath = $prevPath + '/' + $paths[$i];
                }
            }
            this.$initPathsProcess = true;
            this.$initPathsPosition = 0;
        }

        this.initTreeLinks(this.$treeContainer.find('a'));

        var self = this;
        window.addEventListener('hashchange', function(e) {
            self.locationChanged(e)
        });
        window.addEventListener('popstate', function(e) {
            self.popState(e)
        });

        this.initViewScroll();

        this.$treeContainer.find('a').trigger('click');
    }

    initViewArea() {
        this.$listContainer.append('<ol class="breadcrumbs"></ol>').append('<ul></ul>');
        this.$listContainer.append(
            '<div class="sv-messages hidden-element"><div class="empty-folder-message"><h3>No files</h3></div></div>'
        );
    }

    initViewScroll() {
        var self = this;
        this.$listContainer.scroll(function() {
            var $scrollPos = self.$listContainer.scrollTop();
            var $breadcrumbs = self.$listContainer.find('.breadcrumbs'), $height = $breadcrumbs.height();

            if ($scrollPos > $height) {
                $breadcrumbs.css('top', $scrollPos + 'px');
                self.$listContainer.css('padding-top', $height + 'px');
                self.$listContainer.addClass('fixed-breadcrumbs')
            } else {
                $breadcrumbs.css('top', '0');
                self.$listContainer.css('padding-top', '0');
                self.$listContainer.removeClass('fixed-breadcrumbs')
            }
        });
    }

    initTreeLinks($elm) {
        var self = this;
        $elm.unbind('click');
        $elm.on('click', function(e) {
            self.treeLinkClick(e);
        })
    }

    treeLinkClick(e) {
        var $a = $(e.target), $path = $a.data('path');
        this.updateView($path);
        e.preventDefault();
    }

    popState(e) {
        if (document.location.hash.indexOf('expanded') < 0) {
            this.$expandedView.$elements.close.trigger('click');
        }
    }

    locationChanged() {
        var $newHash = document.location.hash.replace('#', '');
        if ($newHash == this.$currentPath) {
            return;
        }
        this.$treeContainer.find('a[data-path="' + $newHash + '"]').trigger('click');
    }

    updateView($path) {
        if (typeof this.$contents[$path] == 'undefined') {
            this.lockPage();
            this.$contents[$path] = this.getPathContents($path);
        } else {
            this.updateLocationHistory($path);
            this.updateTree($path);
            this.updateBreadcrumbs($path);
            this.updateFilesList($path);

            if (this.$initPathsProcess && this.$initPathsPosition < this.$initPaths.length) {
                this.$treeContainer.find('a[data-path="' + decodeURI(this.$initPaths[this.$initPathsPosition]) + '"]').trigger('click');
                this.$initPathsPosition++;
            } else {
                this.unlockPage();
                this.$initPathsProcess = false;
            }
        }
    }

    lockPage() {
        this.$pageContainer.addClass('loading');
    }

    unlockPage() {
        this.$pageContainer.removeClass('loading');
    }

    updateLocationHistory($path) {
        if ($path == '' || $path == this.$currentPath) return;
        var $url = window.location.href.split('#')[0] + '#' + $path;
        window.history.pushState(null, null, $url);
        this.$currentPath = $path;
    }

    updateTree($path) {
        var $ul = this.$treeContainer.find('ul[data-path="' + $path + '"]'),
            $a, $list, $ul, $folders;

        if ($ul.length == 0) {
            if (typeof this.$contents[$path] == 'undefined') {
                this.$contents[$path] = this.getPathContents($path);
            } else {
                $a = this.$treeContainer.find('a[data-path="' + $path + '"]');
                this.highlightTreeItem($a);
                $list = this.$contents[$path];
                if (Object.keys($list).length) {
                    $a.after('<ul data-path="' + $path + '"></ul>');
                    $ul = this.$treeContainer.find('ul[data-path="' + $path + '"]');
                    $folders = 0;
                    $list.forEach(function($item) {
                        if ($item.type == 'folder') {
                            $folders++;
                            $ul.append('<li><a href="#" data-path="' + $item.path + '">' + $item.name + '</a></li>');
                        }
                    });

                    if ($folders == 0) {
                        $ul.remove();
                    } else {
                        this.initTreeLinks($ul.find('a'));
                    }
                }
            }
        } else {
            $a = this.$treeContainer.find('a[data-path="' + $path + '"]');
            this.highlightTreeItem($a);
        }
    }

    highlightTreeItem($a) {
        this.$treeContainer.find('a').removeClass('active');
        $a.addClass('active');
    }

    updateBreadcrumbs($path) {
        var $ol = this.$listContainer.find('.breadcrumbs'),
            $paths = $path.split('/'),
            $prevPath = '',
            $bPath = '',
            $a = null,
            $title = '';

        $ol.html('<li><a data-path="" href="#"></a></li>');

        for (var $i in $paths) {
            if ($paths[$i] != '') {
                $bPath = $prevPath + '/' + decodeURI($paths[$i]);
                $a = this.$treeContainer.find('a[data-path="' + $bPath + '"]');
                $title = $a.text();
                $ol.append('<li><a data-path="' + $bPath + '" href="#">' + $title + '</a></li>')
                $prevPath = $bPath;
            }
        }

        this.initTreeLinks($ol.find('a[data-path]'));

        if ($ol.find('li').length == 1) {
            $ol.hide();
        } else {
            $ol.show();
        }
    }

    updateFilesList($path) {
        var self = this,
            $list = this.$contents[$path],
            count = 0,
            itemHTML,
            $ul;

        this.$listContainer.find('ul').replaceWith('<ul></ul>');
        $ul = this.$listContainer.find('ul');

        $list.forEach(function($item) {
            itemHTML = self.getItemHTML($item);
            if (itemHTML != '') {
                count++;
            }
            $ul.append(itemHTML);
        });

        if (count) {
            this.$listContainer.find('.sv-messages').addClass('hidden-element');
        } else {
            this.$listContainer.find('.sv-messages').removeClass('hidden-element');
        }

        this.initTreeLinks($ul.find('a[data-path]'));
        this.initAssetClick($ul.find('a[data-preview]'));

        $ul.find('a[data-preview]').each(function(){
            $(this).closest('li').attr('data-preview', '');
        })
    }

    initAssetClick($elm) {
        $elm.on('click', function(e) {
            const itemInfo = JSON.parse(this.dataset.itemInfo || '{}');
            window.parent.postMessage({'id': 'picked', 'itemInfo': itemInfo}, '*');
            e.preventDefault();
        })
    }

    getItemHTML($item) {
        var $dataPath = '',
            $fileExtension = '',
            $dataExtension = '',
            $isImage = false,
            $isVideo = false,
            $isModel = false,
            $isSpin = false,
            $spanContent = '',
            $dataPreview = '',
            $dataVideo = '',
            $href = $item.url,
            $dataItemInfo = '';

        if ($item.type == 'folder') {
            $dataPath = ' data-path="' + $item.path + '"';
        } else {
            $fileExtension = $item.name.replace(/.*\.(.{1,})$/gm, '$1');
            if (!$fileExtension.match(this.$allowedAssetsPattern)) {
                return '';
            }

            $dataExtension = ' data-extension="' + $fileExtension + '"';
            $isImage = $fileExtension.match(this.$imagePattern);
            $isVideo = $fileExtension.match(this.$videoPattern);
            $isModel = $fileExtension.match(this.$modelPattern);
            $isSpin = $fileExtension.match(this.$spinPattern);

            if ($isImage || $isVideo) {
                $spanContent = '<img loading="lazy" src="' + $item.url + '?thumbnail=100"/>';
                $dataPreview = ' data-preview';
            }
            if ($isSpin) {
                $spanContent = '<img loading="lazy" src="' + $item.url + '?thumb&w=100"/>';
                $dataPreview = ' data-preview';
            }

            if ($isVideo) {
                $dataVideo = ' data-video';
            }
            if ($isImage && this.$fullImageSize > 0) {
                $href += '?height=' + this.$fullImageSize;
            }

            $dataItemInfo = $item;
            if ($isImage) {
                $dataItemInfo.assetType = 'image';
            }
            if ($isVideo) {
                $dataItemInfo.assetType = 'video';
            }
            if ($isSpin) {
                $dataItemInfo.assetType = 'spin';
            }

            $dataItemInfo = ' data-item-info=\'' + JSON.stringify($dataItemInfo) + '\'';
        }

        return '<li class="' + $item.type + '"><a' + $dataPreview + $dataVideo +
            ' title="' + $item.name + '"' + $dataExtension + $dataPath + ' href="' + $href + '"' +
            $dataItemInfo + '><span>' + $spanContent + '</span><b><i>' + $fileExtension + '</i>' +
            $item.name + '</b></a></li>';
    }

    getPathContents($path) {
        if (typeof this.$contents[$path] != 'undefined') {
            return this.$contents[$path];
        } else {
            var self = this;
            jQuery.ajax({
                url: this.$folderContentURL + '?',
                data: {
                    'path': $path
                },
                dataType: 'json',
                'cache': 'false',
                timeout: 4000,
                error: function (jqXHR, textStatus, errorThrown) {
                    if (console && console.error && errorThrown) console.error(errorThrown);
                    window.parent.postMessage({'id': 'error', 'textStatus': textStatus}, '*');
                },
                success: function($data) {
                    var $list = [];
                    for (var $i in $data) {
                        $data[$i].url = self.$baseURL + $data[$i].path.replace(/^\//gm,'');
                        $list.push($data[$i])
                    }
                    self.$contents[$path] = $list;
                    self.updateView($path);
                }
            });
        }
    }
}
