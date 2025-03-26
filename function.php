<?php
// For products quantity and price and size ((Hossam Custom))
// Add custom fields after product tags in Dokan
add_action('dokan_new_product_after_product_tags', 'add_custom_quantity_field', 10);
add_action('dokan_product_edit_after_product_tags', 'add_custom_quantity_field', 10, 2);

function add_custom_quantity_field($post = null, $post_id = 0) {
    $product = $post_id ? wc_get_product($post_id) : null;
    $is_edit = $post_id > 0; // Check if this is an edit page
    ?>
    <div class="dokan-form-group variations-wrapper" style="<?php echo $is_edit && (!$product || !$product->is_type('variable')) ? 'display:none;' : ''; ?>">
        <label class="dokan-control-label"><?php _e('الحجم والسعر والكمية للمتغيرات', 'dokan'); ?></label>
        <div class="variations-container">
            <?php
            $sizes = $post_id ? get_post_meta($post_id, '_sizes', true) : [];
            $prices = $post_id ? get_post_meta($post_id, '_prices', true) : [];
            $quantities = $post_id ? get_post_meta($post_id, '_quantities', true) : [];

            $sizes = !empty($sizes) && is_array($sizes) ? $sizes : [];
            $prices = !empty($prices) && is_array($prices) ? $prices : [];
            $quantities = !empty($quantities) && is_array($quantities) ? $quantities : [];

            $count = !empty($sizes) ? count($sizes) : 1;
            for ($i = 0; $i < $count; $i++) {
                $size = isset($sizes[$i]) ? esc_attr($sizes[$i]) : '';
                $price = isset($prices[$i]) ? esc_attr($prices[$i]) : '';
                $quantity = isset($quantities[$i]) ? esc_attr($quantities[$i]) : '';
                ?>
                <div class="variations-row dokan-clearfix">
                    <div class="dokan-w4">
                        <input type="text" name="sizes[]" class="dokan-form-control" value="<?php echo $size; ?>" placeholder="الحجم">
                    </div>
                    <div class="dokan-w4">
                        <input type="number" step="0.01" min="0" name="prices[]" class="dokan-form-control" value="<?php echo $price ?: ''; ?>" placeholder="السعر">
                    </div>
                    <div class="dokan-w3">
                        <input type="number" min="0" step="1" name="quantities[]" class="dokan-form-control" value="<?php echo $quantity ?: ''; ?>" placeholder="الكمية">
                    </div>
                    <div class="dokan-w1">
                        <button type="button" class="remove-variation-row dokan-btn dokan-btn-danger"><?php _e('حذف', 'dokan'); ?></button>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <button type="button" class="add-variation-row dokan-btn dokan-btn-theme"><?php _e('إضافة متغير جديد', 'dokan'); ?></button>
    </div>

    <script>
        jQuery(document).ready(function($) {
            function toggleVariations() {
                $('.variations-wrapper').toggle($('#product_type').val() === 'variable');
            }

            $('#product_type').on('change', toggleVariations);
            toggleVariations();

            $('.add-variation-row').on('click', function() {
                var newRow = '<div class="variations-row dokan-clearfix">' +
                    '<div class="dokan-w4"><input type="text" name="sizes[]" class="dokan-form-control" placeholder="الحجم"></div>' +
                    '<div class="dokan-w4"><input type="number" step="0.01" min="0" name="prices[]" class="dokan-form-control" placeholder="السعر"></div>' +
                    '<div class="dokan-w3"><input type="number" min="0" step="1" name="quantities[]" class="dokan-form-control" placeholder="الكمية"></div>' +
                    '<div class="dokan-w1"><button type="button" class="remove-variation-row dokan-btn dokan-btn-danger"><?php _e('حذف', 'dokan'); ?></button></div>' +
                    '</div>';
                $('.variations-container').append(newRow);
            });

            $(document).on('click', '.remove-variation-row', function(e) {
                e.preventDefault();
                $(this).closest('.variations-row').remove();
            });

            $(document).on('input', 'input[name="quantities[]"], input[name="prices[]"]', function() {
                if (!this.value) {
                    this.value = '';
                } else if (this.name === 'quantities[]') {
                    this.value = Math.max(0, Math.floor(this.value));
                }
            });
        });
    </script>
    <?php
}

// Save custom variation data
add_action('dokan_new_product_added', 'save_custom_variation_data', 10, 2);
add_action('dokan_product_updated', 'save_custom_variation_data', 10, 2);

function save_custom_variation_data($product_id, $data) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return;
    }

    // تسجيل البيانات الواردة للتحقق
    error_log("POST Data for Product ID $product_id: " . print_r($_POST, true));

    // التحقق مما إذا كانت الحقول موجودة وغير فارغة
    $sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) ? array_map('sanitize_text_field', $_POST['sizes']) : [];
    $prices = isset($_POST['prices']) && is_array($_POST['prices']) ? array_map('floatval', $_POST['prices']) : [];
    $quantities = isset($_POST['quantities']) && is_array($_POST['quantities']) ? array_map('intval', $_POST['quantities']) : [];

    $valid_variations = [];
    foreach ($sizes as $index => $size) {
        if (!empty($size)) {
            $valid_variations[] = [
                'size' => $size,
                'price' => isset($prices[$index]) ? $prices[$index] : 0,
                'quantity' => isset($quantities[$index]) ? $quantities[$index] : 0
            ];
        }
    }

    // إذا لم يتم إرسال أي بيانات أو كانت جميع الحقول فارغة، قم بحذف كل شيء
    if (empty($sizes) || empty($valid_variations)) {
        // حذف جميع المتغيرات الموجودة
        $existing_variations = $product->get_children();
        foreach ($existing_variations as $var_id) {
            $variation = wc_get_product($var_id);
            if ($variation) {
                $variation->delete(true);
                error_log("Deleted variation ID: $var_id for product ID: $product_id");
            }
        }

        // حذف السمات
        update_post_meta($product_id, '_product_attributes', []);
        
        // حذف البيانات الوصفية المخصصة
        delete_post_meta($product_id, '_sizes');
        delete_post_meta($product_id, '_prices');
        delete_post_meta($product_id, '_quantities');

        // تحديث حالة المخزون
        $product->set_stock_status('outofstock');
        $product->save();
        wc_delete_product_transients($product_id);

        error_log("All variations, attributes, and meta removed for product ID: $product_id");
        return;
    }

    // معالجة المتغيرات إذا كانت موجودة
    $sizes_to_save = array_column($valid_variations, 'size');
    $prices_to_save = array_column($valid_variations, 'price');
    $quantities_to_save = array_column($valid_variations, 'quantity');

    update_post_meta($product_id, '_sizes', $sizes_to_save);
    update_post_meta($product_id, '_prices', $prices_to_save);
    update_post_meta($product_id, '_quantities', $quantities_to_save);

    $attributes = [
        'size' => [
            'name' => 'size',
            'value' => implode('|', array_map('strtolower', $sizes_to_save)),
            'position' => 0,
            'is_visible' => 1,
            'is_variation' => 1,
            'is_taxonomy' => 0
        ]
    ];
    update_post_meta($product_id, '_product_attributes', $attributes);

    $existing_variations = $product->get_children();
    $variation_ids = [];

    foreach ($valid_variations as $variation_data) {
        $variation_id = null;

        foreach ($existing_variations as $var_id) {
            $variation = wc_get_product($var_id);
            if ($variation && strtolower($variation->get_attribute('size')) === strtolower($variation_data['size'])) {
                $variation_id = $var_id;
                break;
            }
        }

        $variation = $variation_id ? wc_get_product($variation_id) : new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_attributes(['size' => strtolower($variation_data['size'])]);
        $variation->set_regular_price($variation_data['price'] > 0 ? $variation_data['price'] : '');
        $variation->set_sale_price('');
        $variation->set_stock_quantity($variation_data['quantity']);
        $variation->set_manage_stock(true);
        $variation->set_stock_status($variation_data['quantity'] > 0 ? 'instock' : 'outofstock');
        $variation->set_status('publish');

        $variation_id = $variation->save();
        $variation_ids[] = $variation_id;

        error_log("Variation Saved - ID: $variation_id, Size: " . $variation_data['size'] . ", Price: " . $variation->get_regular_price() . ", Stock: " . $variation->get_stock_quantity());
    }

    // حذف المتغيرات القديمة غير المستخدمة
    foreach ($existing_variations as $var_id) {
        if (!in_array($var_id, $variation_ids)) {
            $variation = wc_get_product($var_id);
            if ($variation) {
                $variation->delete(true);
                error_log("Deleted old variation ID: $var_id for product ID: $product_id");
            }
        }
    }

    $product->set_stock_status('instock');
    $product->save();
    wc_delete_product_transients($product_id);
}

