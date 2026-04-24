<?php
/* 
Plugin Name: Favicon Rotator
Plugin URI: http://archetyped.com/tools/favicon-rotator/
Description: Easily set site favicon and even rotate through multiple icons
Version: 0.0.0-dev
Author: Archetyped
Author URI: http://archetyped.com
Text Domain: favicon-rotator
*/

// Do not load directly. 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'model.php';
$fvrt = new FaviconRotator();
