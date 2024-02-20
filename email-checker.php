<?php
/*
Plugin Name: Disposable Email Checker (API-Aries)
Plugin URI: https://support.api-aries.online/hc/articles/1/3/3/email-checker
Description: WordPress plugin to check email for disposable emails using the "api-aries" API.
Version: 1.0
Author: API-Aries Team
Author URI: https://api-aries.online/
License: GPL2
*/

add_action( 'admin_menu', 'email_checker_plugin_menu' );

function email_checker_plugin_menu() {
    add_options_page(
        'Disposable Email Checker Plugin Settings',
        'Disposable Email Checker (API-Aries) ',
        'manage_options',
        'email_checker_settings',
        'email_checker_settings_page'
    );
}

function email_checker_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['email_checker_settings_submit'] ) ) {
        update_option( 'email_checker_api_token', $_POST['email_checker_api_token'] );
        update_option( 'email_checker_type', $_POST['email_checker_type'] );
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
    ?>

    <div class="wrap">
        <h1>Disposable Email Checker - Plugin Settings</h1>
        
        <p>If you do not already possess a token, you have the option to <a href="https://forums.api-aries.online/" target="_blank">Sign up </a> and obtain one.</p>
        <h2>Token Types - info</h2>
        <p>Free - 5K Request per day</p>
        <p>Paid - Unlimited Request</p>

        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">API Token</th>
                    <td>
                        <input type="text" name="email_checker_api_token" value="<?php echo esc_attr( get_option( 'email_checker_api_token' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Type</th>
                    <td>
                        <select name="email_checker_type">
                            <option value="2" <?php selected( get_option( 'email_checker_type' ), '2' ); ?>>Free</option>
                            <option value="1" <?php selected( get_option( 'email_checker_type' ), '1' ); ?>>Paid</option>
                            
                        </select>
                    </td>
                </tr>
            </table>
            <p>Add your API token. This should be found on the <a href="https://dashboard.api-aries.online">Dashboard</a>, along with your token type.</p>

            <?php submit_button( 'Save Settings', 'primary', 'email_checker_settings_submit' ); ?>
        </form>
        
        
    </div>

    <?php
}

add_action( 'registration_errors', 'email_checker_validate_email', 10, 3 );

function email_checker_validate_email( $errors, $sanitized_user_login, $user_email ) {
    // Get API token and type from plugin settings
    $api_token = get_option( 'email_checker_api_token' );
    $type = get_option( 'email_checker_type' );

    // API Endpoint URL
    $api_url = 'https://api.api-aries.online/v1/checkers/proxy/email/?email=' . $user_email;

    $headers = array(
        'Type: ' . $type,
        'APITOKEN: ' . $api_token
    );

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $api_url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    $response = curl_exec( $ch );
    curl_close( $ch );

    $data = json_decode( $response, true );

    if ( isset( $data['disposable'] ) && $data['disposable'] === 'yes' ) {
        $errors->add( 'email_invalid', __( 'The email address provided is not valid. Please provide a valid email address. Disposable emails are not permitted.' ) );
    }

    return $errors;
}

?>
