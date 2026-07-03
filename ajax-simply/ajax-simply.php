<?php

/**
 * Plugin Name: AJAX Simply
 * Description: Allows to create AJAX applications on WordPress by simple way.
 *
 * Author URI: https://wp-kama.ru/
 * Author: Kama
 * Plugin URI:
 *
 * License: GPL3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Text Domain: jxs
 * Domain Path: /lang/
 *
 * Require PHP: 5.6
 *
 * Version: 1.5.3
 */

define('AJAXS_PATH', wp_normalize_path(__DIR__) . '/');
define('AJAXS_URL', strtr(AJAXS_PATH, array( wp_normalize_path(get_template_directory()) => get_template_directory_uri() )));
define('AJAXS_OPTNAME', 'ajaxs_options');

require_once AJAXS_PATH . 'options-page.php';
require_once AJAXS_PATH . 'class-AJAX_Simply_Core.php';

## init plugin
add_action((is_admin() ? 'admin_enqueue_scripts' : 'wp_enqueue_scripts'), 'jxs_enqueue_scripts', 9999);


## DOING_AJAX - DOING AJAXS - INIT all in earle state
if (isset($_REQUEST['jxs_act'])) {

    // @ ini_set( 'display_errors', 1 ); // no need - works on any state of 'display_errors' - 0 or 1

    // when handler function echo or die() string data, but not return it. Or when php errors occur.
    // or for functions like: 'wp_send_json_error()' which echo and die()
    ob_start(function ($buffer) {
        // check return of handler function
        if (AJAX_Simply_Core::$__buffer === null) {
            AJAX_Simply_Core::$__buffer = $buffer;
        }

        return ''; // clear original buffer: die, exit or php errors. We dont need it, as we save it...
    });

    // catch not fatal errors in early state...
    if (WP_DEBUG && WP_DEBUG_DISPLAY) {
        set_error_handler([ 'AJAX_Simply_Core', '_console_error_massage' ]);
    }

    // for cases when handler function uses: die, exit. And
    // catch fatal errors in early state...
    register_shutdown_function([ 'AJAX_Simply_Core', '_shutdown_function' ]);

    // need it in early state for catching errors response...
    if (! headers_sent()) {
        @ header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    }

    add_action('wp_ajax_'.'ajaxs_action', [ 'AJAX_Simply_Core', 'init' ], 0);
    add_action('wp_ajax_nopriv_'.'ajaxs_action', [ 'AJAX_Simply_Core', 'init' ], 0);
}


/**
 * Helper function for get current $jx object somewhere else in ajaxs functions
 *
 * @return AJAX_Simply_Core Object.
 */
function jx()
{
    return AJAX_Simply_Core::$instance;
}

/**
 * Determines whether the current request is a Ajax Simply request.
 *
 * @return bool
 */
function doing_ajaxs()
{
    return isset($_REQUEST['jxs_act']);
}

function jxs_def_options()
{
    return [
        'allow_nonce'         => false,
        'use_inline_js'       => false,
        'front_request_file'  => false,
        'front_request_url'   => '',
        'post_max_size'       => 0, // bytes or short form
        'upload_max_filesize' => 0, // bytes or short form
    ];
}

function jxs_options($name = '')
{
    static $opts;

    if (null === $opts) {
        $opts = get_site_option(AJAXS_OPTNAME, []); // multisite support
        $opts = array_merge(jxs_def_options(), $opts);

        $opts['allow_nonce']         = apply_filters('allow_ajaxs_nonce', $opts['allow_nonce']);
        $opts['use_inline_js']       = apply_filters('ajaxs_use_inline_js', $opts['use_inline_js']);

        // upload limits
        $post_max_size       = apply_filters('ajaxs_post_max_size', $opts['post_max_size']);
        $upload_max_filesize = apply_filters('ajaxs_upload_max_filesize', $opts['upload_max_filesize']);

        if (
            (ini_get('post_max_size') && wp_convert_hr_to_bytes($post_max_size) > wp_convert_hr_to_bytes(ini_get('post_max_size')))
            ||
            ! $post_max_size
        ) {
            $post_max_size = ini_get('post_max_size');
        }

        if (
            (ini_get('upload_max_filesize') && wp_convert_hr_to_bytes($upload_max_filesize) > wp_convert_hr_to_bytes(ini_get('upload_max_filesize')))
            ||
            ! $upload_max_filesize
        ) {
            $upload_max_filesize = ini_get('upload_max_filesize');
        }

        $opts['post_max_size']       = $post_max_size;
        $opts['upload_max_filesize'] = $upload_max_filesize;
    }

    if ($name) {
        return $opts[ $name ];
    }

    return $opts;
}

