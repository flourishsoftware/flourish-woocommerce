<?php

namespace FlourishWooCommercePlugin\CustomFields;

defined( 'ABSPATH' ) || exit;

class License
{
    public function register_hooks()
    {
        add_action('woocommerce_register_form', [$this, 'add_license_field_to_registration_form']);
        add_action('woocommerce_register_post', [$this, 'validate_license_field'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'save_license_field']);
        add_action('woocommerce_edit_account_form', [$this, 'add_license_field_to_edit_account_form']);
        add_action('woocommerce_save_account_details', [$this, 'save_license_field']);
        add_filter('woocommerce_checkout_fields', [$this, 'add_license_field_to_checkout_form']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_license_field']);
        add_action('show_user_profile', [$this, 'add_license_field_to_user_edit']);
        add_action('edit_user_profile', [$this, 'add_license_field_to_user_edit']);
        add_action('personal_options_update', [$this, 'save_license_field_on_user_update']);
        add_action('edit_user_profile_update', [$this, 'save_license_field_on_user_update']);
    }

    public function add_license_field_to_registration_form()
    {
        ?>
        <p class="form-row form-row-wide">
            <label for="license"><?php _e('License', 'woocommerce'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="license" id="license" value="<?php if (!empty($_POST['license'])) echo esc_attr($_POST['license']); ?>" />
        </p>
        <?php
    }

    public function validate_license_field($username, $email, $validation_errors)
    {
        if (empty($_POST['license'])) {
            $validation_errors->add('license_error', __('License is required.', 'woocommerce'));
        }
        return $validation_errors;
    }

    public function save_license_field($customer_id)
    {
        if (isset($_POST['license'])) {
            update_user_meta($customer_id, 'license', sanitize_text_field($_POST['license']));
        }
    }

    public function add_license_field_to_edit_account_form()
    {
        $user_id = get_current_user_id();
        $current_license = get_user_meta($user_id, 'license', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="license"><?php _e('License', 'woocommerce'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="license" id="license" value="<?php echo esc_attr($current_license); ?>" />
        </p>
        <?php
    }

    public function add_license_field_to_checkout_form($fields)
    {
        $fields['billing']['license'] = array(
            'label' => __('License', 'woocommerce'),
            'placeholder' => _x('Your license ID like: XYZ-123456', 'placeholder', 'woocommerce'),
            'required' => true,
            'class' => array('form-row-wide'),
            'clear' => true,
            'type' => 'text',
        );
    
        return $fields;
    }

    public function add_license_field_to_user_edit($user)
    {
        $license = get_user_meta($user->ID, 'license', true);
        ?>
        <h3><?php _e('License', 'woocommerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="license"><?php _e('License', 'woocommerce'); ?></label></th>
                <td>
                    <input type="text" name="license" id="license" value="<?php echo esc_attr($license); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_license_field_on_user_update($user_id)
    {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'license', sanitize_text_field($_POST['license']));
        }
    }
}