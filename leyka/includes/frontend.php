<?php
/**
 * @package Leyka
 * @subpackage Global and frontend modifications
 * @copyright Copyright (C) 2012-2013 by Teplitsa of Social Technologies (te-st.ru).
 * @author Lev Zvyagintsev aka Ahaenor
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License v2 or later
 * @since 1.0
 */

if( !defined('ABSPATH') ) exit; // Exit if accessed directly

/**
 * Changing labels.
 * From "downloads", "cart", ... -> "donates", "my donate list", etc.
 * NOTE: This function has been mostly transferred to admin-modifications.php, download labels filtering.
 */
function leyka_default_downloads_name($labels){
    $labels = array(
        'singular' => __('Donate', 'leyka'),
        'plural' => __('Donates', 'leyka')
    );
    return $labels;
}
add_filter('edd_default_downloads_name', 'leyka_default_downloads_name');

function leyka_empty_cart_message(){
    return __('Your "ready to donate" list is empty.', 'leyka');
}
add_filter('edd_empty_cart_message', 'leyka_empty_cart_message');

/** Change "Purchase" button text. */
function leyka_donate_submit_button($html){
    global $edd_options;

    $color = isset($edd_options['checkout_color']) ? $edd_options['checkout_color'] : 'gray';
    $style = isset($edd_options['button_style']) ? $edd_options['button_style'] : 'button';

    $complete_purchase = isset($edd_options['checkout_label']) && strlen(trim($edd_options['checkout_label'])) > 0 ? $edd_options['checkout_label'] : __('Make the donation', 'leyka');

    return '<input type="submit" class="edd-submit '.$color.' '.$style.'" id="edd-purchase-button" name="edd-purchase" value="'.$complete_purchase.'"/>';
}
add_filter('edd_checkout_button_purchase', 'leyka_donate_submit_button');

remove_action('edd_after_download_content', 'edd_append_purchase_link', 10, 1);
remove_action('edd_after_download_content', 'edd_show_has_purchased_item_message', 10, 1);

// Show payment gateways icons with correct hrefs: 
remove_action('edd_payment_mode_top', 'edd_show_payment_icons');
remove_action('edd_before_purchase_form', 'edd_show_payment_icons');
function leyka_show_correct_payment_icons(){
    global $edd_options;

    if(isset($edd_options['accepted_cards'])) {
        echo '<div class="edd-payment-icons">';
        foreach($edd_options['accepted_cards'] as $key => $card) {
            if(edd_string_is_image_url($key)) {
                echo '<img class="payment-icon" src="'.$key . '"/>';
            } else {
                echo '<img class="payment-icon" src="'.EDD_PLUGIN_URL.'includes/images/icons/'.strtolower(str_replace(' ', '', $key)).'.png"/>';
            }
        }
        echo '</div>';
    }
}
add_action('leyka_payment_mode_top', 'leyka_show_correct_payment_icons');

/** Sets an error on checkout if no gateways are enabled. */
function leyka_no_gateway_error(){
    $gateways = edd_get_enabled_payment_gateways();
    if( !$gateways )
        edd_set_error(
            'no_gateways',
            str_replace('%s', LEYKA_PLUGIN_TITLE, __('You must enable a payment gateway to use %s', 'leyka'))
        );
    else
        unset($_SESSION['edd-errors']['no_gateways']);
}
remove_action('init', 'edd_no_gateway_error');
add_action('init', 'leyka_no_gateway_error');

/** Adds a correct JS for agree to the terms module. */
function leyka_agree_to_terms_js(){
    global $edd_options;

    if( !empty($edd_options['show_agree_to_terms']) ) {?>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('body').on('click', '.edd_terms_links', function(e) {
                //e.preventDefault();
                $('#edd_terms').slideToggle();
                $('.edd_terms_links').toggle();
                return false;
            });
        });
    </script>
    <?php
    }
}
add_action('leyka_payment_mode_top', 'leyka_agree_to_terms_js');

//function leyka_frontend_init(){
//}
//add_action('init', 'leyka_frontend_init', 1);

/** Add a permanent "donor comments" field to all gateway payment forms. */
function leyka_after_cc_form(){?>
<fieldset>
    <legend><?php _e('Payment additional info', 'leyka'); ?></legend>
    <p>
        <textarea rows="5" cols="20" name="donor_comments" id="leyka-donor-comment" class="edd-input" placeholder="<?php echo __('Type your comments, if needed', 'leyka');?>"></textarea>
        <label class="edd-label leyka-donor-comment-label" for="leyka-donor-comment">
            <?php _e('Your comment', 'leyka');?>
        </label>
        <span id="leyka-comment-symbols-remain">100</span>
    </p>
    <p>
        <label><input type="checkbox" name="leyka_send_donor_email_conf" value="1" checked="1" />&nbsp;<?php echo __('Send me an email confimation for my donation', 'leyka');?></label>
    </p>
</fieldset>
<?php }
add_action('edd_purchase_form_after_cc_form', 'leyka_after_cc_form', 5);

