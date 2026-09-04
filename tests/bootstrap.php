<?php
/**
 * Minimal PHPUnit bootstrap for pure unit tests.
 *
 * These tests do not load a full WordPress test suite and never call real CRM APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'FORMCOURIER_CRM_TESTING' ) ) {
    define( 'FORMCOURIER_CRM_TESTING', true );
}

if ( ! defined( 'FORMCOURIER_CRM_OPTION_NAME' ) ) {
    define( 'FORMCOURIER_CRM_OPTION_NAME', 'formcourier_crm_settings' );
}

if ( ! defined( 'FORMCOURIER_CRM_VERSION' ) ) {
    define( 'FORMCOURIER_CRM_VERSION', '1.0.1' );
}

if ( ! defined( 'FORMCOURIER_CRM_LICENSE_OPTION_NAME' ) ) {
    define( 'FORMCOURIER_CRM_LICENSE_OPTION_NAME', 'formcourier_crm_license' );
}

if ( ! defined( 'FORMCOURIER_CRM_PRODUCT_SLUG' ) ) {
    define( 'FORMCOURIER_CRM_PRODUCT_SLUG', 'formcourier-crm' );
}

if ( ! defined( 'FORMCOURIER_CRM_BASENAME' ) ) {
    define( 'FORMCOURIER_CRM_BASENAME', 'formcourier-crm/formcourier-crm.php' );
}

if ( ! defined( 'FORMCOURIER_CRM_URL' ) ) {
    define( 'FORMCOURIER_CRM_URL', 'https://example.test/wp-content/plugins/formcourier-crm/' );
}

if ( ! defined( 'FORMCOURIER_CRM_PRO_URL' ) ) {
    define( 'FORMCOURIER_CRM_PRO_URL', 'https://formcourier.site/' );
}



if ( ! defined( 'FORMCOURIER_CRM_DB_VERSION' ) ) {
    define( 'FORMCOURIER_CRM_DB_VERSION', '1.2.3' );
}

if ( ! defined( 'FORMCOURIER_CRM_QUEUE_DB_VERSION' ) ) {
    define( 'FORMCOURIER_CRM_QUEUE_DB_VERSION', '1.2.2' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'AUTH_KEY' ) ) {
    define( 'AUTH_KEY', 'unit-test-auth-key' );
}

if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
    define( 'SECURE_AUTH_KEY', 'unit-test-secure-auth-key' );
}

if ( ! defined( 'LOGGED_IN_KEY' ) ) {
    define( 'LOGGED_IN_KEY', 'unit-test-logged-in-key' );
}

if ( ! defined( 'NONCE_KEY' ) ) {
    define( 'NONCE_KEY', 'unit-test-nonce-key' );
}

if ( ! defined( 'AUTH_SALT' ) ) {
    define( 'AUTH_SALT', 'unit-test-auth-salt' );
}

if ( ! defined( 'SECURE_AUTH_SALT' ) ) {
    define( 'SECURE_AUTH_SALT', 'unit-test-secure-auth-salt' );
}

if ( ! defined( 'LOGGED_IN_SALT' ) ) {
    define( 'LOGGED_IN_SALT', 'unit-test-logged-in-salt' );
}

if ( ! defined( 'NONCE_SALT' ) ) {
    define( 'NONCE_SALT', 'unit-test-nonce-salt' );
}

$GLOBALS['formcourier_crm_test_options'] = [];
$GLOBALS['formcourier_crm_test_current_time'] = 1700000000;
$GLOBALS['formcourier_crm_test_scheduled_events'] = [];
$GLOBALS['formcourier_crm_test_transients'] = [];
$GLOBALS['formcourier_crm_test_site_transients'] = [];
$GLOBALS['formcourier_crm_test_downloads'] = [];
$GLOBALS['formcourier_crm_test_download_callback'] = null;
$GLOBALS['formcourier_crm_test_settings_errors'] = [];
$GLOBALS['formcourier_crm_test_actions'] = [];
$GLOBALS['formcourier_crm_test_locale'] = 'en_US';
$GLOBALS['formcourier_crm_encryption_test_failures'] = [];
$GLOBALS['formcourier_crm_test_enqueued_scripts'] = [];
$GLOBALS['formcourier_crm_test_localized_scripts'] = [];
$GLOBALS['formcourier_crm_test_wp_consent_type'] = false;
$GLOBALS['formcourier_crm_test_wp_consents'] = [];
$GLOBALS['formcourier_crm_test_privacy_policy_content'] = [];
$GLOBALS['formcourier_crm_test_is_ssl'] = true;
$GLOBALS['formcourier_crm_test_home_url'] = 'https://example.test';
$GLOBALS['formcourier_crm_test_http_requests'] = [];
$GLOBALS['formcourier_crm_test_http_callback'] = null;

final class FormCourier_CRM_Test_WPDB {
    public $prefix = 'wp_';
    public $queries = [];
    public $last_query = '';
    public $query_result = 1;
    public $get_var_result = 0;
    public $get_row_result = null;
    public $get_results_result = [];
    public $insert_id = 0;
    public $last_insert_table = '';
    public $last_insert_data = [];
    public $last_update_table = '';
    public $last_update_data = [];
    public $last_update_where = [];
    public $update_count = 0;
    public $insert_count = 0;
    public $delete_count = 0;
    public $last_delete_table = '';

    public function prepare( string $query, ...$args ): string {
        if ( 1 === count( $args ) && is_array( $args[0] ) ) {
            $args = $args[0];
        }

        foreach ( $args as $arg ) {
            if ( preg_match( '/%i/', $query ) ) {
                $replacement = '`' . str_replace( '`', '``', (string) $arg ) . '`';
                $query       = preg_replace( '/%i/', $replacement, $query, 1 );
                continue;
            }

            if ( is_int( $arg ) || is_float( $arg ) || ctype_digit( (string) $arg ) ) {
                $replacement = (string) $arg;
            } else {
                $replacement = "'" . addslashes( (string) $arg ) . "'";
            }

            $query = preg_replace( '/%[sd]/', $replacement, $query, 1 );
        }

        return $query;
    }

    public function query( string $query ) {
        $this->last_query = $query;
        $this->queries[]  = $query;

        return $this->query_result;
    }

    public function insert( string $table, array $data, array $format = [] ) {
        $this->insert_count++;
        $this->insert_id++;
        $this->last_insert_table = $table;
        $this->last_insert_data  = $data;

        return 1;
    }

    public function update( string $table, array $data, array $where, array $format = [], array $where_format = [] ) {
        $this->update_count++;
        $this->last_update_table = $table;
        $this->last_update_data  = $data;
        $this->last_update_where = $where;

        return 1;
    }

    public function delete( string $table, array $where, array $where_format = [] ) {
        $this->delete_count++;
        $this->last_delete_table = $table;

        return 1;
    }

    public function get_var( string $query ) {
        $this->last_query = $query;
        $this->queries[]  = $query;

        return $this->get_var_result;
    }

    public function get_row( string $query, $output = OBJECT ) {
        $this->last_query = $query;
        $this->queries[]  = $query;

        return $this->get_row_result;
    }

    public function get_results( string $query, $output = OBJECT ): array {
        $this->last_query = $query;
        $this->queries[]  = $query;

        return $this->get_results_result;
    }

    public function esc_like( string $text ): string {
        return addcslashes( $text, '_%\\' );
    }

    public function get_charset_collate(): string {
        return 'DEFAULT CHARSET=utf8mb4';
    }
}

$GLOBALS['wpdb'] = new FormCourier_CRM_Test_WPDB();

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return array_key_exists( $option, $GLOBALS['formcourier_crm_test_options'] )
            ? $GLOBALS['formcourier_crm_test_options'][ $option ]
            : $default;
    }
}

if ( ! function_exists( 'wp_get_consent_type' ) ) {
    function wp_get_consent_type() {
        return $GLOBALS['formcourier_crm_test_wp_consent_type'];
    }
}

if ( ! function_exists( 'wp_has_consent' ) ) {
    function wp_has_consent( $category ) {
        return array_key_exists( $category, $GLOBALS['formcourier_crm_test_wp_consents'] )
            ? (bool) $GLOBALS['formcourier_crm_test_wp_consents'][ $category ]
            : true;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        $GLOBALS['formcourier_crm_test_options'][ $option ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        unset( $GLOBALS['formcourier_crm_test_options'][ $option ] );
        return true;
    }
}

if ( ! function_exists( 'add_settings_error' ) ) {
    function add_settings_error( $setting, $code, $message, $type = 'error' ) {
        $GLOBALS['formcourier_crm_test_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
    }
}


if ( ! function_exists( 'settings_errors' ) ) {
    function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {
        foreach ( $GLOBALS['formcourier_crm_test_settings_errors'] ?? [] as $error ) {
            if ( '' !== (string) $setting && (string) ( $error['setting'] ?? '' ) !== (string) $setting ) {
                continue;
            }

            $type = sanitize_key( (string) ( $error['type'] ?? 'error' ) );
            echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>'
                . esc_html( (string) ( $error['message'] ?? '' ) )
                . '</p></div>';
        }
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) {
        return $GLOBALS['formcourier_crm_test_transients'][ $transient ] ?? false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) {
        $GLOBALS['formcourier_crm_test_transients'][ $transient ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $transient ) {
        unset( $GLOBALS['formcourier_crm_test_transients'][ $transient ] );
        return true;
    }
}

if ( ! function_exists( 'get_site_transient' ) ) {
    function get_site_transient( $transient ) {
        return $GLOBALS['formcourier_crm_test_site_transients'][ $transient ] ?? false;
    }
}

if ( ! function_exists( 'set_site_transient' ) ) {
    function set_site_transient( $transient, $value, $expiration = 0 ) {
        $GLOBALS['formcourier_crm_test_site_transients'][ $transient ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_site_transient' ) ) {
    function delete_site_transient( $transient ) {
        unset( $GLOBALS['formcourier_crm_test_site_transients'][ $transient ] );
        return true;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $message;

        public function __construct( string $code = '', string $message = '' ) {
            $this->message = $message;
        }

        public function get_error_message(): string {
            return $this->message;
        }
    }
}


if ( ! function_exists( 'wp_remote_post' ) ) {
    function wp_remote_post( $url, $args = [] ) { return wp_remote_request( $url, array_merge( [ 'method' => 'POST' ], $args ) ); }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = [] ) { return wp_remote_request( $url, array_merge( [ 'method' => 'GET' ], $args ) ); }
}
if ( ! function_exists( 'wp_safe_remote_post' ) ) {
    function wp_safe_remote_post( $url, $args = [] ) {
        return wp_remote_request( $url, $args );
    }
}

if ( ! function_exists( 'wp_remote_request' ) ) {
    function wp_remote_request( $url, $args = [] ) {
        $request = [
            'url'  => (string) $url,
            'args' => is_array( $args ) ? $args : [],
        ];

        $GLOBALS['formcourier_crm_test_http_requests'][] = $request;
        $callback = $GLOBALS['formcourier_crm_test_http_callback'] ?? null;

        if ( is_callable( $callback ) ) {
            return $callback( $request['url'], $request['args'] );
        }

        return new WP_Error( 'unexpected_http_request', 'Unexpected HTTP request in unit test.' );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $value ) {
        return $value instanceof WP_Error;
    }
}

if ( ! function_exists( 'download_url' ) ) {
    function download_url( $url, $timeout = 300, $signature_verification = false ) {
        $GLOBALS['formcourier_crm_test_downloads'][] = [
            'url' => (string) $url,
            'timeout' => (int) $timeout,
        ];
        $callback = $GLOBALS['formcourier_crm_test_download_callback'] ?? null;
        if ( is_callable( $callback ) ) {
            return $callback( (string) $url, (int) $timeout );
        }
        return new WP_Error( 'unexpected_download', 'Unexpected download in unit test.' );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
    }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = [] ) {
        if ( is_object( $args ) ) {
            $args = get_object_vars( $args );
        }

        if ( ! is_array( $args ) ) {
            $args = [];
        }

        return array_merge( $defaults, $args );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'wp_unslash', $value );
        }

        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }

        $value = wp_unslash( (string) $value );
        $value = strip_tags( $value );
        $value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

        return trim( $value );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }

        $value = wp_unslash( (string) $value );
        $value = strip_tags( $value );

        return trim( $value );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        $email = trim( (string) $email );
        $email = preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', $email );

        return $email;
    }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        if ( -1 === $component ) {
            return parse_url( $url );
        }

        return parse_url( $url, $component );
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) {
        return abs( (int) $value );
    }
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
    function wp_generate_uuid4() {
        return '123e4567-e89b-42d3-a456-426614174000';
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
        return json_encode( $data, $flags, $depth );
    }
}

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) {
        return 'unit-test-wp-salt-' . (string) $scheme;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook_name, $value ) {
        return $value;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        if ( 'timestamp' === $type ) {
            return (int) $GLOBALS['formcourier_crm_test_current_time'];
        }

        if ( 'mysql' === $type ) {
            return date( 'Y-m-d H:i:s', (int) $GLOBALS['formcourier_crm_test_current_time'] );
        }

        return (int) $GLOBALS['formcourier_crm_test_current_time'];
    }
}


if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        if ( 'version' === $show ) {
            return '6.5.0';
        }

        return '';
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        return (bool) ( $GLOBALS['formcourier_crm_test_is_ssl'] ?? true );
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        return (string) ( $GLOBALS['formcourier_crm_test_home_url'] ?? 'https://example.test' ) . $path;
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $value ) {
        return rtrim( (string) $value, '/\\' ) . '/';
    }
}


if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
    function wp_add_privacy_policy_content( $plugin_name, $policy_text ) {
        $GLOBALS['formcourier_crm_test_privacy_policy_content'][] = [
            'plugin_name' => (string) $plugin_name,
            'policy_text' => (string) $policy_text,
        ];

        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action() {
        $GLOBALS['formcourier_crm_test_actions'][] = func_get_args();
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter() {
        return true;
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $args = false ) {
        $GLOBALS['formcourier_crm_test_enqueued_scripts'][ (string) $handle ] = [
            'src'  => (string) $src,
            'deps' => is_array( $deps ) ? $deps : [],
            'ver'  => $ver,
            'args' => $args,
        ];
        return true;
    }
}

if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {
        $GLOBALS['formcourier_crm_test_localized_scripts'][ (string) $handle ][ (string) $object_name ] = $l10n;
        return true;
    }
}

if ( ! function_exists( 'dbDelta' ) ) {
    function dbDelta( $query ) {
        global $wpdb;
        $wpdb->query( $query );
        return [];
    }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook ) {
        return $GLOBALS['formcourier_crm_test_scheduled_events'][ $hook ] ?? false;
    }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook ) {
        $GLOBALS['formcourier_crm_test_scheduled_events'][ $hook ] = $timestamp;
        return true;
    }
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
    function wp_unschedule_event( $timestamp, $hook ) {
        unset( $GLOBALS['formcourier_crm_test_scheduled_events'][ $hook ] );
        return true;
    }
}

if ( ! function_exists( 'get_locale' ) ) {
    function get_locale() {
        return $GLOBALS['formcourier_crm_test_locale'] ?? 'en_US';
    }
}

if ( ! function_exists( 'determine_locale' ) ) {
    function determine_locale() {
        return $GLOBALS['formcourier_crm_test_locale'] ?? 'en_US';
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

if ( ! function_exists( 'formcourier_crm_test_translation_catalog' ) ) {
    function formcourier_crm_test_translation_catalog( $locale ) {
        static $catalogues = [];

        $locale = strtolower( (string) $locale );
        $key    = 0 === strpos( $locale, 'uk' ) ? 'uk' : ( 0 === strpos( $locale, 'ru' ) ? 'ru_RU' : 'en_US' );

        if ( 'en_US' === $key ) {
            return [];
        }

        if ( isset( $catalogues[ $key ] ) ) {
            return $catalogues[ $key ];
        }

        $file = __DIR__ . '/fixtures/i18n-' . $key . '.json';
        $data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : [];

        $catalogues[ $key ] = is_array( $data ) ? $data : [];

        return $catalogues[ $key ];
    }
}

if ( ! function_exists( 'translate' ) ) {
    function translate( $text, $domain = 'default' ) {
        if ( 'formcourier-crm' !== $domain ) {
            return $text;
        }

        $catalogue = formcourier_crm_test_translation_catalog( determine_locale() );

        return $catalogue[ $text ] ?? $text;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return translate( $text, $domain );
    }
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
        return true;
    }
}



if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return htmlspecialchars( (string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $display = true ) {
        $result = (string) $checked === (string) $current ? ' checked="checked"' : '';
        if ( $display ) {
            echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        return $result;
    }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current = true, $display = true ) {
        $result = (string) $selected === (string) $current ? ' selected="selected"' : '';
        if ( $display ) {
            echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        return $result;
    }
}

if ( ! function_exists( 'disabled' ) ) {
    function disabled( $disabled, $current = true, $display = true ) {
        $result = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';
        if ( $display ) {
            echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        return $result;
    }
}

if ( ! function_exists( 'settings_fields' ) ) {
    function settings_fields( $option_group ) {
        echo '<input type="hidden" name="option_page" value="' . esc_attr( (string) $option_group ) . '">';
    }
}

if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
        $label = null === $text ? 'Save Changes' : (string) $text;
        $button = '<button type="submit" name="' . esc_attr( (string) $name ) . '" class="button button-' . esc_attr( (string) $type ) . '">' . esc_html( $label ) . '</button>';
        if ( $wrap ) {
            echo '<p class="submit">' . $button . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }
        echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}


if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
        $field = '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="test-nonce">';
        if ( $display ) {
            echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        return $field;
    }
}

if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = null, $position = null ) {
        $GLOBALS['formcourier_crm_test_submenu_pages'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
        return $menu_slug;
    }
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
    function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
        $separator = str_contains( (string) $actionurl, '?' ) ? '&' : '?';
        return (string) $actionurl . $separator . rawurlencode( (string) $name ) . '=test-nonce';
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return esc_html( translate( $text, $domain ) );
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = 'default' ) {
        return esc_attr( translate( $text, $domain ) );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo esc_html__( $text, $domain );
    }
}


if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return true;
    }
}

if ( ! function_exists( 'check_admin_referer' ) ) {
    function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
        return true;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '' ) {
        throw new RuntimeException( (string) $message );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( array $args, string $url = '' ) {
        $separator = str_contains( $url, '?' ) ? '&' : '?';
        return $url . $separator . http_build_query( $args );
    }
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
    function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
        $GLOBALS['formcourier_crm_test_last_redirect'] = $location;
        return true;
    }
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
        $field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce">';
        if ( $display ) {
            echo $field;
        }
        return $field;
    }
}

if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $display = true ) {
        $result = $checked == $current ? ' checked="checked"' : '';
        if ( $display ) {
            echo $result;
        }
        return $result;
    }
}

if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( $text = 'Save Changes' ) {
        echo '<button type="submit" class="button button-primary">' . esc_html( $text ) . '</button>';
    }
}



if ( ! function_exists( 'wpautop' ) ) {
    function wpautop( $value, $br = true ) { return (string) $value; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $value ) { return (string) $value; }
}
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-i18n.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-encryption.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-form-data-sanitizer.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-submission-meta.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-privacy.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-logger.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-settings.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-provider-router.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-crm-dispatcher.php';
require_once dirname( __DIR__ ) . '/includes/crm-providers/class-formcourier-crm-hubspot-provider.php';
require_once dirname( __DIR__ ) . '/includes/crm-providers/class-formcourier-crm-pipedrive-provider.php';
require_once dirname( __DIR__ ) . '/includes/form-providers/class-formcourier-crm-cf7-provider.php';
require_once dirname( __DIR__ ) . '/includes/form-providers/class-formcourier-crm-wpforms-provider.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-logs-page.php';
require_once dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-plugin.php';

if ( ! function_exists( 'paginate_links' ) ) {
    function paginate_links( $args = [] ) {
        return '';
    }
}
