<?php
/*
Plugin Name: Disposable Email Checker (API-Aries)
Plugin URI: https://support.api-aries.online/hc/articles/1/3/3/email-checker
Description: WordPress plugin to check email for disposable emails using the "api-aries" API.
Version: 1.2
Author: API-Aries Team
Author URI: https://api-aries.online/
License: GPL2
*/

// Add a settings page to enter API token
add_action('admin_menu', 'email_checker_plugin_menu');
register_activation_hook(__FILE__, 'email_checker_activate');

function email_checker_plugin_menu() {
    add_options_page(
        'Disposable Email Checker Plugin Settings',
        'Disposable Email Checker (API-Aries)',
        'manage_options',
        'email_checker_settings',
        'email_checker_settings_page'
    );
}

function email_checker_activate() {
    $api_token = get_option('email_checker_api_token');
    if (!$api_token || !email_checker_is_valid_token($api_token)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('The Disposable Email Checker plugin requires a valid API token to be activated. Please enter a valid API token in the plugin settings.');
    }
}

function email_checker_is_valid_token($api_token) {
    $api_url = 'https://api.api-aries.online/v1/checkers/proxy/email/?email=valid@example.com';
    $headers = array(
        'APITOKEN: ' . $api_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['error_code']) && $data['error_code'] === 'XR12') {
        return false;
    }

    return true;
}

function email_checker_settings_page() {
    // Check if user has necessary permission
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings on form submission
    if (isset($_POST['email_checker_settings_submit'])) {
        check_admin_referer('email_checker_save_settings');
        $api_token = sanitize_text_field($_POST['email_checker_api_token']);
        $enabled = isset($_POST['email_checker_enabled']) ? '1' : '0';
        $disposable_email_message = sanitize_textarea_field($_POST['email_checker_disposable_email']);
        update_option('email_checker_api_token', $api_token);
        update_option('email_checker_enabled', $enabled);
        update_option('email_checker_disposable_email_message', $disposable_email_message);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';

        // Validate the API token
        if (!email_checker_is_valid_token($api_token)) {
            echo '<div class="notice notice-error"><p>Invalid API token. Please enter a valid token. <a href="https://support.api-aries.online/hc/articles/6/7/15/api-error-codes" target="_blank">Learn more</a>.</p></div>';
        }
    }

    $disposable_email_message = get_option('email_checker_disposable_email_message', 'The email address provided is not valid. Please provide a valid email address. Disposable emails are not permitted.');
    ?>

    <div class="wrap">
        <h1>Email Checker Plugin Settings</h1>
        
        <p>If you do not already possess a token, you have the option to <a href="https://forums.api-aries.online/" target="_blank">Sign up</a> and obtain one.</p>
        <h2>Token Types - info</h2>
        <p>Free - learn more - <a href="https://forums.api-aries.online/subscriptions/" target="_blank">Subscriptions</a></p> 
        <p>Paid - learn more - <a href="https://forums.api-aries.online/subscriptions/" target="_blank">Subscriptions</a></p>

        <form method="post" action="">
            <?php wp_nonce_field('email_checker_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">API Token</th>
                    <td>
                        <input type="text" name="email_checker_api_token" value="<?php echo esc_attr(get_option('email_checker_api_token')); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable Email Checker</th>
                    <td>
                        <input type="checkbox" name="email_checker_enabled" value="1" <?php checked(get_option('email_checker_enabled'), '1'); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Disposable Email Message</th>
                    <td>
                        <textarea name="email_checker_disposable_email" rows="5" cols="50"><?php echo esc_textarea($disposable_email_message); ?></textarea>
                    </td>
                </tr>
            </table>
            <p>Add your API token. This should be found on the <a href="https://dashboard.api-aries.online">Dashboard</a>.</p>

            <?php submit_button('Save Settings', 'primary', 'email_checker_settings_submit'); ?>
        </form>
    </div>

    <?php
}

// Hook the function to validate email before registration
add_action('registration_errors', 'email_checker_validate_email', 10, 3);
add_action('user_register', 'email_checker_validate_existing_user_email');
add_action('profile_update', 'email_checker_validate_existing_user_email', 10, 2);
add_filter('preprocess_comment', 'email_checker_validate_comment_email');

function email_checker_validate_email($errors, $sanitized_user_login, $user_email) {
    if (get_option('email_checker_enabled') !== '1') {
        return $errors;
    }

    $validation_error = email_checker_check_email($user_email);
    if ($validation_error) {
        $errors->add('email_invalid', __($validation_error));
    }

    return $errors;
}

function email_checker_validate_existing_user_email($user_id) {
    if (get_option('email_checker_enabled') !== '1') {
        return;
    }

    $user = get_userdata($user_id);
    $user_email = $user->user_email;

    $validation_error = email_checker_check_email($user_email);
    if ($validation_error) {
        wp_die(__($validation_error));
    }
}

function email_checker_validate_comment_email($commentdata) {
    if (get_option('email_checker_enabled') !== '1') {
        return $commentdata;
    }

    $user_email = $commentdata['comment_author_email'];

    $validation_error = email_checker_check_email($user_email);
    if ($validation_error) {
        wp_die(__($validation_error));
    }

    return $commentdata;
}

function email_checker_check_email($email) {
    $api_token = get_option('email_checker_api_token');
    $disposable_email_message = get_option('email_checker_disposable_email_message', 'The email address provided is not valid. Please provide a valid email address. Disposable emails are not permitted.');

    $default_messages = array(
        'rate_limit_exceeded' => 'API rate limit exceeded. https://support.api-aries.online/hc/articles/6/7/15/api-error-codes',
        'invalid_token' => 'Invalid API token. Please check your API token and try again.',
        'missing_token' => 'API token missing. Please add your API token in the plugin settings.',
        'daily_limit_exceeded' => 'Exceeded daily request limit. Please upgrade your plan or try again tomorrow.',
        'service_unavailable' => 'Service unavailable. Please try again later.',
        'unknown_error' => 'An unknown error occurred. Please try again later.'
    );

    $api_url = 'https://api.api-aries.online/v1/checkers/proxy/email/?email=' . urlencode($email);

    $headers = array(
        'APITOKEN: ' . $api_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return $default_messages['unknown_error'];
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $default_messages['unknown_error'];
    }

    if (isset($data['error_code'])) {
        $error_code = $data['error_code'];
        $support_link = isset($data['message']) ? $data['message'] : '';

        switch ($error_code) {
            case 'QR89':
                return $default_messages['rate_limit_exceeded'];
            case 'XR12':
                return $default_messages['invalid_token'];
            case '100':
                return $default_messages['invalid_token'];
            case '101':
                return $default_messages['missing_token'];
            case '102':
                return $default_messages['daily_limit_exceeded'];
            case '103':
                return $default_messages['service_unavailable'];
            default:
                return $default_messages['unknown_error'];
        }
    }

    if (isset($data['disposable']) && $data['disposable'] === 'yes') {
        return $disposable_email_message;
    }

    return null;
}
?>