// Adjust purchasability logic
add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $variation) {
    $price = $variation->get_regular_price();
    $stock = $variation->get_stock_quantity();
    $purchasable = !empty($price) && $price > 0;
    error_log("Variation Purchasable Check - ID: " . $variation->get_id() . ", Price: $price, Stock: $stock, Purchasable: " . ($purchasable ? 'Yes' : 'No'));
    return $purchasable;
}, 10, 2);

// Update variation data for frontend
add_filter('woocommerce_available_variation', 'custom_update_variation_data', 10, 3);
function custom_update_variation_data($data, $product, $variation) {
    $stock_quantity = $variation->get_stock_quantity();
    $data['availability_html'] = $stock_quantity > 0 ? 
        wc_get_stock_html($variation) : 
        '<p class="stock out-of-stock">' . __('هذا المنتج غير متوفر في المخزون حالياً.', 'woocommerce') . '</p>';
    $data['is_in_stock'] = $stock_quantity > 0;
    $data['max_qty'] = $stock_quantity > 0 ? $stock_quantity : 0;

    error_log("Variation Data - ID: " . $variation->get_id() . ", Size: " . $variation->get_attribute('size') . ", Stock: $stock_quantity, Price: " . $variation->get_regular_price());
    return $data;
}

// Custom price HTML for variable products
add_filter('woocommerce_variable_sale_price_html', 'custom_variable_price_html', 10, 2);
add_filter('woocommerce_variable_price_html', 'custom_variable_price_html', 10, 2);
function custom_variable_price_html($price, $product) {
    $available_variations = $product->get_available_variations();
    $prices = [];
    $has_stock = false;

    foreach ($available_variations as $variation) {
        $variation_price = floatval($variation['display_price']);
        if ($variation_price > 0) {
            $prices[] = $variation_price;
        }
        if ($variation['is_in_stock']) {
            $has_stock = true;
        }
    }

    if (!$has_stock) {
        return '<p class="stock out-of-stock">' . __('هذا المنتج غير متوفر في المخزون حالياً.', 'woocommerce') . '</p>';
    }

    if (!empty($prices)) {
        $lowest_price = min($prices);
        return wc_price($lowest_price);
    }

    return $price;
}

