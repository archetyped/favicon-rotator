<?php 
// Do not load directly. 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/class.base.php';
require_once 'includes/class.media.php';

/**
 * @package Favicon Rotator
 * @author Archetyped
 */
class FaviconRotator extends FVRT_Base {
	
	/**
	 * Admin Page hook
	 * @var string
	 */
	var $page;
	
	/**
	 * Plugin options
	 * @var array
	 */
	var $options = null;
	
	/**
	 * Plugin Options key
	 * @var string
	 */
	var $opt_key = 'options';
	
	/**
	 * Key to use for icons array in options
	 * @var string
	 */
	var $opt_icons = 'icons';
	
	/**
	 * Icon types
	 * @var array
	 */
	var $icon_types = array(
		'favicon'	=> array(
			'lbl_title'		=> 'Browser Icon',
			'lbl_set'		=> 'Add Browser Icon'
		),
		'touch'		=> array(
			'limit'			=> 1,
			'lbl_title'		=> 'Touch Icon',
			'lbl_add'		=> 'Set Icon',
			'lbl_set'		=> 'Set Touch Icon',
			'lbl_empty'		=> 'No touch icon set',
			'display'		=> '<link rel="apple-touch-icon-precomposed" href="%1$s" />',
			'file_mime'		=> array('image/png'),
			'file_type'		=> array('png'),
			'file_desc'		=> 'Touch Icon Files',
			'width'			=> 114,
			'height'		=> 114
		)
	);
	
	/**
	 * Default properties for an icon type
	 * @var array
	 */
	var $icon_type_default_properties = array(
		'limit' 		=> null,
		'lbl_title' 	=> '',
		'lbl_add'		=> 'Add Icon',
		'lbl_set'		=> 'Add Icon',
		'lbl_empty'		=> 'No icons set',
		'display'		=> '<link rel="shortcut icon" href="%1$s" />',
		'file_mime'		=> array('image/png', 'image/gif', 'image/jpeg', 'image/x-icon'),
		'file_type'		=> array('png', 'gif', 'jpg', 'ico'),
		'file_desc'		=> 'Icon Files',
		'width'			=> 16,
		'height'		=> 16
	);
	
	/**
	 * Default icon type
	 * @var string
	 */
	var $icon_type_default = 'favicon';
	
	/**
	 * Save action
	 * @var string
	 */
	var $action_save = 'action_save';
	
	/**
	 * Path to admin contextual help file
	 * @var string
	 */
	var $file_admin_help = 'resources/admin_help.html';
	
	/*-** Instance objects **-*/
	
	/**
	 * Media instance
	 * @var FVRT_Media
	 */
	var $media;
	
	/*-** Initialization **-*/

	function __construct() {
		parent::__construct();
		$this->opt_key = $this->add_prefix($this->opt_key);
		$this->action_save = $this->add_prefix($this->action_save);
		$this->media = new FVRT_Media();
		$this->register_hooks();
	}
	
	function register_hooks() {
		/*-** General **-*/
		
		add_action('init', $this->m('register_icon_types'));
		
		/*-** Admin **-*/
		
		add_action('admin_print_scripts-media-upload-popup', $this->m('admin_scripts'));
		
		//Menu
		add_action('admin_menu', $this->m('admin_menu'));
		//Plugins page
		add_filter('plugin_action_links_' . $this->util->get_plugin_base_name(), $this->m('admin_plugin_action_links'), 10, 4);

		/*-** Main **-*/
		
		//Template
		add_action('wp_head', $this->m('display_icons'));
		
		//Instance setup
		$this->media->register_hooks();
	}
	
	/*-** Helpers **-*/
	
	/**
	 * Retrieve options array (or specific option if specified)
	 * @param $key Option to retrieve
	 * @return mixed Full options array or value of specific key (Default: empty array)
	 */
	function get_options($key = null) {
		//Retrieve options entry from DB
		$ret = $this->options;
		if ( is_null($ret) ) {
			$ret = get_option($this->opt_key);
			if ( false === $ret )
				$ret = array();
			$this->options = $ret;
		}
				
		if ( !is_null($key) && isset($ret[$key]) )
			$ret = $ret[$key];
		return $ret;
	}
	
	function set_option($key, $val) {
		//Make sure options array initialized
		$this->get_options();
		//Set option value
		$this->options[$key] = $val;
		//Save updated options to DB
		update_option($this->opt_key, $this->options);
	}
	
	/*-** Media **-*/

	/**
	 * Register icon types with Media instance
	 * @see register_hooks() Hook to method added
	 * @return array Registered types
	 */
	function register_icon_types() {
		$types = $this->icon_types;
		//Register types
		foreach ( $types as $type => $props ) {
			$types[$type] = $this->media->register_type($type, array_merge($this->icon_type_default_properties, $props));
		}
		return $types;
	}
	