// Remove default address and bank card fields from checkout form:
remove_action('edd_after_cc_fields', 'edd_default_cc_address_fields');
remove_action('edd_cc_form', 'edd_get_cc_form');

/** Show free donation amount field in the donate form */
function leyka_free_amount_field($donate_id){
    if( !leyka_is_any_sum_allowed($donate_id) )
        return;

    if(edd_item_in_cart($donate_id)) {
        foreach(edd_get_cart_contents() as $item) {
            if($item['id'] == $donate_id) {
                echo edd_currency_filter($item['options']['sum']);
                break;
            }
        }
    } else {?>
    <input type="text" name="leyka_free_donate_amount" id="free_donate_amount_<?php echo $donate_id;?>" value="<?php echo leyka_get_min_free_donation_sum($donate_id);?>" maxlength="30" />&nbsp;<?php echo edd_currency_filter('');?>
    <?php
    }
}
add_action('leyka_free_amount_field', 'leyka_free_amount_field');

/** Process free priced donate sums in the donates list ([downloads] shortcode). */
function leyka_donate_payment_form($purchase_form, $args){
    global $edd_options, $post;

    if ( !isset($edd_options['purchase_page']) || $edd_options['purchase_page'] == 0 ) {
        edd_set_error('set_checkout', sprintf( __( 'No checkout page has been configured. Visit <a href="%s">Settings</a> to set one.', 'edd'), admin_url('edit.php?post_type=download&page=edd-settings')));
        edd_print_errors();
        return false;
    }

    $defaults = array(
        'download_id' => $post->ID,
        'text'        => isset($edd_options['add_to_cart_text']) && $edd_options['add_to_cart_text']  != '' ? $edd_options['add_to_cart_text'] : __('Purchase', 'edd'),
        'style'       => isset($edd_options['button_style'])? $edd_options['button_style'] : 'button',
        'color'       => isset($edd_options[ 'checkout_color']) ? $edd_options['checkout_color'] : 'blue',
        'class'       => 'edd-submit'
    );

    $args = wp_parse_args($args, $defaults);
    $args['donate_id'] = $args['download_id'];
    unset($args['download_id']);

    if( !leyka_is_any_sum_allowed($args['donate_id']) )
        return $purchase_form;

    if(edd_item_in_cart($args['donate_id'])) {
        $button_display   = 'style="display:none;"';
        $checkout_display = '';
    } else {
        $button_display   = '';
        $checkout_display = 'style="display:none;"';
    }

    ob_start();?>
<form id="edd_purchase_<?php echo $args['donate_id']; ?>" class="edd_free_donate_form" method="post" action="#">
    <?php do_action('leyka_free_amount_field', $args['donate_id']);?>

    <div class="edd_purchase_submit_wrapper">
        <input type="submit" class="leyka-free-add-to-cart <?php echo implode(' ', array($args['style'], $args['color'], trim( $args['class'])));?>" name="leyka_donate" value="<?php echo __('Make a donation with this sum', 'leyka');?>" <?php echo $button_display;?> />
        <a href="<?php echo edd_get_checkout_uri();?>" class="<?php echo esc_attr('edd_go_to_checkout');?> <?php echo implode(' ', array( $args['style'], $args['color'], trim($args['class'])));?>" <?php echo $checkout_display;?>><?php echo __('Checkout', 'edd');?></a>

        <span class="edd-cart-ajax-alert">
            <img src="<?php echo esc_url(EDD_PLUGIN_URL.'includes/images/loading.gif');?>" class="edd-cart-ajax" style="display:none;" />
            <span class="edd-cart-added-alert" style="display:none;">&mdash;<?php _e('Item successfully added to your cart.', 'edd');?></span>
        </span>
    </div><!--end .edd_purchase_submit_wrapper-->

    <input type="hidden" class="donate_id" value="<?php echo (int)$args['donate_id'];?>" />
    <input type="hidden" class="action" value="leyka-free-donate-add-to-cart" />

    <?php do_action('edd_purchase_link_end', $args['donate_id']);?>

</form><!--end #edd_purchase_<?php echo esc_attr($args['download_id']);?>-->
<?php
    return apply_filters('leyka_free_donate_form', ob_get_clean(), $args);
}
add_filter('edd_purchase_download_form', 'leyka_donate_payment_form', 10, 2);

/**
 * Add a tmp price param contains the value donor inserted to the free donate,
 * so EDD could calculate total cart amounts properly.
 * This param will be deleted after the successful checkout. 
 */
