<?php
/**
 * Plugin Name: Custom Variation Data for Dokan
 * Description: Customizes WooCommerce product variations for Dokan vendors, ensuring variations display on preview after first save.
 * Version: 1.6.1
 * Author: Hossam Hamdy
 * License: GPL-2.0+
 */
// For products quantity, price, and size (Hossam Custom)
add_action('dokan_new_product_after_product_tags', 'add_custom_quantity_field', 99999);
add_action('dokan_product_edit_after_product_tags', 'add_custom_quantity_field', 99999, 2);

function add_custom_quantity_field($post = null, $post_id = 0) {
    $product = $post_id ? wc_get_product($post_id) : null;
    $is_edit = $post_id > 0;

    $base_price = $post_id ? floatval(get_post_meta($post_id, '_custom_base_price', true)) : '';
    $base_quantity = $post_id ? intval(get_post_meta($post_id, '_custom_base_quantity', true)) : '';

    ?>
    <div class="dokan-form-group variations-wrapper" style="<?php echo $is_edit && (!$product || !$product->is_type('variable')) ? 'display:none;' : ''; ?>">
        <label class="dokan-control-label"><?php _e('الحجم والسعر والكمية للمتغيرات', 'dokan'); ?></label>
        <div class="variations-container">
            <!-- Base Product Price Field -->
            <div class="variations-row dokan-clearfix">
                <div class="dokan-w4">
                    <input type="text" class="dokan-form-control" value="<?php _e('سعر المنتج', 'dokan'); ?>" disabled>
                </div>
                <div class="dokan-w4">
                    <input type="number" step="0.01" min="0" name="custom_base_price" class="dokan-form-control" value="<?php echo esc_attr($base_price); ?>" placeholder="السعر" required>
                </div>
                <div class="dokan-w3">
                    <input type="number" min="0" step="1" name="custom_base_quantity" class="dokan-form-control" value="<?php echo esc_attr($base_quantity); ?>" placeholder="الكمية" required>
                </div>
                <div class="dokan-w1">
                    <button type="button" class="clear-base-price dokan-btn dokan-btn-warning"><?php _e('تفريغ', 'dokan'); ?></button>
                </div>
            </div>

            <?php
            // Retrieve variation data
            $sizes = $post_id ? get_post_meta($post_id, '_sizes', true) : [];
            $prices = $post_id ? get_post_meta($post_id, '_prices', true) : [];
            $quantities = $post_id ? get_post_meta($post_id, '_quantities', true) : [];

            $sizes = is_array($sizes) ? $sizes : [];
            $prices = is_array($prices) ? $prices : [];
            $quantities = is_array($quantities) ? $quantities : [];

            // Filter variations to exclude base price
            $filtered_variations = [];
            foreach ($sizes as $index => $size) {
                if (!empty($size) && strtolower($size) !== 'سعر المنتج') {
                    $filtered_variations[] = [
                        'size' => $size,
                        'price' => isset($prices[$index]) ? floatval($prices[$index]) : '',
                        'quantity' => isset($quantities[$index]) ? intval($quantities[$index]) : ''
                    ];
                }
            }

            // Only render variation rows if there are actual variations
            if (!empty($filtered_variations)) {
                foreach ($filtered_variations as $variation) {
                    $size = esc_attr($variation['size']);
                    $price = esc_attr($variation['price']);
                    $quantity = esc_attr($variation['quantity']);
                    ?>
                    <div class="variations-row dokan-clearfix">
                        <div class="dokan-w4">
                            <input type="text" name="sizes[]" class="dokan-form-control" value="<?php echo $size; ?>" placeholder="الحجم" required>
                        </div>
                        <div class="dokan-w4">
                            <input type="number" step="0.01" min="0" name="prices[]" class="dokan-form-control" value="<?php echo $price; ?>" placeholder="السعر" required>
                        </div>
                        <div class="dokan-w3">
                            <input type="number" min="0" step="1" name="quantities[]" class="dokan-form-control" value="<?php echo $quantity; ?>" placeholder="الكمية" required>
                        </div>
                        <div class="dokan-w1">
                            <button type="button" class="remove-variation-row dokan-btn dokan-btn-danger"><?php _e('حذف', 'dokan'); ?></button>
                        </div>
                    </div>
                    <?php
                }
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
                $('.no-variations-message').remove();

                var newRow = '<div class="variations-row dokan-clearfix">' +
                    '<div class="dokan-w4"><input type="text" name="sizes[]" class="dokan-form-control" placeholder="الحجم" required></div>' +
                    '<div class="dokan-w4"><input type="number" step="0.01" min="0" name="prices[]" class="dokan-form-control" placeholder="السعر" required></div>' +
                    '<div class="dokan-w3"><input type="number" min="0" step="1" name="quantities[]" class="dokan-form-control" placeholder="الكمية" required></div>' +
                    '<div class="dokan-w1"><button type="button" class="remove-variation-row dokan-btn dokan-btn-danger"><?php _e('حذف', 'dokan'); ?></button></div>' +
                    '</div>';
                $('.variations-container').append(newRow);
            });

            $(document).on('click', '.remove-variation-row', function(e) {
                e.preventDefault();
                $(this).closest('.variations-row').remove();

                if ($('.variations-row').length === 1) {
                    $('.variations-container').append('<p class="no-variations-message"><?php _e('لا توجد متغيرات حالياً. يمكنك إضافة متغير جديد باستخدام الزر أدناه.', 'dokan'); ?></p>');
                }
            });

            $(document).on('click', '.clear-base-price', function(e) {
                e.preventDefault();
                $(this).closest('.variations-row').find('input[name="custom_base_price"]').val('');
                $(this).closest('.variations-row').find('input[name="custom_base_quantity"]').val('');
            });

            $(document).on('input', 'input[name="quantities[]"], input[name="custom_base_quantity"]', function() {
                if (!this.value) {
                    this.value = '';
                } else {
                    this.value = Math.max(0, Math.floor(this.value));
                }
            });

            $(document).on('input', 'input[name="prices[]"], input[name="custom_base_price"]', function() {
                if (!this.value) {
                    this.value = '';
                }
            });

            $('#dokan-product-form, #dokan-edit-product-form').on('submit', function(e) {
                if ($('#product_type').val() !== 'variable') {
                    return; // Skip validation for non-variable products
                }

                var basePrice = $('input[name="custom_base_price"]').val();
                var baseQuantity = $('input[name="custom_base_quantity"]').val();
                var hasInvalidVariation = false;

                $('.variations-row:not(:first)').each(function() {
                    var size = $(this).find('input[name="sizes[]"]').val();
                    var price = $(this).find('input[name="prices[]"]').val();
                    var quantity = $(this).find('input[name="quantities[]"]').val();

                    if (size && (!price || parseFloat(price) <= 0 || !quantity || parseInt(quantity) < 0)) {
                        hasInvalidVariation = true;
                    }
                });

                if (!basePrice || parseFloat(basePrice) <= 0) {
                    alert('يرجى إدخال سعر المنتج، يجب أن يكون أكبر من 0.');
                    e.preventDefault();
                    return false;
                }

                if (!baseQuantity || parseInt(baseQuantity) < 0) {
                    alert('يرجى إدخال كمية المنتج، يجب أن تكون 0 أو أكبر.');
                    e.preventDefault();
                    return false;
                }

                if (hasInvalidVariation) {
                    alert('يرجى التأكد من إدخال بيانات صحيحة لكل المتغيرات (الحجم، السعر، الكمية).');
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
    <?php
}

add_action('dokan_new_product_added', 'save_custom_variation_data', 99999, 2);
add_action('dokan_product_updated', 'save_custom_variation_data', 99999, 2);

function save_custom_variation_data($product_id, $data) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return; // Exit if product is not variable
    }

    $custom_base_price = isset($_POST['custom_base_price']) ? floatval($_POST['custom_base_price']) : 0;
    $custom_base_quantity = isset($_POST['custom_base_quantity']) ? intval($_POST['custom_base_quantity']) : 0;

    if ($custom_base_price <= 0) {
        wc_add_notice(__('سعر المنتج مطلوب ويجب أن يكون أكبر من 0.', 'dokan'), 'error');
        wp_redirect(wp_get_referer());
        exit;
    }

    if ($custom_base_quantity < 0) {
        wc_add_notice(__('كمية المنتج مطلوبة ويجب أن تكون 0 أو أكبر.', 'dokan'), 'error');
        wp_redirect(wp_get_referer());
        exit;
    }

    update_post_meta($product_id, '_custom_base_price', $custom_base_price);
    update_post_meta($product_id, '_custom_base_quantity', $custom_base_quantity);

    $sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) ? array_map('sanitize_text_field', $_POST['sizes']) : [];
    $prices = isset($_POST['prices']) && is_array($_POST['prices']) ? array_map('floatval', $_POST['prices']) : [];
    $quantities = isset($_POST['quantities']) && is_array($_POST['quantities']) ? array_map('intval', $_POST['quantities']) : [];

    $valid_variations = [];
    foreach ($sizes as $index => $size) {
        if (!empty($size) && strtolower($size) !== 'سعر المنتج') {
            $price = isset($prices[$index]) ? floatval($prices[$index]) : 0;
            $quantity = isset($quantities[$index]) ? intval($quantities[$index]) : 0;
            if ($price > 0 && $quantity >= 0) {
                $valid_variations[] = [
                    'size' => $size,
                    'price' => $price,
                    'quantity' => $quantity
                ];
            }
        }
    }

    $all_variations = array_merge([[
        'size' => 'سعر المنتج',
        'price' => $custom_base_price,
        'quantity' => $custom_base_quantity
    ]], $valid_variations);

    $sizes_to_save = array_column($valid_variations, 'size');
    $prices_to_save = array_column($valid_variations, 'price');
    $quantities_to_save = array_column($valid_variations, 'quantity');

    update_post_meta($product_id, '_sizes', $sizes_to_save);
    update_post_meta($product_id, '_prices', $prices_to_save);
    update_post_meta($product_id, '_quantities', $quantities_to_save);

    $attributes = [
        'size' => [
            'name' => 'size',
            'value' => implode('|', array_map('strtolower', array_column($all_variations, 'size'))),
            'position' => 0,
            'is_visible' => 1,
            'is_variation' => 1,
            'is_taxonomy' => 0
        ]
    ];
    update_post_meta($product_id, '_product_attributes', $attributes);

    $existing_variations = $product->get_children();
    foreach ($existing_variations as $var_id) {
        $variation = wc_get_product($var_id);
        if ($variation) {
            $variation->delete(true);
        }
    }

    $variation_ids = [];
    foreach ($all_variations as $variation_data) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_attributes(['size' => strtolower($variation_data['size'])]);
        $variation->set_regular_price($variation_data['price']);
        $variation->set_sale_price('');
        $variation->set_stock_quantity($variation_data['quantity']);
        $variation->set_manage_stock(true);
        $variation->set_stock_status($variation_data['quantity'] > 0 ? 'instock' : 'outofstock');
        $variation->set_status('publish');

        $variation_id = $variation->save();
        $variation_ids[] = $variation_id;
    }

    $product->set_stock_status($custom_base_quantity > 0 ? 'instock' : 'outofstock');
    $product->save();
    wc_delete_product_transients($product_id);
}