	/**
	 * Retrieve icon types
	 * @return array Icon types (as object of properties)
	 */
	function get_icon_types() {
		static $types = null;
		if ( is_null($types) ) {
			$types = $this->register_icon_types();
		}
		return $types;
	}
	
	/**
	 * Retrieve icon type names
	 * @return array Icon type names
	 */
	function get_icon_type_names() {
		static $names = null;
		if ( is_null($names) )
			$names = array_keys($this->get_icon_types());
		return $names;
	}
	
	/**
	 * Retrieve icon type properties
	 * @param string $type Icon type
	 * @return object|bool Type properties (FALSE if type not registered)
	 */
	function get_icon_type($type) {
		$ret = null;
		$types = $this->get_icon_types();
		if ( isset($types[$type]) ) {
			$ret = $types[$type];
		} else {
			$ret = (object) $this->icon_type_default_properties;
			$ret->type_name = $type;
		}
		return $ret;
	}
	
	/**
	 * Normalize icons array to contain valid icon groups
	 * @param array $icons Raw Icons array
	 * @return array Normalized icons
	 */
	function normalize_icons($icons) {
		static $groups_default = null;
		$save = false;
		//Build default groups array
		if ( is_null($groups_default) ) {
			$groups_default = $dval = array();
			foreach ( $this->get_icon_type_names() as $type ) {
				$groups_default[$type] = $dval;
			}
		}
		//Make sure icons array is valid (icons grouped by type)
		if ( is_array($icons) && !empty($icons) ) {
			$icons_temp = array_values($icons);
			//Put icons into default group if necessary
			if ( !is_array($icons_temp[0]) ) {
				$icons = array($this->icon_type_default => $icons_temp);
				$save = true;
			}
		} elseif ( !is_array($icons) ) {
			$icons = array();
		}
		//Merge and return icons with defaults
		$icons = array_merge($groups_default, $icons);
		if ( $save )
			$this->save_icons($icons);
		return $icons;
	}
	
	/**
	 * Retrieve Post IDs of favicons
	 * @param string $type Icon type to retrieve
	 * @return array Icon IDs grouped by icon type
	 */
	function get_icon_ids($type = null) {
		//Get array of attachment IDs for icons (grouped by type)
		$icons = $this->get_options($this->opt_icons);
		
		//Normalize icons array
		$icons = $this->normalize_icons($icons);
		
		//Get specific icon type
		if ( is_string($type) && !empty($type) ) {
			$icons = ( isset($icons[$type]) ) ? $icons[$type] : array();
		}

		return $icons;
	}
	
	/**
	 * Retrieve list of icons as string
	 * @param string $type Icon type
	 * @return string Icon list
	 */
	function get_icon_ids_list($type) {
		return implode(',', $this->get_icon_ids($type));
	}
	
	/**
	 * Retrieve icons saved in options menu
	 * @return array Media attachment objects
	 */
	function get_icons($type = null) {
		$icons = array();
		//Get icon ids
		$ids = $this->get_icon_ids($type);
		//Retrieve attachment objects
		if ( !empty($ids) ) {
			//Wrap IDs in assoc array if valid type is specified
			$type_valid = false;
			if ( is_string($type) && !empty($type) ) {
				$ids = array($type => $ids);
				$type_valid = true;
			}
			foreach ( $ids as $type_key => $type_ids ) {
				$icons[$type_key] = ( !empty($type_ids) ) ? get_posts(array('post_type' => 'attachment', 'include' => $type_ids)) : array();
				//Fix icon option if invalid icons were passed
				if ( count($icons[$type_key]) != count($type_ids) ) {
					$ids_temp = array();
					foreach ( $icons[$type_key] as $icon ) {
						$ids_temp[] = $icon->ID;
					}
					//Save to DB
					$this->save_icons($ids_temp, $type_key);
				}
			}
			//Break specified type out of assoc array
			if ( $type_valid )
				$icons = $icons[$type];
		}
		
		return $icons;
	}
	
