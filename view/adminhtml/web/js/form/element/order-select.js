define([
    'jquery',
    'Magento_Ui/js/form/element/ui-select'
], function ($, UiSelect) {
    'use strict';

    return UiSelect.extend({
        defaults: {
            imports: {},
            exports: {},
            itemsUrl: '',
            customerFieldsMap: {
                customer_id: 'customer_id',
                customer_name: 'customer_name',
                customer_email: 'customer_email',
                store_id: 'store_id'
            }
        },

        onUpdate: function (value) {
            this._super();
            this.populateCustomerFields(value);
            this.loadOrderItems(value);
        },

        populateCustomerFields: function (value) {
            let option = this.getOptionByValue(value),
                source = this.source,
                fieldsetPrefix,
                field;

            if (!option || !source) {
                return;
            }

            fieldsetPrefix = 'data.';

            for (field in this.customerFieldsMap) {
                if (this.customerFieldsMap.hasOwnProperty(field) && option[field] !== undefined) {
                    source.set(fieldsetPrefix + field, option[field]);
                }
            }
        },

        loadOrderItems: function (orderId) {
            let self = this,
                container = $('#rma-order-items-container');

            if (!container.length) {
                return;
            }

            // Clear items data from source when order changes
            if (this.source) {
                this.source.set('data.items', {});
            }

            if (!orderId || !this.itemsUrl) {
                container.html(
                    '<p class="rma-items-message">' +
                    $.mage.__('Select an order to see available items.') +
                    '</p>'
                );
                return;
            }

            container.html(
                '<p class="rma-items-message">' +
                $.mage.__('Loading order items...') +
                '</p>'
            );

            $.ajax({
                url: this.itemsUrl,
                type: 'GET',
                dataType: 'json',
                data: {order_id: orderId},
                success: function (response) {
                    if (response.error) {
                        container.html(
                            '<p class="rma-items-message">' + response.error + '</p>'
                        );
                        return;
                    }

                    if (!response.items || response.items.length === 0) {
                        container.html(
                            '<p class="rma-items-message">' +
                            $.mage.__('This order has no returnable items.') +
                            '</p>'
                        );
                        return;
                    }

                    self.renderItemsTable(container, response.items, response.conditions);
                },
                error: function () {
                    container.html(
                        '<p class="rma-items-message">' +
                        $.mage.__('Error loading order items.') +
                        '</p>'
                    );
                }
            });
        },

        syncItemsToSource: function () {
            let items = {},
                container = $('#rma-order-items-container');

            container.find('.rma-item-checkbox').each(function () {
                let checkbox = $(this),
                    row = checkbox.closest('tr'),
                    orderItemId = checkbox.data('item-id');

                if (checkbox.is(':checked')) {
                    items[orderItemId] = {
                        selected: '1',
                        qty_requested: row.find('.rma-item-qty').val() || '0',
                        condition_id: row.find('.rma-item-condition').val() || ''
                    };
                }
            });

            if (this.source) {
                this.source.set('data.items', items);
            }
        },

        renderItemsTable: function (container, items, conditions) {
            let self = this,
                html = '<table>',
                i, item, conditionOptions, j, reason;

            html += '<thead><tr>';
            html += '<th></th>';
            html += '<th>' + $.mage.__('Product Name') + '</th>';
            html += '<th>' + $.mage.__('SKU') + '</th>';
            html += '<th>' + $.mage.__('Ordered') + '</th>';
            html += '<th>' + $.mage.__('Available') + '</th>';
            html += '<th>' + $.mage.__('Qty to Return') + '</th>';
            html += '<th>' + $.mage.__('Condition') + '</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            // Build condition options HTML once
            conditionOptions = '<option value="">' + $.mage.__('-- Select --') + '</option>';

            for (j = 0; j < conditions.length; j++) {
                conditionOptions += '<option value="' + conditions[j].value + '">' +
                    this.escapeHtml(conditions[j].label) + '</option>';
            }

            for (i = 0; i < items.length; i++) {
                item = items[i];

                if (!item.is_eligible) {
                    reason = this.escapeHtml(item.disabled_reason);
                    html += '<tr class="rma-items-row-disabled" title="' + reason + '">';
                    html += '<td><input type="checkbox" disabled title="' + reason + '"/></td>';
                    html += '<td>' + this.escapeHtml(item.name) +
                        '<div class="rma-disabled-reason">' + reason + '</div></td>';
                    html += '<td>' + this.escapeHtml(item.sku) + '</td>';
                    html += '<td>' + item.qty_ordered + '</td>';
                    html += '<td>0</td>';
                    html += '<td>&mdash;</td>';
                    html += '<td>&mdash;</td>';
                    html += '</tr>';
                    continue;
                }

                html += '<tr>';
                html += '<td><input type="checkbox" ' +
                    'class="rma-item-checkbox" ' +
                    'data-item-id="' + item.order_item_id + '"/></td>';
                html += '<td>' + this.escapeHtml(item.name) + '</td>';
                html += '<td>' + this.escapeHtml(item.sku) + '</td>';
                html += '<td>' + item.qty_ordered + '</td>';
                html += '<td>' + item.qty_available + '</td>';
                html += '<td><input type="number" ' +
                    'value="' + item.qty_available + '" ' +
                    'min="1" max="' + item.qty_available + '" ' +
                    'class="admin__control-text rma-item-qty" ' +
                    'disabled/></td>';
                html += '<td><select ' +
                    'class="admin__control-select rma-item-condition" ' +
                    'disabled>' + conditionOptions + '</select></td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            container.html(html);

            // Bind checkbox toggle to enable/disable qty and condition fields,
            // and sync items data to source on every change
            container.off('change', '.rma-item-checkbox').on('change', '.rma-item-checkbox', function () {
                let row = $(this).closest('tr'),
                    isChecked = $(this).is(':checked');

                row.find('.rma-item-qty, .rma-item-condition').prop('disabled', !isChecked);
                self.syncItemsToSource();
            });

            container.off('change', '.rma-item-qty, .rma-item-condition')
                .on('change', '.rma-item-qty, .rma-item-condition', function () {
                    self.syncItemsToSource();
                });
        },

        escapeHtml: function (str) {
            if (!str) {
                return '';
            }

            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        getOptionByValue: function (value) {
            let options = this.options() || [],
                cacheOptions = this.cacheOptions.plain || [],
                allOptions = options.concat(cacheOptions),
                i;

            if (!value) {
                return null;
            }

            for (i = 0; i < allOptions.length; i++) {
                if (String(allOptions[i].value) === String(value)) {
                    return allOptions[i];
                }
            }

            return null;
        }
    });
});
