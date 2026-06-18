<?php
// Do not load directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility methods
 *
 * @package Favicon Rotator
 * @subpackage Utilities
 * @author Archetyped
 */
class FVRT_Utilities {

	public function __construct() {
	}

	/**
	 * Returns callback array to instance method
	 *
	 * @param object $obj Instance object
	 * @param string $method Name of method
	 *
	 * @return array Callback array
	 */
	public function &m( &$obj, $method = '' ) {
		if ( null === $obj && isset( $this ) ) {
			$obj =& $this;
		}
		$arr = array( &$obj, $method );
		return $arr;
	}

	/* Helper Functions */

	/*-** WP **-*/

	/**
	 * Checks if $post is a valid Post object
	 * If $post is not valid, assigns global post object to $post (if available)
	 *
	 * @param object $post Post object to evaluate
	 *
	 * @return bool TRUE if $post is valid object by end of function processing
	 */
	public function check_post( &$post ) {
		if ( empty( $post ) ) {
			if ( isset( $GLOBALS['post'] ) ) {
				$post = $GLOBALS['post'];
				$GLOBALS['post'] =& $post;
			} else {
				return false;
			}
		}
		if ( is_array( $post ) ) {
			$post = (object) $post;
		} elseif ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! is_object( $post ) ) {
			return false;
		}
		return true;
	}

	/*-** Request **-*/

	/**
	 * Retrieves nonce value.
	 *
	 * Handles sanitization and default value.
	 *
	 * @param string $nonce_key Key in `$qv` that contains nonce value.
	 * @param array|null $qv (optional) Query array containing nonce value (Default: `$_POST`).
	 *
	 * @return string Nonce value (Default: empty string).
	 */
	public function nonce_get( string $nonce_key, ?array $qv = null ): string {
		// Set default return value.
		$ret = '';
		// Set default collection.
		if ( ! $qv ) {
			$qv = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- sanitization performed later.
		}
		// Stop processing invalid query.
		if ( empty( $qv ) || ! isset( $qv[ $nonce_key ] ) ) {
			return $ret;
		}
		$ret = sanitize_text_field( wp_unslash( $qv[ $nonce_key ] ) );
		return $ret;
	}

	/**
	 * Checks $_SERVER['SCRIPT_NAME'] to see if file base name matches specified file name
	 *
	 * @param string $filename Filename to check for
	 *
	 * @return bool TRUE if current page matches specified filename, FALSE otherwise
	 */
	public function is_file( $filename ) {
		// Sanity check.
		if ( ! is_string( $filename ) || empty( $filename ) || ! isset( $_SERVER['SCRIPT_NAME'] ) ) {
			return false;
		}
		return ( basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) === $filename );
	}

	/**
	 * Joins and normalizes the slashes in the paths passed to method
	 * All forward/back slashes are converted to forward slashes
	 * Multiple path segments can be passed as additional argments
	 *
	 * @param string $path Path to normalize
	 * @param bool $trailing_slash (optional) Whether or not normalized path should have a trailing slash or not (Default: FALSE)
	 *  If multiple path segments are passed, $trailing_slash will be the LAST parameter (default value used if omitted)
	 *
	 * @todo Refactor to use variadic parameters.
	 */
	public function normalize_path( $path, $trailing_slash = false ) {
		$sl_f = '/';
		$sl_b = '\\';
		$parts = func_get_args();
		if ( func_num_args() > 1 ) {
			$tr = $parts[ count( $parts ) - 1 ];
			if ( is_bool( ( $tr ) ) ) {
				$trailing_slash = $tr;
				// Remove from args array.
				array_pop( $parts );
			} else {
				$trailing_slash = false;
			}
			$first = true;
			// Trim trailing slashes from path parts.
			foreach ( $parts as $key => $part ) {
				$part = trim( $part );
				// Special Trim.
				$parts[ $key ] = trim( $part, $sl_f . $sl_b );
				// Verify path still contains value.
				if ( empty( $parts[ $key ] ) ) {
					unset( $parts[ $key ] );
					continue;
				}
				// Only continue processing the first valid path segment.
				if ( $first ) {
					$first = ! $first;
				} else {
					continue;
				}
				// Add back leading slash if necessary.
				if ( $part[0] === $sl_f || $part[0] === $sl_b ) {
					$parts[ $key ] = $sl_f . $parts[ $key ];
				}
			}
		}
		// Join path parts together.
		$parts = implode( $sl_b, $parts );
		$parts = str_replace( $sl_b, $sl_f, $parts );
		// Add trailing slash (if necessary).
		if ( $trailing_slash ) {
			$parts . $sl_f;
		}
		return $parts;
	}

	/**
	 * Returns URL of file (assumes that it is in plugin directory)
	 *
	 * @param string $file name of file get URL
	 *
	 * @return string File path
	 */
	public function get_file_url( $file ) {
		if ( is_string( $file ) && '' !== trim( $file ) ) {
			$file = $this->normalize_path( $this->get_url_base(), $file );
		}
		return $file;
	}

	/**
	 * Retrieves file extension
	 *
	 * @param string $file file name/path
	 *
	 * @return string File's extension
	 */
	public function get_file_extension( $file ) {
		$ret = '';
		$sep = '.';
		$rpos = ( is_string( $file ) ) ? strrpos( $file, $sep ) : false;
		if ( false !== $rpos ) {
			$ret = substr( $file, $rpos + 1 );
		}
		return $ret;
	}

	/**
	 * Checks if file has specified extension
	 *
	 * @param string $file File name/path
	 * @param string $extension File ending to check $file for
	 *
	 * @return bool TRUE if file has extension
	 */
	public function has_file_extension( $file, $extension ) {
		return ( $this->get_file_extension( $file ) === $extension ) ? true : false;
	}

	/**
	 * Retrieve base URL for plugin-specific files
	 *
	 * @return string Base URL
	 */
	public function get_url_base() {
		static $url_base = '';
		if ( '' === $url_base ) {
			$url_base = $this->normalize_path( WP_PLUGIN_URL, $this->get_plugin_base() );
		}
		return $url_base;
	}

	public function get_path_base() {
		static $path_base = '';
		if ( '' === $path_base ) {
			$path_base = $this->normalize_path( WP_PLUGIN_DIR, $this->get_plugin_base() );
		}
		return $path_base;
	}

	public function get_plugin_base() {
		static $plugin_dir = '';
		if ( '' === $plugin_dir ) {
			$plugin_dir = str_replace( $this->normalize_path( WP_PLUGIN_DIR ), '', $this->normalize_path( dirname( __DIR__ ) ) );
		}
		return $plugin_dir;
	}

	public function get_plugin_base_file() {
		$file = 'main.php';
		return $this->get_path_base() . '/' . $file;
	}

	public function get_plugin_base_name() {
		$file = $this->get_plugin_base_file();
		return plugin_basename( $file );
	}

	/**
	 * Retrieve current action based on URL query variables
	 *
	 * @param mixed $def (optional) Default action if no action exists
	 *
	 * @return string Current action
	 */
	public function get_action( $def = null ) {
		// Retrieve action from URL.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- operations not dependent on specific action.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// Determine action based on plugin plugin admin page suffix.
		if ( empty( $action ) && isset( $_GET['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
			$suffix_pos = strrpos( $page, '-' );
			if ( false !== $suffix_pos && ( strlen( $page ) - 1 !== $suffix_pos ) ) {
				$action = trim( substr( $page, $suffix_pos + 1 ), '-_' );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended -- end exclusion.

		// Determine action for core admin pages.
		if ( empty( $action ) && isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$page = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '.php' );
			$actions = array(
				'add'       => array( 'page-new', 'post-new' ),
				'edit-item' => array( 'page', 'post' ),
				'edit'      => array( 'edit', 'edit-pages' ),
			);
			$action = array_find_key(
				$actions,
				function ( $pages ) use ( $page ) {
					return in_array( $page, $pages, true );
				}
			);
		}
		// Fallback: Default action.
		if ( empty( $action ) ) {
			$action = $def;
		}
		return $action;
	}

	/*-** General **-*/

	/**
	 * Retrieve specified property from object or array
	 *
	 * @param object|array $obj Object or array to get property from
	 * @param string $property Property name to retrieve
	 *
	 * @return mixed Property value
	 */
	public function &get_property( &$obj, $property ) {
		$property = trim( $property );
		// Object.
		if ( is_object( $obj ) ) {
			return $obj->{$property};
		}
		// Array.
		if ( is_array( $obj ) ) {
			return $obj[ $property ];
		}
		// Class.
		if ( is_string( $obj ) && class_exists( $obj ) ) {
			$cvars = get_class_vars( $obj );
			if ( isset( $cvars[ $property ] ) ) {
				return $cvars[ $property ];
			}
		}
	}

	/**
	 * Merges 1 or more arrays together
	 * Methodology
	 * - Set first parameter as base array
	 *   - All other parameters will be merged into base array
	 * - Iterate through other parameters (arrays)
	 *   - Skip all non-array parameters
	 *   - Iterate though key/value pairs of current array
	 *     - Merge item in base array with current item based on key name
	 *     - If the current item's value AND the corresponding item in the base array are BOTH arrays, recursively merge the the arrays
	 *     - If the current item's value OR the corresponding item in the base array is NOT an array, current item overwrites base item
	 *
	 * @param array<array> $arrs Variable number of arrays
	 *
	 * @todo Append numerical elements (as opposed to overwriting element at same index in base array)
	 * @return array Merged array
	 */
	public function array_merge_recursive_distinct( array ...$arrs ) {
		// Set first array as base array.
		$merged = $arrs[0];
		// Iterate through arrays to merge.
		$arrs_count = count( $arrs );
		for ( $x = 1; $x < $arrs_count; $x++ ) {
			// Iterate through argument items.
			foreach ( $arrs[ $x ] as $key => $val ) {
				if ( ! isset( $merged[ $key ] ) || ! is_array( $merged[ $key ] ) || ! is_array( $val ) ) {
					$merged[ $key ] = $val;
				} elseif ( is_array( $merged[ $key ] ) && is_array( $val ) ) {
					$merged[ $key ] = $this->array_merge_recursive_distinct( $merged[ $key ], $val );
				}
			}
		}
		return $merged;
	}

	/**
	 * Replaces string value in one array with the value of the matching element in a another array
	 *
	 * @param string $search Text to search for in array
	 * @param array $arr_replace Array to use for replacing values
	 * @param array $arr_subject Array to search for specified value
	 *
	 * @return array Searched array with replacements made
	*/
	public function array_replace_recursive( $search, $arr_replace, $arr_subject ) {
		foreach ( $arr_subject as $key => $val ) {
			// Skip element if key does not exist in the replacement array.
			if ( ! isset( $arr_replace[ $key ] ) ) {
				continue;
			}
			// If element values for both arrays are strings, replace text.
			if ( is_string( $val ) && strpos( $val, $search ) !== false && is_string( $arr_replace[ $key ] ) ) {
				$arr_subject[ $key ] = str_replace( $search, $arr_replace[ $key ], $val );
			}
			// If value in both arrays are arrays, recursively replace text.
			if ( is_array( $val ) && is_array( $arr_replace[ $key ] ) ) {
				$arr_subject[ $key ] = $this->array_replace_recursive( $search, $arr_replace[ $key ], $val );
			}
		}

		return $arr_subject;
	}

	/**
	 * Checks if item at specified path in array is set
	 *
	 * @param array $arr Array to check for item
	 * @param array $path Array of segments that form path to array (each array item is a deeper dimension in the array)
	 * @param mixed $item Optional. Reference to variable to pass path value back to.
	 *
	 * @return boolean TRUE if item is set in array, FALSE otherwise
	 */
	public function array_item_isset( $arr, $path, &$item = null ) {
		// Basic validation.
		if ( ! is_array( $arr ) || ! is_array( $path ) || empty( $arr ) || empty( $path ) ) {
			return false;
		}
		// Validate path keys.
		if ( ! array_all( $path, fn( $val ) => ( is_string( $val ) || is_int( $val ) ) ) ) {
			return false;
		}
		// Check if path keys exist in array.
		$base = &$arr;
		foreach ( $path as $key ) {
			// Stop if key not set.
			if ( ! isset( $base[ $key ] ) ) {
				return false;
			}
			// Set new base for next iteration.
			$base = &$base[ $key ];
		}
		// All checks passed.
		$item = $base;
		return true;
	}

	/**
	 * Returns value of item at specified path in array
	 *
	 * @param array $arr Array to get item from
	 * @param array $path Array of segments that form path to array (each array item is a deeper dimension in the array)
	 *
	 * @return mixed Value of item in array (Default: empty string)
	 */
	public function get_array_item( $arr, $path ) {
		$item = '';
		// Retrieve item.
		$this->array_item_isset( $arr, $path, $item );
		return $item;
	}

	/**
	 * Builds array of path elements based on arguments
	 * Each item in path array represents a deeper level in structure path is for (object, array, filesystem, etc.)
	 *
	 * @param array|string Value to add to the path
	 *
	 * @return array 1-dimensional array of path elements
	 */
	public function build_path() {
		$path = array();
		$args = func_get_args();

		// Iterate through parameters and build path.
		foreach ( $args as $arg ) {
			if ( empty( $arg ) ) {
				continue;
			}

			if ( is_array( $arg ) ) {
				// Recurse through array items to pull out any more arrays.
				foreach ( $arg as $key => $val ) {
					$path = array_merge( $path, $this->build_path( $val ) );
				}
			} elseif ( is_scalar( $arg ) ) {
				$path[] = $arg;
			}
		}

		return $path;
	}

	/**
	 * Builds attribute string for HTML element.
	 *
	 * @param array $attrs Attributes.
	 *
	 * @return string Formatted attribute string.
	 */
	public function build_attribute_string( $attrs ) {
		$ret = '';
		// Convert object to array.
		if ( is_object( $attrs ) ) {
			$attrs = (array) $attrs;
		}
		// Convert array to string of attributes and values.
		if ( is_array( $attrs ) ) {
			$attr_str = array();
			// Build as array of strings.
			foreach ( $attrs as $key => $val ) {
				$attr_str[] = sprintf( '%1$s="%2$s"', esc_attr( $key ), esc_attr( $val ) );
			}
			// Merge strings into single string.
			$ret = implode( ' ', $attr_str );
		}
		return $ret;
	}

	/**
	 * Generate input element
	 *
	 * @param string $type (optional) Input type
	 * @param string $name (optional) Input name
	 * @param mixed $value (optional) Input value
	 * @param array $attributes (optional) Additional attributes
	 */
	public function build_input_element( $type = 'text', $name = '', $value = '', $attributes = array() ) {
		// Build attributes.
		$attributes = wp_parse_args(
			$attributes,
			array(
				'type'  => $type,
				'name'  => $name,
				'value' => $value,
			)
		);
		// Build element.
		return $this->build_html_element( 'input', true, $attributes );
	}

	/**
	 * Generates HTML element.
	 *
	 * @param string $tag_name (optional) Element tag name (Default: span).
	 * @param boolean $is_void (optional) Whether element is void or contains a closing tag (Default: false)
	 * @param array $attributes (optional) Attributes (key/value pairs).
	 * @param string $content (optional) Element text content (Default: empty string).
	 *
	 * @return string Generated HTML element.
	 */
	public function build_html_element( string $tag_name = 'span', bool $is_void = false, array $attributes = array(), string $content = '' ): string {
		$tag_name = sanitize_key( $tag_name );
		$content = trim( $content );

		// Build element processor.
		$tag_fmt = ( $is_void ) ? '<%s>' : '<%s></%s>';
		$fragment = sprintf( $tag_fmt, $tag_name );
		$el = new WP_HTML_Tag_Processor( $fragment );
		$el->next_tag();

		// Set attributes.
		foreach ( $attributes as $name => $val ) {
			$el->set_attribute( $name, $val );
		}

		// Set content.
		if ( $content ) {
			$el->set_modifiable_text( $content );
		}

		// Return element.
		return $el->get_updated_html();
	}

	/*-** Admin **-*/

	/**
	 * Add submenu page in the admin menu
	 * Adds ability to set the position of the page in the menu
	 *
	 * @param $parent_menu
	 * @param $page_title
	 * @param $menu_title
	 * @param $access_level
	 * @param $file
	 * @param $callback
	 * @param int $pos Index position of menu page
	 *
	 * @see add_submenu_page (Wraps functionality)
	 * @global array $submenu Admin page submenus
	 */
	public function add_submenu_page( $parent_menu, $page_title, $menu_title, $capability, $file, $callback = '', $pos = false ) {
		// Add submenu page as usual.
		$args = func_get_args();
		$hookname = call_user_func_array( 'add_submenu_page', $args );
		if ( is_int( $pos ) ) {
			global $submenu;
			// Get last submenu added.
			$parent_menu = $this->get_submenu_parent_file( $parent_menu );
			if ( isset( $submenu[ $parent_menu ] ) ) {
				$subs =& $submenu[ $parent_menu ];
				// Make sure menu isn't already in the desired position.
				if ( $pos <= ( count( $subs ) - 1 ) ) {
					// Get submenu that was just added.
					$sub = array_pop( $subs );
					// Insert into desired position.
					if ( 0 === $pos ) {
						array_unshift( $subs, $sub );
					} else {
						$top = array_slice( $subs, 0, $pos );
						$bottom = array_slice( $subs, $pos );
						array_push( $top, $sub );
						$subs = array_merge( $top, $bottom );
					}
				}
			}
		}

		return $hookname;
	}

	/**
	 * Remove admin submenu
	 *
	 * @param string $parent_menu Submenu parent file
	 * @param string $file Submenu file name
	 *
	 * @return int|null Index of removed submenu (NULL if submenu not found)
	 *
	 * @global array $submenu
	 * @global array $_registered_pages
	 */
	public function remove_submenu_page( $parent_menu, $file ) {
		global $submenu, $_registered_pages;
		$ret = null;

		$parent_menu = $this->get_submenu_parent_file( $parent_menu );
		$file = plugin_basename( $file );
		$file_index = 2;

		// Find submenu.
		if ( isset( $submenu[ $parent_menu ] ) ) {
			$subs =& $submenu[ $parent_menu ];
			$subs_count = count( $subs );
			for ( $x = 0; $x < $subs_count; $x++ ) {
				if ( $subs[ $x ][ $file_index ] === $file ) {
					// Remove matching submenu.
					$hookname = get_plugin_page_hookname( $file, $parent_menu );
					remove_all_actions( $hookname );
					unset( $_registered_pages[ $hookname ] );
					unset( $subs[ $x ] );
					$subs = array_values( $subs );
					// Update submenu count.
					$subs_count = count( $subs );
					// Set index and stop processing.
					$ret = $x;
					break;
				}
			}
		}

		return $ret;
	}

	/**
	 * Replace a submenu page
	 * Adds a submenu page in the place of an existing submenu page that has the same $file value
	 *
	 * @param $parent_menu
	 * @param $page_title
	 * @param $menu_title
	 * @param $access_level
	 * @param $file
	 * @param $callback
	 *
	 * @return string Hookname
	 *
	 * @global array $submenu
	 */
	public function replace_submenu_page( $parent_menu, $page_title, $menu_title, $access_level, $file, $callback = '' ) {
		global $submenu;
		// Remove matching submenu (if exists).
		$pos = $this->remove_submenu_page( $parent_menu, $file );
		// Insert submenu page.
		$hookname = $this->add_submenu_page( $parent_menu, $page_title, $menu_title, $access_level, $file, $callback, $pos );
		return $hookname;
	}

	/**
	 * Retrieves parent file for submenu
	 *
	 * @param string $parent_menu Parent file
	 *
	 * @return string Formatted parent file name
	 *
	 * @global array $_wp_real_parent_file;
	 */
	public function get_submenu_parent_file( $parent_menu ) {
		global $_wp_real_parent_file;
		$parent_menu = plugin_basename( $parent_menu );
		if ( isset( $_wp_real_parent_file[ $parent_menu ] ) ) {
			$parent_menu = $_wp_real_parent_file[ $parent_menu ];
		}
		return $parent_menu;
	}
}