add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $variation) {
    $parent_product = wc_get_product($variation->get_parent_id());
    if (!$parent_product || !$parent_product->is_type('variable')) {
        return $purchasable; // Skip for non-variable products
    }

    $price = floatval($variation->get_regular_price());
    $stock = $variation->get_stock_quantity();
    return $price > 0 && $stock > 0;
}, 99999, 2);

add_filter('woocommerce_available_variation', 'custom_update_variation_data', 99999, 3);
function custom_update_variation_data($data, $product, $variation) {
    if (!$product->is_type('variable')) {
        return $data; // Skip for non-variable products
    }

    $stock_quantity = $variation->get_stock_quantity();
    $data['availability_html'] = $stock_quantity > 0 ? 
        wc_get_stock_html($variation) : 
        '<p class="stock out-of-stock">' . __('هذا المنتج غير متوفر في المخزون حالياً.', 'woocommerce') . '</p>';
    $data['is_in_stock'] = $stock_quantity > 0;
    $data['max_qty'] = $stock_quantity > 0 ? $stock_quantity : 0;
    return $data;
}

add_filter('woocommerce_variable_sale_price_html', 'custom_variable_price_html', 99999, 2);
add_filter('woocommerce_variable_price_html', 'custom_variable_price_html', 99999, 2);
function custom_variable_price_html($price, $product) {
    if (!$product->is_type('variable')) {
        return $price; // Skip for non-variable products
    }

    $custom_base_price = floatval(get_post_meta($product->get_id(), '_custom_base_price', true));
    $custom_base_quantity = intval(get_post_meta($product->get_id(), '_custom_base_quantity', true));

    if ($custom_base_price > 0 && $custom_base_quantity >= 0) {
        return wc_price($custom_base_price);
    }

    $available_variations = $product->get_available_variations();
    $prices = [];
    $has_stock = false;

    foreach ($available_variations as $variation) {
        $variation_price = floatval($variation['display_price']);
        if ($variation_price > 0 && $variation['attributes']['attribute_size'] !== 'سعر المنتج') {
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

add_filter('woocommerce_add_to_cart_validation', 'custom_validate_add_to_cart', 99999, 5);
function custom_validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = []) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return $passed; // Skip for non-variable products
    }

    if ($variation_id) {
        $cart = WC()->cart->get_cart();
        foreach ($cart as $cart_item_key => $cart_item) {
            if ($cart_item['variation_id'] == $variation_id && $cart_item['product_id'] == $product_id) {
                $new_quantity = $cart_item['quantity'] + $quantity;
                $variation = wc_get_product($variation_id);
                $stock = $variation->get_stock_quantity();
                if ($stock !== null && $new_quantity > $stock) {
                    wc_add_notice(__('الكمية المطلوبة غير متوفرة في المخزون.', 'woocommerce'), 'error');
                    return false;
                }
                WC()->cart->set_quantity($cart_item_key, $new_quantity);
                return false;
            }
        }
    }
    return $passed;
}