function leyka_pre_add_to_cart($donate_id, $options){
    if(leyka_is_any_sum_allowed($donate_id))
        add_post_meta($donate_id, 'edd_price', $options['sum'], true)
            or update_post_meta($donate_id, 'edd_price', $options['sum']);
}
add_action('edd_pre_add_to_cart', 'leyka_pre_add_to_cart', 10, 2);

/**
 * Delete the price params of the free-priced donations in the cart, 'cause they mustn't be there by nature,
 * and this params was added only to correct the total cart price calculations by EDD core.
 * It's done in a filter instead of an action just because there weren't an appropriate actions in there.    
 */
function leyka_before_gateway($donation_data, $valid_data){
    foreach($donation_data['cart_details'] as $donation) {
        if(empty($donation['options']['is_free_sum']))
            continue;
        delete_post_meta($donation['id'], 'edd_price', $donation['options']['sum'], true);
    }

    return $donation_data;
}
add_filter('edd_purchase_data_before_gateway', 'leyka_before_gateway', 10, 2);

/** Extend the cart with the field to quickly add the donation. */
function ed_doanates_before_checkout(){?>
<div id="leyka_quick_add_to_cart_wrapper">
    <form id="leyka_quick_add_to_cart_form" method="post" action="#">
        <?php $donates = get_posts(array('post_type' => 'download', 'post_status' => 'publish'));
        $donates_to_add = array();
        foreach((array)$donates as $donate) {
            if(edd_item_in_cart($donate->ID))
                continue;
            else
                $donates_to_add[] = $donate;
        }

        if($donates_to_add) {?>
            <select name="leyka_quick_add_donate" id="leyka_quick_add_donate">
                <?php foreach($donates_to_add as $donate) {
                if(edd_has_variable_prices($donate->ID)) {
                    $price_options = edd_get_variable_prices($donate->ID);?>
                    <optgroup label="<?php echo $donate->post_title;?>">
                        <?php foreach($price_options as $key => $price) {?>
                        <option value="<?php echo $donate->ID.'_'.$key;?>">
                            <?php echo esc_html($price['name'].' - '.edd_currency_filter($price['amount']));?>
                        </option>
                        <?php }?>
                    </optgroup>
                    <?php } else if(leyka_is_any_sum_allowed($donate->ID)) {?>
                    <option value="<?php echo $donate->ID;?>" class="any-sum" data-min-price="<?php echo leyka_get_min_free_donation_sum($donate->ID);?>"><?php echo $donate->post_title.' - '.__('Any sum', 'leyka');?></option>
                    <?php } else {?>
                    <option value="<?php echo $donate->ID;?>"><?php echo $donate->post_title.' - '.edd_currency_filter(edd_get_download_price($donate->ID));?></option>
                    <?php }?>
                <?php }?>
            </select>
            &nbsp;&nbsp;
            <label id="leyka_quick_free_sum_label">
                <?php echo __('Insert the sum of your donation', 'leyka');?>
                <input type="text" size="4" id="leyka_quick_free_sum" class="edd-input" name="leyka_quick_free_sum" value="" />&nbsp;<?php echo edd_currency_filter('');?>
            </label>
            <input type="submit" name="leyka_quick_add_donate_submit" value="<?php echo __('Add to cart', 'edd');?>" />
            <?php }?>
    </form>
</div>
<?php }
add_action('edd_before_checkout_cart', 'ed_doanates_before_checkout');

/** Show "quick add" button on the empty cart. */
function leyka_empty_cart(){
    do_action('edd_before_checkout_cart');
}
add_action('edd_empty_cart', 'leyka_empty_cart');

/** Include all JS and CSS that frontend needed. */
function leyka_scripts(){?>
<script type="text/javascript">
    leyka_single_nonce = '<?php echo wp_create_nonce('leyka-single-donate-nonce');?>';
    leyka_free_nonce = '<?php echo wp_create_nonce('leyka-free-add-to-cart-nonce');?>';
</script>
<?php
    wp_enqueue_script('leyka-frontend-jq-plugins', LEYKA_PLUGIN_BASE_URL.'js/jq-plugins-frontend.js', array('jquery'), LEYKA_VERSION);
    wp_enqueue_script('leyka-frontend', LEYKA_PLUGIN_BASE_URL.'js/leyka-frontend.js', array('jquery', 'leyka-frontend-jq-plugins'), LEYKA_VERSION);
    wp_localize_script('leyka-frontend', 'l10n', array(
        'error_single_donate_free_sum_incorrect' => __('Sorry, the donation amount is incorrect', 'leyka'),
        'error_single_donate_must_agree_to_terms' => __('Sorry, you must agree to the donation terms first', 'leyka'),
    ));

    wp_register_style('leyka-styles', LEYKA_PLUGIN_BASE_URL.'styles/style.css');
    wp_enqueue_style('leyka-styles');
}
add_action('wp_enqueue_scripts', 'leyka_scripts', 11);