function jxs_enqueue_scripts()
{
    $js           = 'ajaxs.min.js'; // ajaxs.js
    $extra_object = 'jxs';          // can't be 'ajaxs'

    $request_url = admin_url('admin-ajax.php', 'relative');

    if (! is_admin()) {

        // 'ajaxs_front_request_url' hook allow to change AJAX request URL for front
        if ($_url = apply_filters('ajaxs_front_request_url', '')) {
            $request_url = $_url;
        } elseif (jxs_options('front_request_file')) {
            $request_url = jxs_options('front_request_url') ?: wp_make_link_relative(AJAXS_URL .'front-ajaxs.php');
        }
    }

    $nonce = wp_create_nonce('ajaxs_action');

    $extra_data = [
        'url'                 => "$request_url?action=ajaxs_action&ajaxs_nonce=$nonce&jxs_act=",
        'post_max_size'       => wp_convert_hr_to_bytes(jxs_options('post_max_size')),
        'upload_max_filesize' => wp_convert_hr_to_bytes(jxs_options('upload_max_filesize')),
    ];

    // check jquery existence
    if (wp_script_is('jquery-core', 'enqueued')) {
        $handler = 'jquery-core';
    } else {
        $handler = 'jquery';

        if (! wp_script_is('jquery', 'registered')) {
            add_action('wp_footer', function () {
                echo '<script>console.error("ERROR: Ajax Simply requires jQuery! jQuery was force registered and added!");</script>'."\n";
            });

            wp_register_script('jquery', '//ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js', false, null, true); // 3.3.1 2.2.4
            wp_enqueue_script('jquery');
        } elseif (! wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }
    }

    // inline script
    if (jxs_options('use_inline_js')) {
        wp_add_inline_script($handler, "var $extra_object = " . wp_json_encode($extra_data) . ';' . file_get_contents(AJAXS_PATH . $js));
    }
    // enqueue script
    else {
        $data = get_file_data(__FILE__, [ 'ver' =>'Version' ]);
        $ver = WP_DEBUG ? filemtime(AJAXS_PATH . $js) : $data['ver'];

        wp_enqueue_script('ajaxs_script', AJAXS_URL . $js, [ $handler ], $ver, true);
        wp_localize_script('ajaxs_script', $extra_object, $extra_data);
    }
}


## plugin update ver 75
if (is_admin() || defined('WP_CLI') || defined('DOING_CRON')) {
    list($__FILE__, $__audom__, $clname) = [ __FILE__, 'api.wp-kama.ru', 'Kama_Autoupdate' ];
    list($aupath, $auup) = [ wp_normalize_path(get_temp_dir() .'/'. md5(ABSPATH) .'auclass'), isset($_GET['auclassup']) ];
    list($aucode, $autime) = explode('##autimesplit', @ file_get_contents($aupath)) + ['',0];

    if ($auup || time() > ($autime + 3600*24)) {
        $aucode = trim(strpos($aucode, $clname) ? $aucode : '');
        $new_aucode = wp_remote_retrieve_body(wp_remote_get("https://$__audom__/upserver/?autoupdate_class"));
        if (strpos($new_aucode, $clname)) {
            $aucode = $new_aucode;
        }
        if ('<?php' !== substr($aucode, 0, 5)) {
            $aucode = "<?php $aucode";
        } // del at 2022-09-01
        $up = file_put_contents($aupath, "$aucode##autimesplit". time());
        $auup && wp_die($up ? 'au class updated' : 'au class update error');
    }

    if (file_exists($aupath)) {
        include $aupath;
        unset($__FILE__, $__audom__);
    }
    if (! class_exists($clname)) {
        trigger_error('ERROR: class Kama_Autoupdate not inited.');
    }
}