add_action('wp_footer', 'set_default_variation_to_base_price', 99999);
function set_default_variation_to_base_price() {
    if (is_product()) {
        global $product;
        if (!$product || !$product->is_type('variable')) {
            return; // Skip for non-variable products
        }

        $custom_base_price = floatval(get_post_meta($product->get_id(), '_custom_base_price', true));
        $custom_base_quantity = intval(get_post_meta($product->get_id(), '_custom_base_quantity', true));
        if ($custom_base_price > 0 && $custom_base_quantity >= 0) {
            $currency_symbol = get_woocommerce_currency_symbol();
            $formatted_price = wc_format_decimal($custom_base_price, wc_get_price_decimals());
            ?>
            <script>
                jQuery(document).ready(function($) {
                    var $variationForm = $('.variations_form');
                    var $priceDisplay = $('.woocommerce-Price-amount');

                    $variationForm.find('select[name="attribute_size"]').val('سعر المنتج').trigger('change');

                    $variationForm.on('woocommerce_variation_has_changed', function() {
                        var selectedSize = $variationForm.find('select[name="attribute_size"]').val();
                        if (selectedSize === 'سعر المنتج') {
                            $priceDisplay.html('<bdi><span class="woocommerce-Price-currencySymbol"><?php echo esc_js($currency_symbol); ?></span> <?php echo esc_js($formatted_price); ?></bdi>');
                        }
                    });

                    $variationForm.trigger('woocommerce_variation_has_changed');
                });
            </script>
            <?php
        }
    }
}