// Display total weight on product page
add_action('woocommerce_after_add_to_cart_button', 'display_total_weight');
function display_total_weight() {
    global $product;
    if ($product->is_type('variable')) {
        ?>
        <div id="total-weight-display" style="margin-top: 10px; font-weight: bold;">
            <?php _e('الإجمالي: ', 'woocommerce'); ?><span id="total-weight-value">0</span>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Function to update total weight
            function updateTotalWeight() {
                var selectedSize = $('select[name="attribute_size"]').val() || ''; // Get selected size
                var quantity = parseInt($('input[name="quantity"]').val()) || 1; // Get quantity, default to 1
                var sizeValue = 0;
                var unit = '';

                // Extract numeric value and unit from size (e.g., "20 حبة" -> 20 and "حبة")
                if (selectedSize) {
                    var sizeMatch = selectedSize.match(/(\d+)\s*(\S+)/); // Match number and unit
                    if (sizeMatch) {
                        sizeValue = parseInt(sizeMatch[1]) || 0; // Numeric part
                        unit = sizeMatch[2] || ''; // Unit part (e.g., "حبة" or "كيلو")
                    }
                }

                var totalWeight = sizeValue * quantity;
                $('#total-weight-value').text(totalWeight + ' ' + unit);

                // Debugging
                console.log('Selected Size:', selectedSize);
                console.log('Size Value:', sizeValue);
                console.log('Unit:', unit);
                console.log('Quantity:', quantity);
                console.log('Total Weight:', totalWeight);
            }

            // Trigger on variation change
            $('form.variations_form').on('woocommerce_variation_has_changed', function() {
                updateTotalWeight();
            });

            // Trigger on quantity change
            $('input[name="quantity"]').on('input change', function() {
                updateTotalWeight();
            });

            // Trigger on "Add to Cart" click
            $('.single_add_to_cart_button').on('click', function(e) {
                updateTotalWeight();
            });

            // Trigger on page load if variation is pre-selected
            $(document).ready(function() {
                if ($('.variation_id').val() && $('.variation_id').val() > 0) {
                    updateTotalWeight();
                }
            });

            // Ensure update triggers when form is fully initialized
            $('form.variations_form').on('show_variation', function() {
                updateTotalWeight();
            });
        });
        </script>
        <?php
    }
}

// For 5% commission
add_action('woocommerce_order_status_completed', 'log_commission_on_order_complete', 10, 1);
function log_commission_on_order_complete($order_id) {
    $order = wc_get_order($order_id);
    $total = $order->get_total();
    $commission = $total * 0.05;
    $vendor_amount = $total - $commission;

    update_post_meta($order_id, '_admin_commission', $commission);
    update_post_meta($order_id, '_vendor_amount', $vendor_amount);
}

// Change currency symbol
add_filter('woocommerce_currency_symbol', 'change_sar_currency_symbol_except_cart', 10, 2);
function change_sar_currency_symbol_except_cart($currency_symbol, $currency) {
    if ($currency === 'SAR') {
        if (is_cart()) {
            return 'ر.س';
        } else {
            return '';
        }
    }
    return $currency_symbol;
}

