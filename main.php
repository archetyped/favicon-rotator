<?php
/**
 * Favicon Rotator
 *
 * @package Favicon Rotator
 * @author Archetyped <support@archetyped.com>
 * @copyright 2026 Archetyped
 *
 * Plugin Name: Favicon Rotator
 * Plugin URI: http://archetyped.com/tools/favicon-rotator/
 * Description: Easily set site favicon and even rotate through multiple icons
 * License: GPLv2
 * Version: 1.2.12
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Text Domain: favicon-rotator
 * Author: Archetyped
 * Author URI: http://archetyped.com
 * Support URI: https://github.com/archetyped/favicon-rotator/wiki/Reporting-Issues
*/


// Do not load directly. 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'model.php';
$fvrt = new FaviconRotator();
