/**
 * Slides order widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'jquery/ui'
], function ($, mageTemplate) {
    'use strict';

    $.widget('sirv.slidesOrder', {

        options: {
            itemSelector: '[data-role=item]',
            inputSelector: '[name=mt-config\\[slides_order\\]]',
            itemTemplate: '<div class="slides_order_item" data-role="item" ' +
                'data-item-type="<%- type %>" data-position="<%- position %>">' +
                '<div class="slides_order_item_wrapper">' +
                '<span class="slides_order_item_label"><%- label %></span>' +
                '<div class="slides_order_actions">' +
                '<div class="slides_order_action slides_order_action_draggable_handle" title="Drag and drop to sort"></div>' +
                '<div class="slides_order_action slides_order_action_remove" data-role="delete-button" title="Delete"></div>' +
                '</div></div></div>'
        },

        isWidgetDisabled: false,
        itemTmpl: null,

        /** @inheritdoc */
        _create: function () {
            this.isWidgetDisabled = this.element.hasClass('disabled');
            this.itemTmpl = mageTemplate(this.options.itemTemplate);
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            this._on({
                addItem: '_addItem',
                removeItem: '_removeItem',
                resortItems: '_resortItems',
                closeItemsMenu: '_closeItemsMenu',

                /**
                 * @param {jQuery.Event} event
                 */
                'click [data-role=delete-button]': function (event) {
                    var $item;
                    event.preventDefault();
                    if (event.which == 1 ) {
                        $item = $(event.currentTarget).closest(this.options.itemSelector);
                        this.element.trigger('removeItem', $item);
                    }
                    return false;
                },

                /**
                 * @param {jQuery.Event} event
                 */
                'click [data-role=add-button]': function (event) {
                    var $item;
                    event.stopPropagation();
                    if (event.which == 1 ) {
                        $item = $(event.currentTarget).closest('.slides_order_item');
                        $item.find('.items-menu').addClass('open');
                    }
                    return false;
                },

                /**
                 * @param {jQuery.Event} event
                 */
                'click [data-role=menu-item]': function (event) {
                    var $menuItem;
                    event.stopPropagation();
                    if (event.which == 1 ) {
                        $menuItem = $(event.currentTarget).closest('.slides_order_menu_item');
                        this.element.trigger('addItem', {
                            type: $menuItem.attr('data-item-type'),
                            label: $menuItem.attr('data-item-label')
                        });
                        this.element.trigger('closeItemsMenu');
                    }
                    return false;
                }
            });

            this.element.sortable({
                distance: 8,
                items: this.options.itemSelector,
                tolerance: 'pointer',
                cancel: 'input, button, .slides_order_action_remove, .item_disabled',
                update: $.proxy(function () {
                    this.element.trigger('resortItems');
                }, this)
            });

            var el = this.element;
            this.element.closest('body').on({
                /**
                 * @param {jQuery.Event} event
                 */
                'click': function (event) {
                    el.trigger('closeItemsMenu');
                }
            });

            if (this.isWidgetDisabled) {
                this._disableWidget(true);
            }
            var self = this;
            $('#mt-slides_order-switcher').on({
                /**
                 * @param {jQuery.Event} event
                 */
                'change': function (event) {
                    self._disableWidget(
                        !$(event.currentTarget).prop('checked')
                    );
                }
            });
        },

        /**
         * Add item
         * @param {jQuery.Event} event
         * @param {Object} itemData
         * @private
         */
        _addItem: function (event, itemData) {
            var count = this.element.find(this.options.itemSelector).not('.removed').length,
                item;

            item = this.itemTmpl({
                type: itemData.type,
                label: itemData.label,
                position: count
            });
            item = $(item);

            if (count === 0) {
                item.prependTo(this.element);
            } else {
                item.insertAfter(this.element.find(this.options.itemSelector + ':last'));
            }

            this._updateValue();
        },

        /**
         * Remove item
         * @param {jQuery.Event} event
         * @param {Object} item
         * @private
         */
        _removeItem: function (event, item) {
            $(item).addClass('removed').hide();
            this._updateValue();
        },

        /**
         * Resort items
         * @param {jQuery.Event} event
         * @private
         */
        _resortItems: function (event) {
            this.element.find(this.options.itemSelector).not('.removed').each($.proxy(function (index, item) {
                var pos = $(item).attr('data-position');
                if (pos != index) {
                    $(item).attr('data-position', index);
                }
            }, this));

            this._updateValue();
        },

        /**
         * Close items menu
         * @param {jQuery.Event} event
         * @private
         */
        _closeItemsMenu: function (event) {
            this.element.find('.items-menu').removeClass('open');
        },

        /**
         * Update value
         * @private
         */
        _updateValue: function () {
            var value = [], input;
            this.element.find(this.options.itemSelector).not('.removed').each($.proxy(function (index, item) {
                value.push($(item).attr('data-item-type'));
            }, this));
            value = value.join(',');
            input = this.element.parent().find(this.options.inputSelector);
            input.val(value);
        },

        /**
         * Make disabled
         * @param {Boolean} disable
         * @private
         */
        _disableWidget: function (disable) {
            if (disable) {
                this.element.addClass('disabled').find('.slides_order_item').addClass('item_disabled').on({
                    /**
                     * @param {jQuery.Event} event
                     */
                    'click.widgetDisabled': function (event) {
                        event.stopPropagation();
                        return false;
                    }
                });
            } else {
                this.element.removeClass('disabled')
                    .find('.slides_order_item').removeClass('item_disabled').off('click.widgetDisabled');
            }
        }
    });

    return $.sirv.slidesOrder;
});