	/**
	 * Save list of selected icons to DB
	 * @param array $ids (optional) Array of icon IDs
	 * @param string $type (optional) Icon type being saved
	 */
	function save_icons($icons = null, $type = null) {
		//Check if valid icon IDs are passed to function
		if ( !is_null($icons) ) {
			if ( !is_array($icons) ) {
				 $icons = ( is_int($icons) ) ? array($icons) : null;
			}
		}
		
		//Get icon IDs from form submission
		if ( is_null($icons) && check_admin_referer($this->action_save) ) {
			$icons = array();
			$field_base = 'fv_id_';
			foreach ( $this->get_icon_type_names() as $itype ) {
				$field = $field_base . $itype;
				if ( isset($_POST[$field]) ) { 
					$icons[$itype] = explode( ',', esc_attr( $_POST[$field] ) );
				}
			}
		}
		
		//Save to DB
		if ( is_array($icons) ) {
			//Build full icons array: Combine saved icons and current icons
			if ( !is_null($type) ) {
				$icons_temp = $icons;
				$icons = array($type => $icons_temp);
				unset($icons_temp);
				//Merge saved icons with new icon values
				$icons = wp_parse_args($icons, $this->get_icon_ids());
			}
			//Validate values
			foreach ( $icons as $itype => $type_ids ) {
				$changed = false;
				foreach ( $type_ids as $idx => $id ) {
					if ( !intval($id) ) {
						unset($icons[$itype][$idx]);
						$changed = true;
					}
				}
				if ( $changed ) {
					$icons[$itype] = array_values($icons[$itype]);
				}
			}
			//Save icons to DB
			$this->set_option($this->opt_icons, $icons);
		}
	}
	
	/**
	 * Output markup for all icons
	 */
	function display_icons() {
		echo "<!-- Favicon Rotator -->\r\n";
		foreach ( $this->get_icon_type_names() as $type ) {
			$this->display_icon($type);
		}
		echo "<!-- End Favicon Rotator -->\r\n";
	}
	
	/**
	 * Output markup for specified icon type
	 */
	function display_icon($type) {
		//Get icons
		$icons = $icons_orig = $this->get_icon_ids($type);
		$icon = null;
		//Loop through retrieved icons until valid icon is returned
		while ( is_null($icon) && count($icons) > 0 ) {
			//Select random icon
			$idx = ( count($icons) > 1 ) ? array_rand($icons) : 0;
			$icon_id = $icons[$idx];
			$icon_src = $this->media->get_icon_src($icon_id, $type);
			$icon = array_shift($icon_src);
			//Validate icon
			if ( !is_string($icon) || empty($icon) ) {
				//Reset variable to NULL (will loop to next icon)
				$icon = null;
				//Remove invalid icon from list
				unset($icons[$idx]);
				$icons = array_values($icons);
			}
		}
		
		//Display icon
		if ( !is_null($icon) ) {
			$t = $this->get_icon_type($type);
			$out = sprintf( $t->display, esc_url( $icon ) ); 
			$allowed_tags = [
				'link' => [
					'rel' => true,
					'href' => true,
				],
			];

			echo wp_kses( $out, $allowed_tags ) . "\r\n";
		}
		
		//Update icons array (if necessary)
		if ( count($icons) !== count($icons_orig) )
			$this->save_icons($icons, $type);
	}
	
	/*-** Admin **-*/
	
