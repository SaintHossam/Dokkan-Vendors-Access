<?php

add_action('dokan_new_product_after_product_tags', 'add_custom_quantity_field', 10);
add_action('dokan_product_edit_after_product_tags', 'add_custom_quantity_field', 10, 2);

function add_custom_quantity_field($post = null, $post_id = 0)
{
    $product = $post_id ? wc_get_product($post_id) : null;
?>
    <?php if (!$product || $product->is_type('variable')): ?>
        <div class="dokan-form-group variations-wrapper">
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
                            <?php if ($i === 0): ?>
                                <button type="button" class="clear-variation-row dokan-btn dokan-btn-warning"><?php _e('تفريغ', 'dokan'); ?></button>
                            <?php elseif ($i > 0): ?>
                                <button type="button" class="remove-variation-row dokan-btn dokan-btn-danger"><?php _e('حذف', 'dokan'); ?></button>
                            <?php endif; ?>
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
                        '<div class="dokan-w1"><button type="button" class="remove-variation-row dokan-btn dokan-btn-danger">حذف</button></div>' +
                        '</div>';
                    $('.variations-container').append(newRow);
                });

                $(document).on('click', '.remove-variation-row', function() {
                    $(this).closest('.variations-row').remove();
                });

                $(document).on('click', '.clear-variation-row', function() {
                    var $row = $(this).closest('.variations-row');
                    $row.find('input[name="sizes[]"]').val('');
                    $row.find('input[name="prices[]"]').val('');
                    $row.find('input[name="quantities[]"]').val('');
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
    <?php endif; ?>
<?php
}

// Since fields aren't required anymore, update the save function to handle empty values better
add_action('dokan_new_product_added', 'save_custom_variation_data', 10, 2);
add_action('dokan_product_updated', 'save_custom_variation_data', 10, 2);

function save_custom_variation_data($product_id, $data)
{
    if (!isset($_POST['sizes']) || !isset($_POST['prices']) || !isset($_POST['quantities'])) {
        return;
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return;
    }

    $sizes = array_map('sanitize_text_field', $_POST['sizes']);
    $prices = array_map('floatval', $_POST['prices']);
    $quantities = array_map('intval', $_POST['quantities']);

    $valid_variations = [];
    foreach ($sizes as $index => $size) {
        // Only save if at least size is provided, price and quantity can be empty
        if (!empty($size)) {
            $valid_variations[] = [
                'size' => $size,
                'price' => isset($prices[$index]) ? $prices[$index] : 0,
                'quantity' => isset($quantities[$index]) ? $quantities[$index] : 0
            ];
        }
    }

    if (empty($valid_variations)) {
        return;
    }

    $sizes_to_save = array_column($valid_variations, 'size');
    $prices_to_save = array_column($valid_variations, 'price');
    $quantities_to_save = array_column($valid_variations, 'quantity');

    update_post_meta($product_id, '_sizes', $sizes_to_save);
    update_post_meta($product_id, '_prices', $prices_to_save);
    update_post_meta($product_id, '_quantities', $quantities_to_save);

    $attributes = [
        'size' => [
            'name' => 'size',
            'value' => implode('|', $sizes_to_save),
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
        $variation->set_attributes(['size' => $variation_data['size']]);
        $variation->set_regular_price($variation_data['price'] > 0 ? $variation_data['price'] : '');
        $variation->set_sale_price('');
        $variation->set_stock_quantity($variation_data['quantity']);
        $variation->set_manage_stock(true);
        $variation->set_stock_status($variation_data['quantity'] > 0 ? 'instock' : 'outofstock');
        $variation->set_status('publish');

        $variation_id = $variation->save();
        $variation_ids[] = $variation_id;
    }

    foreach ($existing_variations as $var_id) {
        if (!in_array($var_id, $variation_ids)) {
            $variation = wc_get_product($var_id);
            if ($variation) {
                $variation->delete(true);
            }
        }
    }

    $product->set_stock_status('instock');
    $product->save();
    wc_delete_product_transients($product_id);
}

add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $variation) {
    return $variation->get_stock_quantity() > 0 && $variation->get_regular_price() > 0;
}, 10, 2);

add_filter('woocommerce_available_variation', 'custom_update_variation_data', 10, 3);
function custom_update_variation_data($data, $product, $variation)
{
    $stock_quantity = $variation->get_stock_quantity();
    $data['availability_html'] = $stock_quantity > 0 ? 
        wc_get_stock_html($variation) : 
        '<p class="stock out-of-stock">' . __('هذا المنتج غير متوفر في المخزون حالياً.', 'woocommerce') . '</p>';
    $data['is_in_stock'] = $stock_quantity > 0;
    $data['max_qty'] = $stock_quantity > 0 ? $stock_quantity : 0;
    return $data;
}

add_filter('woocommerce_variable_sale_price_html', 'custom_variable_price_html', 10, 2);
add_filter('woocommerce_variable_price_html', 'custom_variable_price_html', 10, 2);
function custom_variable_price_html($price, $product) {
    $available_variations = $product->get_available_variations();
    $has_stock = false;
    
    foreach ($available_variations as $variation) {
        if ($variation['is_in_stock']) {
            $has_stock = true;
            break;
        }
    }
    
    if (!$has_stock) {
        return '<p class="stock out-of-stock">' . __('هذا المنتج غير متوفر في المخزون حالياً.', 'woocommerce') . '</p>';
    }
    
    return $price;
}
