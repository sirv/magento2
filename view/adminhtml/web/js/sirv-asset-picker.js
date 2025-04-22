/**
 * Sirv asset picker widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2024 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/prompt'
], function ($, uiAlert, uiPrompt) {
    'use strict';

    $.widget('sirv.sirvAssetPicker', {

        options: {
            sirvBaseUrl: '',
            folderContentUrl: '',
            modelIconUrl: ''
        },

        contentsNodeSelector: '.sirv-asset-picker-content',
        contentsNode: null,

        /** @inheritdoc */
        _create: function () {

            this.contentsNode = this.element;

            this.contents = {};
            this.searchedContents = {};

            this.baseURL = this.options.sirvBaseUrl;
            this.folderContentURL = this.options.folderContentUrl;

            this.currentPath = '';

            this.treeContainer = this.contentsNode.find('.sv-tree');
            this.pageContainer = this.contentsNode.find('.sv-page');
            this.listContainer = this.contentsNode.find('.sv-content');

            this.allowedAssets = {
                'image': ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'svg'],
                'video': ['mpg', 'mpeg', 'm4v', 'mp4', 'avi', 'mov', 'ogv'],
                'model': ['usdz', 'glb', 'dwg'],
                'spin': ['spin']
            };
            this.allowedAssetsPattern = null;
            var patterns = {};
            for (var assetType in this.allowedAssets) {
                patterns[assetType] = this.allowedAssets[assetType].join('|');
            }
            this.allowedAssetsPattern = new RegExp(
                '^(' + Object.keys(patterns).map((key) => (patterns[key])).join('|') + ')$',
                'i'
            );

            this.imagePattern = new RegExp('^(' + patterns['image'] + ')$', 'i');
            this.videoPattern = new RegExp('^(' + patterns['video'] + ')$', 'i');
            this.modelPattern = new RegExp('^(' + patterns['model'] + ')$', 'i');
            this.spinPattern = new RegExp('^(' + patterns['spin'] + ')$', 'i');

            this.fullImageSize = 1000;

            this.initTreeLinks(this.treeContainer.find('a'));

            this.initViewScroll();

            this.initSearch();

            var self = this,
                button = this.contentsNode.find('.sv-add-button'),
                gallery = $('.sirv-manually-added-assets'),
                ul,
                items;

            button.on('click', function(e) {
                ul = self.listContainer.find('ul');
                items = ul.find('li.selected a[data-preview]');

                if (!items.length) {
                    alert('No items selected!');
                    return;
                }

                items.each(function() {
                    const itemInfo = JSON.parse(this.dataset.itemInfo || '{}');
                    itemInfo.file = itemInfo.path;
                    itemInfo.type = itemInfo.assetType;

                    if (itemInfo.type == 'video') {
                        itemInfo.url = itemInfo.url + '?thumbnail=' + (itemInfo.width || 150);
                    }
                    if (itemInfo.type == 'spin') {
                        itemInfo.url = itemInfo.url + '?thumb=spin&image.frames=1';
                    }
                    if (itemInfo.type == 'model') {
                        itemInfo.url = self.options.modelIconUrl;
                    }

                    gallery.trigger('addItem', itemInfo);
                    $(this).closest('li').removeClass('selected');
                });

                gallery.trigger('closeModalWindow');
            });

            button = this.contentsNode.find('.sv-new-folder-button');
            button.on('click', function(e) {
                uiPrompt({
                    /*
                    title: $.mage.__('Create new folder'),
                    */
                    content: $.mage.__('New folder name:'),
                    actions: {
                        /**
                         * @param {String} name
                         * @this {actions}
                         */
                        confirm: function (name) {
                            var uiAlertFn = function (message) {
                                setTimeout(function () {
                                    uiAlert({content: $.mage.__(message)});
                                }, 500);
                            };
                            if (typeof name == 'string') {
                                if (name == '') {
                                    uiAlertFn('Folder name can\'t be emoty!');
                                } else {
                                    var regExp = /\/|\\/i;
                                    var found = name.match(regExp);
                                    if (found) {
                                        uiAlertFn('Folder name cannot contain \'/\' or \'\\\'.');
                                    } else {
                                        self.createFolder(name);
                                    }
                                }
                            }
                        }
                    }
                });
            });

            this.treeContainer.find('a').trigger('click');
            window.sirvAssetPickerPath = '';
        },

        initViewScroll() {
            var self = this;
            this.listContainer.scroll(function() {
                var scrollPos = self.listContainer.scrollTop(),
                    bar = self.listContainer.find('.sv-content-bar'),
                    height = bar.height();

                if (scrollPos > height) {
                    bar.css('top', scrollPos + 'px');
                    self.listContainer.css('padding-top', height + 'px');
                    self.listContainer.addClass('fixed-bar');
                } else {
                    bar.css('top', '0');
                    self.listContainer.css('padding-top', '0');
                    self.listContainer.removeClass('fixed-bar');
                }

                bar = self.listContainer.find('.sv-bottom-bar');
                if (scrollPos > 0) {
                    bar.css('bottom', '-' + scrollPos + 'px');
                } else {
                    bar.css('bottom', '0');
                }
            });
        },

        initSearch() {
            var self = this,
                input = this.contentsNode.find('.sv-search-input'),
                button = this.contentsNode.find('.sv-search-button');
            input.on('keydown', function(e) {
                if (e.which == 13 ) {
                    self.updateSearchView(input.val());
                    e.preventDefault();
                }
            });
            button.on('click', function(e) {
                self.updateSearchView(input.val());
            });
        },

        initTreeLinks(elm) {
            var self = this;
            elm.unbind('click');
            elm.on('click', function(e) {
                self.treeLinkClick(e);
            });
        },

        treeLinkClick(e) {
            var node = e.target,
                path;

            if (node.tagName.toLowerCase() != 'a') {
                node = node.parentNode;
                if (node.tagName.toLowerCase() != 'a') {
                    e.preventDefault();
                    return;
                }
            }

            path = $(node).data('path');
            this.updateView(path);
            e.preventDefault();
        },

        updateViewForce(path) {
            if (typeof path == 'undefined') {
                path = this.currentPath;
            }

            if (typeof this.contents[path] == 'undefined') {
            } else {
                delete this.contents[path];
            }

            this.updateView(path);
        },

        updateView(path) {
            if (typeof this.contents[path] == 'undefined') {
                this.lockPage();
                this.contents[path] = this.getPathContents(path);
            } else {
                this.currentPath = path;
                this.updateTree(path);
                this.updateBreadcrumbs(path);
                this.updateFilesList(path);
                window.sirvAssetPickerPath = path;
                this.unlockPage();
            }
        },

        updateSearchView(search) {
            if (typeof this.searchedContents[search] == 'undefined') {
                this.lockPage();
                this.searchedContents[search] = this.getSearchedContents(search);
            } else {
                this.currentPath = '';
                this.updateTree('');
                var ol = this.listContainer.find('.sv-breadcrumbs');
                ol.html('<li><a data-path="" href="#"></a></li>');
                ol.append('<li>&nbsp;&nbsp;Result for \'' + search + '\'</li>');
                this.initTreeLinks(ol.find('a[data-path]'));
                this.updateFilesList('', search);
                this.unlockPage();
            }
        },

        lockPage() {
            this.pageContainer.addClass('loading');
        },

        unlockPage() {
            this.pageContainer.removeClass('loading');
        },

        updateTree(path) {
            var ul = this.treeContainer.find('ul[data-path="' + path + '"]'),
                a, list, ul, folders;

            if (ul.length == 0) {
                if (typeof this.contents[path] == 'undefined') {
                    this.contents[path] = this.getPathContents(path);
                    window.sirvAssetPickerPath = path;
                } else {
                    a = this.treeContainer.find('a[data-path="' + path + '"]');
                    this.highlightTreeItem(a);
                    list = this.contents[path];
                    if (Object.keys(list).length) {
                        a.after('<ul data-path="' + path + '"></ul>');
                        ul = this.treeContainer.find('ul[data-path="' + path + '"]');
                        folders = 0;
                        list.forEach(function(item) {
                            if (item.type == 'folder') {
                                folders++;
                                ul.append('<li><a href="#" data-path="' + item.path + '">' + item.name + '</a></li>');
                            }
                        });

                        if (folders == 0) {
                            ul.remove();
                        } else {
                            this.initTreeLinks(ul.find('a'));
                        }
                    }
                }
            } else {
                a = this.treeContainer.find('a[data-path="' + path + '"]');
                this.highlightTreeItem(a);
            }
        },

        highlightTreeItem(a) {
            this.treeContainer.find('a').removeClass('active');
            a.addClass('active');
        },

        updateBreadcrumbs(path) {
            var self = this,
                ol = this.listContainer.find('.sv-breadcrumbs'),
                paths = path.split('/'),
                prevPath = '',
                bPath = '',
                a = null,
                title = '';

            ol.html('<li><a data-path="" href="#"></a></li>');

            if (Object.keys(paths).length) {
                paths.forEach(function(item) {
                    if (item != '') {
                        bPath = prevPath + '/' + decodeURI(item);

                        a = self.treeContainer.find('a[data-path="' + bPath + '"]');
                        title = a.text();

                        ol.append('<li><a data-path="' + bPath + '" href="#">' + title + '</a></li>');
                        prevPath = bPath;
                    }
                });
            }

            this.initTreeLinks(ol.find('a[data-path]'));
        },

        updateFilesList(path, search) {
            var self = this,
                count = 0,
                itemHTML,
                list,
                ul;

            list = search ? this.searchedContents[search] : this.contents[path];

            this.contentsNode.find('.sv-add-button').attr('disabled', 'disabled').addClass('disabled');
            this.listContainer.find('ul').replaceWith('<ul></ul>');
            ul = this.listContainer.find('ul');

            list.forEach(function(item) {
                itemHTML = self.getItemHTML(item, count);
                if (itemHTML != '') {
                    count++;
                }
                ul.append(itemHTML);
            });

            if (count) {
                this.listContainer.find('.sv-messages').addClass('hidden-element');
            } else {
                this.listContainer.find('.sv-messages').removeClass('hidden-element');
            }

            this.initTreeLinks(ul.find('a[data-path]'));
            this.initAssetClick(ul.find('a[data-preview]'));
        },

        initAssetClick(elm) {
            var self = this,
                ul = this.listContainer.find('ul'),
                li = ul.find('a[data-preview]').first().closest('li');

            elm.on('click', function(e) {
                if (!(e.ctrlKey || e.shiftKey)) {
                    ul.find('a[data-preview]').closest('li').removeClass('selected');
                    li = $(this).closest('li');
                    li.addClass('selected');
                } else if (e.ctrlKey && !e.shiftKey) {
                    li = $(this).closest('li');
                    li.toggleClass('selected');
                } else if (!e.ctrlKey && e.shiftKey) {
                    var first, last, i;
                    ul.find('a[data-preview]').closest('li').removeClass('selected');
                    first = Number(li.attr('data-order'));
                    last = Number($(this).closest('li').attr('data-order'));
                    if (first <= last) {
                        i = first;
                    } else {
                        i = last;
                        last = first;
                    }
                    while (i <= last) {
                        ul.find('li[data-order=' + i + ']').addClass('selected');
                        i++;
                    }
                }
                self.contentsNode.find('.sv-add-button').attr('disabled', false).removeClass('disabled');
                e.preventDefault();
                return false;
            });

            elm.on('dblclick', function(e) {
                const itemInfo = JSON.parse(this.dataset.itemInfo || '{}');
                var gallery = document.querySelector('.sirv-manually-added-assets');

                itemInfo.file = itemInfo.path;
                itemInfo.type = itemInfo.assetType;

                if (itemInfo.type == 'video') {
                    itemInfo.url = itemInfo.url + '?thumbnail=' + (itemInfo.width || 150);
                }
                if (itemInfo.type == 'spin') {
                    itemInfo.url = itemInfo.url + '?thumb=spin&image.frames=1';
                }
                if (itemInfo.type == 'model') {
                    itemInfo.url = self.options.modelIconUrl;
                }

                gallery = jQuery(gallery);
                gallery.trigger('closeModalWindow');
                gallery.trigger('addItem', itemInfo);

                e.preventDefault();
            });
        },

        getItemHTML(item, index) {
            var dataPath = '',
                fileExtension = '',
                dataExtension = '',
                isImage = false,
                isVideo = false,
                isModel = false,
                isSpin = false,
                spanContent = '',
                dataPreview = '',
                dataVideo = '',
                href = item.url,
                dataItemInfo = '';

            if (item.type == 'folder') {
                dataPath = ' data-path="' + item.path + '"';
            } else {
                fileExtension = item.name.replace(/.*\.(.{1,})$/gm, '$1');
                if (!fileExtension.match(this.allowedAssetsPattern)) {
                    return '';
                }

                dataExtension = ' data-extension="' + fileExtension + '"';
                isImage = fileExtension.match(this.imagePattern);
                isVideo = fileExtension.match(this.videoPattern);
                isModel = fileExtension.match(this.modelPattern);
                isSpin = fileExtension.match(this.spinPattern);

                if (isImage || isVideo) {
                    spanContent = '<img loading="lazy" src="' + item.url + '?thumbnail=100"/>';
                    dataPreview = ' data-preview';
                }
                if (isSpin) {
                    spanContent = '<img loading="lazy" src="' + item.url + '?thumb&w=100"/>';
                    dataPreview = ' data-preview';
                }
                if (isModel) {
                    spanContent = '<img loading="lazy" src="' + this.options.modelIconUrl + '"/>';
                    dataPreview = ' data-preview';
                }

                if (isVideo) {
                    dataVideo = ' data-video';
                }
                if (isImage && this.fullImageSize > 0) {
                    href += '?height=' + this.fullImageSize;
                }

                dataItemInfo = item;
                if (isImage) {
                    dataItemInfo.assetType = 'image';
                }
                if (isVideo) {
                    dataItemInfo.assetType = 'video';
                }
                if (isSpin) {
                    dataItemInfo.assetType = 'spin';
                }
                if (isModel) {
                    dataItemInfo.assetType = 'model';
                }

                dataItemInfo = ' data-item-info=\'' + JSON.stringify(dataItemInfo) + '\'';
            }

            return '<li class="' + item.type + '" data-order="' + index + '" ><a' + dataPreview + dataVideo +
                ' title="' + item.name + '"' + dataExtension + dataPath + ' href="' + href + '"' +
                dataItemInfo + '><span>' + spanContent + '</span><b><i>' + fileExtension + '</i>' +
                item.name + '</b></a></li>';
        },

        getPathContents(path, create) {
            if (typeof create == 'undefined') {
                create = '';
            }
            if (typeof this.contents[path] != 'undefined') {
                window.sirvAssetPickerPath = path;
                return this.contents[path];
            } else {
                var self = this;
                $.ajax({
                    url: this.folderContentURL + '?',
                    data: {
                        'path': path,
                        'create': create
                    },
                    type: 'get',
                    dataType: 'json',
                    'cache': 'false',
                    timeout: 0,
                    error: function (jqXHR, textStatus, errorThrown) {
                        if (console && console.error && errorThrown) console.error(errorThrown);
                        alert(textStatus);
                        location.reload();
                    },
                    success: function(data) {
                        if (data.ajaxExpired) {
                            location.reload();
                            return;
                        }
                        var list = [];
                        if (Object.keys(data).length) {
                            data.forEach(function(item) {
                                item.url = self.baseURL + item.path.replace(/^\//gm,'');
                                list.push(item);
                            });
                        }
                        self.contents[path] = list;
                        self.updateView(path);
                        window.sirvAssetPickerPath = path;
                    }
                });
            }
        },

        getSearchedContents(search) {
            if (typeof this.searchedContents[search] != 'undefined') {
                window.sirvAssetPickerPath = '';
                return this.searchedContents[search];
            } else {
                var self = this;
                $.ajax({
                    url: this.folderContentURL + '?',
                    data: {
                        'search': search
                    },
                    type: 'get',
                    dataType: 'json',
                    cache: 'false',
                    timeout: 0,
                    error: function (jqXHR, textStatus, errorThrown) {
                        if (console && console.error && errorThrown) console.error(errorThrown);
                        alert(textStatus);
                        location.reload();
                    },
                    success: function(data) {
                        if (data.ajaxExpired) {
                            location.reload();
                            return;
                        }
                        var list = [];
                        if (Object.keys(data).length) {
                            data.forEach(function(item) {
                                item.url = self.baseURL + item.path.replace(/^\//gm,'');
                                list.push(item);
                            });
                        }
                        self.searchedContents[search] = list;
                        self.updateSearchView(search);
                        window.sirvAssetPickerPath = '';
                    }
                });
            }
        },

        createFolder(name) {
            var self = this,
                path = window.sirvAssetPickerPath + '/' + name;

            this.lockPage();
            delete this.contents[window.sirvAssetPickerPath];

            var ul = this.treeContainer.find('ul[data-path="' + window.sirvAssetPickerPath + '"]');
            ul.remove();

            this.contents[window.sirvAssetPickerPath] = this.getPathContents(window.sirvAssetPickerPath, name);
        }

    });

    return $.sirv.sirvAssetPicker;
});
