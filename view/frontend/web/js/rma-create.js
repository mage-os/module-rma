define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        let itemsAjaxUrl = config.itemsAjaxUrl,
            conditions = config.conditions || [],
            preloadOrderId = config.preloadOrderId || null,
            $form = $(element),
            $orderSelect = $form.find('#order_id'),
            $itemsContainer = $form.find('#rma-order-items-container');

        /**
         * Escape HTML special characters
         */
        function escapeHtml(str) {
            if (!str) {
                return '';
            }

            let div = document.createElement('div');
            div.appendChild(document.createTextNode(str));

            return div.innerHTML;
        }

        function loadOrderItems(orderId) {
            if (!orderId) {
                $itemsContainer.html(
                    '<div class="message info"><span>' +
                    escapeHtml($t('Select an order to see available items.')) +
                    '</span></div>'
                );

                return;
            }

            $itemsContainer.html(
                '<div class="message info"><span>' +
                escapeHtml($t('Loading items...')) +
                '</span></div>'
            );

            $.ajax({
                url: itemsAjaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {order_id: orderId},
                success: function (response) {
                    if (response.error) {
                        $itemsContainer.html(
                            '<div class="message error"><span>' +
                            escapeHtml(response.error) +
                            '</span></div>'
                        );

                        return;
                    }

                    if (!response.items || response.items.length === 0) {
                        $itemsContainer.html(
                            '<div class="message info"><span>' +
                            escapeHtml($t('No items available for return in this order.')) +
                            '</span></div>'
                        );

                        return;
                    }

                    renderItemsTable(response.items);
                },
                error: function () {
                    $itemsContainer.html(
                        '<div class="message error"><span>' +
                        escapeHtml($t('An error occurred while loading order items.')) +
                        '</span></div>'
                    );
                }
            });
        }

        function renderItemsTable(items) {
            let html = '<div class="field field-items">' +
                '<label class="label"><span>' + escapeHtml($t('Items to Return')) + '</span></label>' +
                '<div class="control">' +
                '<div class="table-wrapper">' +
                '<table class="data table table-rma-items-select">' +
                '<thead><tr>' +
                '<th class="col select">' + escapeHtml($t('Select')) + '</th>' +
                '<th class="col product">' + escapeHtml($t('Product')) + '</th>' +
                '<th class="col sku">' + escapeHtml($t('SKU')) + '</th>' +
                '<th class="col qty">' + escapeHtml($t('Quantity')) + '</th>' +
                '<th class="col condition">' + escapeHtml($t('Condition')) + '</th>' +
                '</tr></thead><tbody>';

            $.each(items, function (index, item) {
                let itemId = item.order_item_id,
                    reason = escapeHtml(item.disabled_reason),
                    titleAttr = item.is_eligible ? '' : ' title="' + reason.replace(/"/g, '&quot;') + '"';

                html += '<tr data-item-id="' + itemId + '"' +
                    (item.is_eligible ? '' : ' class="rma-item-disabled"') + titleAttr + '>' +
                    '<td class="col select">' +
                    '<input type="checkbox" name="items[' + itemId + '][selected]" value="1" ' +
                    'class="rma-item-checkbox" data-item-id="' + itemId + '"' +
                    (item.is_eligible ? '' : ' disabled="disabled"') + titleAttr + '/>' +
                    '</td>' +
                    '<td class="col product">' + escapeHtml(item.name) +
                    (item.is_eligible ? '' : '<div class="rma-disabled-reason">' + reason + '</div>') +
                    '</td>' +
                    '<td class="col sku">' + escapeHtml(item.sku) + '</td>' +
                    '<td class="col qty">' +
                    '<input type="number" name="items[' + itemId + '][qty_requested]" ' +
                    'value="' + item.qty_available + '" ' +
                    'min="1" max="' + item.qty_available + '" ' +
                    'class="input-text qty rma-item-qty" disabled="disabled" ' +
                    'data-item-id="' + itemId + '"/>' +
                    '<span class="qty-info">(' + escapeHtml($t('max')) + ': ' + item.qty_available + ')</span>' +
                    '</td>' +
                    '<td class="col condition">' +
                    buildConditionSelect(itemId) +
                    '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div></div></div>';

            $itemsContainer.html(html);
            bindItemEvents();
        }

        function buildConditionSelect(itemId) {
            let html = '<select name="items[' + itemId + '][condition_id]" ' +
                'class="select rma-item-condition" disabled="disabled" data-item-id="' + itemId + '">' +
                '<option value="">' + escapeHtml($t('-- Select --')) + '</option>';

            $.each(conditions, function (index, condition) {
                html += '<option value="' + condition.value + '">' +
                    escapeHtml(condition.label) + '</option>';
            });

            html += '</select>';

            return html;
        }

        function bindItemEvents() {
            $itemsContainer.on('change', '.rma-item-checkbox', function () {
                let $checkbox = $(this),
                    itemId = $checkbox.data('item-id'),
                    isChecked = $checkbox.is(':checked'),
                    $row = $checkbox.closest('tr'),
                    $qty = $row.find('.rma-item-qty'),
                    $condition = $row.find('.rma-item-condition');

                $qty.prop('disabled', !isChecked);
                $condition.prop('disabled', !isChecked);

                if (!isChecked) {
                    $qty.val($qty.attr('max'));
                    $condition.val('');
                }
            });
        }

        $form.on('submit', function (e) {
            let hasSelectedItem = $itemsContainer.find('.rma-item-checkbox:checked').length > 0;

            if (!hasSelectedItem) {
                e.preventDefault();
                alert($t('Please select at least one item to return.'));

                return false;
            }

            return true;
        });

        // Init: bind to order select change
        if ($orderSelect.length) {
            $orderSelect.on('change', function () {
                loadOrderItems($(this).val());
            });

            // Trigger if order is already selected
            if ($orderSelect.val()) {
                loadOrderItems($orderSelect.val());
            }
        }

        // For guest: preload items if order_id is set
        if (preloadOrderId) {
            loadOrderItems(preloadOrderId);
        }
    };
});
