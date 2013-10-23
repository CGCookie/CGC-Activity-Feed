<?php
/**
 * Plugin Name: CGC Activity Feed
 * Plugin URI: http://cgcookie.com
 * Description: Customizable Activity Feed where theme and other plugins can hook to display custom activity.
 * Author: Brian DiChiara
 * Author URI: http://briandichiara.com
 * Version: 0.0.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'CGCAF_VERSION',	'0.0.1' );
define( 'CGCAF_PATH',		plugin_dir_path( __FILE__ ) );
define( 'CGCAF_DIR',		plugin_dir_url( __FILE__ ) );

require_once( CGCAF_PATH . 'includes/functions.php' );
require_once( CGCAF_PATH . 'classes/cgc-activity-feed.class.php' );

global $cgcaf_plugin;
$cgcaf_plugin = new CGC_Activity_Feed();
$cgcaf_plugin->initialize();