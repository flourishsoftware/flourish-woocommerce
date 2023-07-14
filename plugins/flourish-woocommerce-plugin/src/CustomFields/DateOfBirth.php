<?php

namespace FlourishWooCommercePlugin\CustomFields;

defined( 'ABSPATH' ) || exit;

class DateOfBirth
{
    public function register_hooks()
    {
        // Needed to handle DOB in various locations
        add_action('woocommerce_register_form', [$this, 'add_dob_field_to_registration_form']);
        add_action('woocommerce_register_post', [$this, 'validate_dob_field'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'save_dob_field']);
        add_action('woocommerce_edit_account_form', [$this, 'add_dob_field_to_edit_account_form']);
        add_action('woocommerce_save_account_details', [$this, 'save_dob_field']);
        add_filter('woocommerce_checkout_fields', [$this, 'add_dob_field_to_checkout_form']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_dob_field']);
        add_action('show_user_profile', [$this, 'add_dob_field_to_user_edit']);
        add_action('edit_user_profile', [$this, 'add_dob_field_to_user_edit']);
        add_action('personal_options_update', [$this, 'save_dob_field_on_user_update']);
        add_action('edit_user_profile_update', [$this, 'save_dob_field_on_user_update']);
    }

    public function add_dob_field_to_registration_form()
    {
        ?>
        <p class="form-row form-row-wide">
            <label for="dob"><?php _e('Date of Birth', 'woocommerce'); ?> <span class="required">*</span></label>
            <input type="date" class="input-text" name="dob" id="dob" value="<?php if (!empty($_POST['dob'])) echo esc_attr($_POST['dob']); ?>" />
        </p>
        <?php
    }

    public function validate_dob_field($username, $email, $validation_errors)
    {
        if (empty($_POST['dob'])) {
            $validation_errors->add('dob_error', __('Date of Birth is required.', 'woocommerce'));
        }
        return $validation_errors;
    }

    public function save_dob_field($customer_id)
    {
        if (isset($_POST['dob'])) {
            update_user_meta($customer_id, 'dob', sanitize_text_field($_POST['dob']));
        }
    }

    public function add_dob_field_to_edit_account_form()
    {
        $user_id = get_current_user_id();
        $current_dob = get_user_meta($user_id, 'dob', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="dob"><?php _e('Date of Birth', 'woocommerce'); ?> <span class="required">*</span></label>
            <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="dob" id="dob" value="<?php echo esc_attr($current_dob); ?>" />
        </p>
        <?php
    }

    public function add_dob_field_to_checkout_form($fields)
    {
        $fields['billing']['dob'] = array(
            'label' => __('Date of Birth', 'woocommerce'),
            'placeholder' => _x('Date of Birth', 'placeholder', 'woocommerce'),
            'required' => true,
            'class' => array('form-row-wide'),
            'clear' => true,
            'type' => 'date',
        );
    
        return $fields;
    }

    public function add_dob_field_to_user_edit($user)
    {
        $dob = get_user_meta($user->ID, 'dob', true);
        ?>
        <h3><?php _e('Date of Birth', 'woocommerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="dob"><?php _e('Date of Birth', 'woocommerce'); ?></label></th>
                <td>
                    <input type="date" name="dob" id="dob" value="<?php echo esc_attr($dob); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    function save_dob_field_on_user_update($user_id)
    {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'dob', sanitize_text_field($_POST['dob']));
        }
    }
}
