<?php 

/*

Plugin Name: Longer Permalinks

Plugin URI: https://github.com/gecon/longer-permalinks/archive/master.zip

Description: This plugin allows longer permalinks by extending slug length (post_name) from default 200 to 3000. 
In a way that is future WordPress core updates compatible, by extending always the current/installed core functionality.
Useful for permalinks using non latin characters in URLs. Long permalinks will now work.

Author: Giannis Economou

Version: 1.13

Author URI: http://www.antithesis.gr

*/

defined( 'ABSPATH' ) OR exit;

define('REDEF_FILE', WP_PLUGIN_DIR."/longer-permalinks/sanitize_override.inc");
register_activation_hook( __FILE__, 'longer_permalinks_plugin_install' );

$last_wp_ver = get_option('longer-permalinks-wpver');
$current_wp_ver = get_bloginfo('version');

$redefined = 0;
if ( !file_exists (REDEF_FILE) || ($last_wp_ver != $current_wp_ver) ) {
	$redefined = redefine_sanitize_title_with_dashes();
} else {
	$redefined = 1;
}

if ($redefined) {
	include(REDEF_FILE);

	// replace the standard filter
	remove_filter( 'sanitize_title', 'sanitize_title_with_dashes' );
	add_filter( 'sanitize_title', 'longer_permalinks_sanitize_title_with_dashes', 10, 3 );

	//update wp version
	update_option( 'longer-permalinks-wpver', $current_wp_ver );
}


function redefine_sanitize_title_with_dashes() {
	if ( !is_writable( dirname(REDEF_FILE) ) ) {
		add_action('admin_notices','longer_permalinks_notice__error_dir_write_access');
		return 0;
	}
	if ( file_exists(REDEF_FILE) && !is_writable( REDEF_FILE ) ) {
	        add_action('admin_notices','longer_permalinks_notice__error_file_write_access');
		return 0;
	}

	$func = new ReflectionFunction('sanitize_title_with_dashes');
	$filename = $func->getFileName();
	$start_line = $func->getStartLine() - 1; 
	$end_line = $func->getEndLine();
	$length = $end_line - $start_line;

	$source = file($filename);
	$body = implode("", array_slice($source, $start_line, $length));

	$body = preg_replace('/function sanitize_title_with_dashes/','function longer_permalinks_sanitize_title_with_dashes',$body);
	$body = preg_replace('/\$title = utf8_uri_encode\(\$title\, 200\);/','$title = utf8_uri_encode($title, 3000);',$body);

	if (strlen($body) > 0) {
		$body = '<' . "?php\n" .$body;
		file_put_contents(REDEF_FILE, $body);
	}
	return 1;
}

function longer_permalinks_notice__error_dir_write_access() {
    echo '<div class="notice notice-error is-dismissible"><p>';
    echo _e('Could not write into plugin directory.') . REDEF_FILE ."<br>";
    echo _e("Plugin Longer Permalinks will not work. Please make plugin directory writable.");
    echo '</p></div>';
}

function longer_permalinks_notice__error_file_write_access() {
    echo '<div class="notice notice-error is-dismissible"><p>';
    echo _e('Could not write file ') . REDEF_FILE ."<br>";
    echo _e("Plugin Longer Permalinks will not work. Please make file writable.");
    echo '</p></div>';
}

function longer_permalinks_plugin_install() {
	global $wpdb;

	if ( !current_user_can( 'activate_plugins' ) ) {
        return;
	}

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	//update posts table field length
	$sql="ALTER TABLE {$wpdb->prefix}posts modify post_name varchar(3000);";
	$wpdb->query($sql);
	if($wpdb->last_error !== '') {
		trigger_error( _e('Plugin requires at least MySQL 5.0.3 - Activation Failed'), E_USER_ERROR );
	}
}