	/**
	 * Adds custom links below plugin on plugin listing page
	 * @param $actions
	 * @param $plugin_file
	 * @param $plugin_data
	 * @param $context
	 */
	function admin_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
		//Add link to settings (only if active)
		if ( is_plugin_active($this->util->get_plugin_base_name()) ) {
			$settings = __('Settings', 'favicon-rotator');
			$settings_url = add_query_arg('page', dirname($this->util->get_plugin_base_name()), admin_url('themes.php'));
			array_unshift($actions, '<a href="' . $settings_url . '" title="' . $settings . '">' . $settings . '</a>');
		}
		return $actions;
	}
	
	/**
	 * Get ID of settings section on admin page
	 * @return string ID of settings section
	 */
	function admin_get_settings_section() {
		return $this->add_prefix('settings');
	}
	
	/**
	 * Adds admin submenu item to Appearance menu 
	 */
	function admin_menu() {
		$this->page = $p = add_theme_page(__('Favicon', 'favicon-rotator'), __('Favicon', 'favicon-rotator'), 'edit_theme_options', $this->util->get_plugin_base(), $this->m('admin_page'));
		//Head
		add_action("admin_print_scripts-$p", $this->m('admin_scripts'));
		add_action("admin_print_styles-$p", $this->m('admin_styles'));
		add_action("admin_head-$p", $this->m('admin_help'));
	}
	
	/**
	 * Defines content for admin page
	 */
	function admin_page() {
		if ( ! current_user_can('edit_theme_options') )
			wp_die( esc_html__('You do not have permission to customize favicons.', 'favicon-rotator') );
			
		//Get saved icons
		if ( isset($_POST['fv_submit']) )
			$this->save_icons();
		$class = "button thickbox fv_btn";
		//Setup query arguments
		$filter = array('limit', 'lbl_title', 'lbl_add', 'lbl_empty', 'display');
		$upload_args_base = array_diff(array_keys($this->icon_type_default_properties), $filter);
		$upload_args_base[] = 'type_name';
		$upload_args_map = array();
		foreach ( $upload_args_base as $key ) {
			$upload_args_map[$this->add_prefix($key)] = $key;
		}
		
		?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Favicon Rotator', 'favicon-rotator' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $_SERVER[ 'REQUEST_URI' ] ); ?>">
		<?php foreach ( $this->get_icon_types() as $tname => $t ) : /* Output UI for icon types */ 
			$icons = $this->get_icons( $t->type_name );
			$upload_args = array();
			foreach ( $upload_args_map as $param => $prop ) {
				if ( isset( $t->$prop ) )
					$upload_args[$param] = $t->$prop;
			}
			$upload_link_escaped = sprintf(
				'<a href="%1$s" class="%2$s" title="%3$s">%4$s</a>',
				esc_url( $this->media->get_upload_iframe_src( 'image', $upload_args ) ), /* URL */
				esc_attr( $class ), /* class */
				esc_attr( $t->lbl_add, 'favicon-rotator' ), /* title */
				esc_html( $t->lbl_add, 'favicon-rotator' ), /* content */	
			);
		?>
			<h3><?php esc_html_e( $t->lbl_title, 'favicon-rotator' ); ?> <?php echo $upload_link_escaped; ?></h3>
			<div class="fv_container">
				<p id="fv_msg_empty_<?php echo esc_attr( $t->type_name ); ?>"<?php if ( $icons ) echo ' style="display: none;"'?>><?php esc_html_e( $t->lbl_empty, 'favicon-rotator' ); ?></p>
				<ul id="fv_item_wrap_<?php echo esc_attr( $t->type_name ); ?>" class="fv_item_wrap <?php echo ( is_null( $t->limit ) ) ? 'multi' : 'single'; ?>">
				<?php foreach ( $icons as $icon ) : //List icons
					$icon_src = array_shift( $this->media->get_icon_src( $icon->ID, $t->type_name ) );
					$icon_media = wp_get_attachment_image_src( $icon->ID, 'full' );
					$src = array_shift( $icon_media );
				?>
					<li class="fv_item">
						<div>
							<img class="icon" src="<?php echo esc_url( $icon_src ); ?>" />
							<div class="details">
								<div class="name"><?php echo esc_html( basename( $src ) ); ?></div>
								<div class="options">
									<a href="#" id="<?php echo esc_attr( 'fv_id_' . $t->type_name . '_' . $icon->ID ); ?>" class="remove">Remove</a>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; //End icon listing
					unset( $icon_src, $icon_media, $src );
				?>
				</ul>
				<div style="display: none">
					<li id="fv_item_temp_<?php echo esc_attr( $t->type_name ); ?>" class="fv_item">
						<div>
							<img class="icon" src="" />
							<div class="details">
								<div class="name"></div>
								<div class="options">
									<a href="#" class="remove">Remove</a>
								</div>
							</div>
						</div>
					</li>
				</div>
			</div>
			<input type="hidden" id="fv_id_<?php echo esc_attr( $t->type_name ); ?>" name="fv_id_<?php echo esc_attr( $t->type_name ); ?>" value="<?php echo esc_attr( $this->get_icon_ids_list( $t->type_name ) ); ?>" />
		<?php endforeach; /* END UI for icon types */ ?>
			<?php wp_nonce_field( $this->action_save ); ?>
			<p class="submit"><input type="submit" class="button-primary" name="fv_submit" value="<?php esc_attr_e( 'Save Changes', 'favicon-rotator' ); ?>" /></p>
		</form>
	</div>
	<?php 
	}
	
	/**
	 * Adds JS to Admin page
	 */
	function admin_scripts() {
		wp_enqueue_script('media-upload');
		$h_admin = $this->add_prefix('admin_script');
		$h_media = $this->add_prefix('media');
		wp_enqueue_script($h_admin, $this->util->get_file_url('js/admin.js'), array('jquery-migrate', 'jquery', 'thickbox'));
		wp_enqueue_script($h_media, $this->util->get_file_url('js/media.js'), array('jquery-migrate', 'jquery', $h_admin));
	}
	
	/**
	 * Adds CSS to Admin page
	 */
	function admin_styles() {
		add_thickbox();
		wp_enqueue_style($this->add_prefix('admin_styles'), $this->util->get_file_url('css/admin_styles.css'));
	}
	
	/**
	 * Add contextual help to admin page
	 */
	function admin_help() {
		$screen = get_current_screen();
		if ( $screen->id == $this->page ) {
			$help = file_get_contents(dirname(__FILE__) . '/resources/admin_help.html');
			$screen->add_help_tab(array(
				'id'	=> $this->add_prefix('help'),
				'title'	=> __('Overview', 'favicon-rotator'),
				'content'	=> $help,
			));
		}
	}
}