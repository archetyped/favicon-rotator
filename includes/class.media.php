<?php
// Do not load directly. 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class.base.php';

/**
 * Core properties/methods for Media management
 * @package Favicon Rotator
 * @subpackage Media
 * @author Archetyped
 */
class FVRT_Media extends FVRT_Base {
	/**
	 * Prefix for all instance variables that should be prefixed
	 * @var string
	 */
	var $prefix_var = 'var_';
	
	/**
	 * Query var used to set field media is being selected for
	 * Prefix added upon instantiation
	 * @var string
	 */
	var $var_type = 'media';
	
	/**
	 * Query data identifier
	 * @var string
	 */
	var $var_query_data = 'data';
	
	/**
	 * Query var used to set media upload action
	 * Prefix added upon instantiation
	 * @var string
	 */
	var $var_action = 'action';
	
	/**
	 * ID of variable used to submit selected icon
	 * Prefix added upon instantiation
	 * @var unknown_type
	 */
	var $var_setmedia = 'setmedia';
	
	/**
	 * Mime types for favicon
	 * @var array
	 */
	var $mime_types = array('png', 'gif', 'jpg', 'x-icon');
	
	/**
	 * Arguments for upload URL building
	 * @var array
	 */
	var $upload_url_args;
	
	/**
	 * Intermediate media types
	 * @var array
	 */
	var $types = array();
	
	/**
	 * Name of type for current request
	 * @var string
	 */
	var $type_current = null;
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->init_vars(); 
	}
	
	/* Methods */
	
	function register_hooks() {
		//Modify layout on media upload page
		add_action('admin_print_styles-media-upload-popup', $this->m('upload_styles'));
		
		//Register handler for custom media requests
		add_action('media_upload_' . $this->var_type, $this->m('upload_media'));
		
		//Limit mime types for custom requests
		add_filter('post_mime_types', $this->m('post_mime_types'));
		
		add_filter('media_upload_mime_type_links', $this->m('media_upload_mime_type_links'));
		
		add_filter('parse_query', $this->m('set_query_mime_types'));
		
		//Display custom UI in media item box
		add_filter('attachment_fields_to_edit', $this->m('attachment_fields_to_edit'), 11, 2);
		
		//Resize icons
		add_filter('intermediate_image_sizes', $this->m('add_intermediate_image_size'));
		
		//Modify tabs in upload popup for fields
		add_filter('media_upload_tabs', $this->m('upload_tabs'));
		
		//Modifies media upload query vars so that request is routed through plugin
		add_filter('media_upload_form_url', $this->m('upload_url'), 10, 2);
		
		//Restrict file types in upload file dialog
		add_filter('upload_file_glob', $this->m('upload_file_types'));
	}
	
	/**
	 * Adds icon size to intermediate images
	 * Only added when image is uploaded by plugin
	 * @param array $sizes Names of intermediate sizes
	 * @return array Updated sizes
	 */
	function add_intermediate_image_size($sizes) {
		$p = $this->get_request_props();
		if ( !!$p && $p->width && $p->height ) {
			$crop = true;
			add_image_size($p->type_name, $p->width, $p->height, $crop);
			$sizes[] = $p->type_name;
		}
		return $sizes;
	}
	
	/**
	 * Initialize value of instance variables
	 */
	function init_vars() {
		//Get object variables
		$vars = get_object_vars($this);
		foreach ( $vars as $var => $val ) {
			if ( strpos($var, $this->prefix_var) === 0 )
				$this->{$var} = $this->add_prefix($val);
		}
	}
	
	/**
	 * Sets acceptable file types for uploading
	 * Also sets File dialog description (hacky but works)
	 * @param string $types
	 * @return string Modified file types value & file dialog description
	 */
	function upload_file_types($types) {
		if ( $this->is_custom_media() ) {
			$p = $this->get_request_props();
			$filetypes = '*.' . implode(';*.', $p->file_type);
			$types = esc_js($filetypes) . '",file_types_description: "' . esc_js(__($p->file_desc));
		}
		return $types;
	}
	
	function set_query_mime_types(&$q) {
		$var = 'post_mime_type';
		if ( $this->is_custom_media() && 'attachment' == $q->query_vars['post_type'] && empty($q->query_vars[$var]) ) {
			$qv =& $q->query_vars;
			$p = $this->get_request_props();
			//Set GET variable when single mime type specified (for future queries)
			if ( !!$p && isset($p->file_mime) && is_array($p->file_mime) )
				$qv[$var] = $p->file_mime;
		}
	}
	
	/**
	 * Modifies media upload URL to work with plugin
	 * @param string $url Full admin URL
	 * @param string $type Media type
	 * @return string Modified media upload URL
	 */
	function upload_url($url, $type = null) {
		$args = ( is_array($type) ) ? $type : array();
		$custom = ( ( is_string($type) && 0 === strpos($type, $this->add_prefix('')) ) || !empty($args) ) ? true : $this->is_custom_media($url);
		$p = parse_url($url);
		$p = basename( ( isset($p['path']) ) ? $p['path'] : $url );
		if ( strpos($p, 'media-upload.php') === 0 && $custom ) {
			$defaults = array(
				'type'				=> $this->var_type,
				'tab'				=> 'type'
			);
			$u = ( is_string($type) ) ? null : $url;
			//Parse URL
			$q = wp_parse_args($args, $this->get_request_args($u));
			//Check for tab variable
			if ( isset($q['tab']) && 'type' != $q['tab'] ) {
				//Replace tab value
				$defaults[$this->add_prefix('tab')] = $q['tab'];
			}
			//Move Thickbox query args to end of URL
			$tb = array();
			foreach ( $q as $key => $val ) {
				if ( 0 === strpos($key, 'TB_') ) {
					//Add arg to temp array
					$tb[$key] = $val;
					//Remove from query array
					$defaults[$key] = false;
				}
			}
			unset($q);
			
			//Build URL query
			$args = ( is_array($type) ) ? wp_parse_args($type, $defaults) : $defaults;
			$url = add_query_arg($args, $url);
			//Add thickbox args back to URL
			if ( count($tb) )
				$url = add_query_arg($tb, $url);
		}
		return sanitize_url( $url );
	}
	
	/**
	 * Retrieve source data for icon media
	 * Updates metadata (intermediate sizes, etc.) if necessary
	 * @param int $icon_id Attachment ID
	 * @return array Image data (src, width, height)
	 */
	function get_icon_src($icon_id, $type = null) {
		//Add intermediate size (if necessary)
		$type = $this->set_type_current($type);
		$this->update_attachment_metadata($icon_id, $type);
		if ( !$type ) {
			$p = $this->get_request_props();
			$type = $p->type_name;
		} else {
			$type = $type->type_name;
		}
		$icon = ( wp_attachment_is_image($icon_id) && is_string($type) ) ? wp_get_attachment_image_src($icon_id, $type) : wp_get_attachment_url($icon_id);
		if ( !is_array($icon) )
			$icon = array($icon, 0, 0);
		$this->clear_type_current();
		return $icon;
	}
	
	/**
	 * Updates image intermediate size if necessary
	 * @param int $id Attachment ID
	 */
	function update_attachment_metadata($id, $type = null) {
		$type = $this->get_type($type);
		if ( !$type ) {
			$p = $this->get_request_props();
			if ( !$p )
				return false;
			$type = $p->type_name;
		} else {
			$type = $type->type_name;
		}
		//Generate intermediate size (if necessary)
		if ( wp_attachment_is_image($id) && ( $meta = wp_get_attachment_metadata($id) ) && !isset($meta['sizes'][$type]) ) {
			//Full metadata update
			if ( function_exists('wp_generate_attachment_metadata') ) {
				$data = wp_generate_attachment_metadata($id, get_attached_file($id));
				wp_update_attachment_metadata( $id, $data );
			}
		}
	}
	
	/**
	 * Handles upload/selecting of an icon
	 */
	function upload_media() {
		$errors = array();
		$id = 0;
		//Process media selection
		if ( isset($_POST[$this->var_setmedia]) ) {
			/* Send image data to main post edit form and close popup */
			//Get Attachment ID
			$args = new stdClass();
			$args->id = $this->util->array_key_first( $_POST[ $this->var_setmedia ] );
			//Make sure post is valid
			if ( wp_attachment_is_image($args->id) ) {
				$p = $this->get_request_props();
				
				//Build object of properties to send to parent page
				$icon = $this->get_icon_src($args->id);
				if ( !empty($icon) ) {
					$args->url = $icon[0];
					$meta = wp_get_attachment_metadata($args->id); 
					$args->name = basename( ( isset($meta['file']) && !empty($meta['file']) ) ? $meta['file'] : wp_get_attachment_url($args->id) );
					if ( isset($p->type_name) ) {
						$args->type_name = $p->type_name;
					}
				}
			}
			
			// Build JS output.
			ob_start();
			?>
			<script>
			( function() {
				var win = window.dialogArguments || opener || parent || top;
				win.fvrt.media.setIcon( <?php echo wp_json_encode( $args, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT ) ?> );
			}() );
			</script>
			<?php
			// Output inline JS.
			wp_print_inline_script_tag( wp_remove_surrounding_empty_script_tags( ob_get_clean() ) );
			exit;
		}
		
		//Handle HTML upload
		if ( isset($_POST['html-upload']) && !empty($_FILES) ) {
			$id = media_handle_upload('async-upload', $_REQUEST['post_id']);
			//Clear uploaded files
			unset($_FILES);
			if ( is_wp_error($id) ) {
				$errors['upload_error'] = $id;
				$id = false;
			}
		}
		
		//Display default UI
					
		//Determine media type
		$type = ( isset($_REQUEST['type']) ) ? esc_attr( $_REQUEST['type'] ) : $this->var_type;
		//Determine UI to use (disk or URL upload)
		$upload_form = ( isset($_GET['tab']) && 'type_url' == $_GET['tab'] ) ? 'media_upload_type_url_form' : 'media_upload_type_form';
		//Load UI
		return wp_iframe( $upload_form, $type, $errors, $id );
	}
	
	/**
	 * Loads CSS Styles for media upload pages
	 */
	function upload_styles() {
		if ( $this->is_custom_media() )
			wp_enqueue_style($this->add_prefix('media'), $this->util->get_file_url('css/media.css'));
	}
	
	/**
	 * Filter mime types for custom requests
	 * 
	 * @see `post_mime_types` hook to filter mime types
	 * @see get_post_mime_types()
	 * @uses $_GET to set post_mime_type variable (if necessary)
	 * 
	 * @param array $post_mime_types Default post mime types
	 * @return array Filtered mime types
	 */
	function post_mime_types( $post_mime_types ) {
		global $wp_query;
		if ( $this->is_custom_media() ) {
			$p = $this->get_request_props();
			if ( isset( $p->file_mime ) ) {
				// Save original mime types.
				$mime_types = $post_mime_types;
				// Add additional mime types.
				$mime_types_extra = array(
					'image/png'    => array(
						__( 'PNG Images', 'favicon-rotator' ),
						__( 'Manage PNG Images', 'favicon-rotator' ),
						_n_noop(
							'PNG Image <span class="count">(%s)</span>',
							'PNG Images <span class="count">(%s)</span>'
						),
					),
					'image/gif'    => array(
						__( 'GIF Images', 'favicon-rotator' ),
						__( 'Manage GIF Images', 'favicon-rotator' ),
						_n_noop(
							'GIF Image <span class="count">(%s)</span>',
							'GIF Images <span class="count">(%s)</span>'
						),
					),
					'image/jpeg'   => array(
						__( 'JPG Images', 'favicon-rotator' ),
						__( 'Manage JPG Images', 'favicon-rotator' ),
						_n_noop(
							'JPG Image <span class="count">(%s)</span>',
							'JPG Images <span class="count">(%s)</span>'
						),
					),
					'image/x-icon' => array(
						__( 'ICO Images', 'favicon-rotator' ),
						__( 'Manage ICO Images', 'favicon-rotator' ),
						_n_noop(
							'ICO Image <span class="count">(%s)</span>',
							'ICO Images <span class="count">(%s)</span>'
						),
					),
				);
				// Combine new types and original types.
				$mime_types = wp_parse_args( $mime_types_extra, $mime_types );
				// Format request data.
				$p->file_mime = (array) $p->file_mime;
				// Only include mime types allowed for current request.
				$post_mime_types = array();
				foreach ( $p->file_mime as $mime ) {
					if ( isset( $mime_types[ $mime ] ) ) {
						$post_mime_types[ $mime ] = $mime_types[ $mime ];
					}
				}
			}
		}
		return $post_mime_types;
	}
	
	/**
	 * Filter mime type links for custom requests
	 * @see `media_upload_mime_type_links` hook to filter mime types
	 * @see media_upload_library_form()
	 * @param array $type_links Default mime type links
	 * @return array Filtered mime type links
	 */
	function media_upload_mime_type_links($type_links) {
		global $wp_query;
		if ( $this->is_custom_media() && ( $p = $this->get_request_props() ) && isset($p->file_mime) && count($p->file_mime) == 1 ) {
			//Remove ALL media type link for requests that specify a SINGLE mime type
			array_shift($type_links);
		}
		return $type_links;
	}
	
	/**
	 * Modifies array of form fields to display on Attachment edit form
	 * Array items are in the form:
	 * 'key' => array(
	 * 				  'label' => "Label Text",
	 * 				  'value' => Value
	 * 				  )
	 * 
	 * @return array Form fields to display on Attachment edit form 
	 * @param array $form_fields Associative array of Fields to display on form (@see get_attachment_fields_to_edit())
	 * @param object $attachment Attachment post object
	 */
	function attachment_fields_to_edit($form_fields, $attachment) {
		if ( $this->is_custom_media() ) {
			$post = get_post($attachment);
			//Clear all form fields
			$form_fields = array();
			if ( isset($post->post_mime_type) && 0 === strpos($post->post_mime_type, 'image/') && ( $q = $this->get_request_props() ) && false !== $q ) {
				$html = array();
				$type = 'hidden';
				$name_base = $this->var_query_data . '[' . $post->ID . '][%1$s]';
				$name_base_sub = $name_base . '[%2$s]';
				//Create fields for all custom parameters
				foreach ( (array)$q as $prop => $val ) {
					//Build multiple fields for array values
					if ( is_array($val) ) {
						foreach ( $val as $akey => $aval ) {
							$name = sprintf($name_base_sub, $prop, $akey);
							$html[] = $this->util->build_input_element($type, $name, $aval);
						}
					} else {
						$name = sprintf($name_base, $prop);
						$html[] = $this->util->build_input_element($type, $name, $val);
					}
				}
				//Add custom fields
				if ( !empty($html) ) {
					$form_fields[$this->var_query_data] = array('input' => 'html', 'html' => implode('', $html), 'label' => '');
				}
				
				//Add "Set as Image" button (if valid attachment type)
				$set_as = __( ( isset($q->lbl_set) ) ? $q->lbl_set : 'Set Media' );
				$field_name = sprintf('%1$s[%2$s]', $this->var_setmedia, $post->ID);
				$field_html = $this->util->build_input_element('submit', $field_name, $set_as, array('class' => 'button'));
				$field = array(
					'input'		=> 'html',
					'html'		=> $field_html,
					'label'		=> '',
				);
				$form_fields['buttons'] = $field;
			}
		}
		return $form_fields;
	}
	
	/**
	 * Checks if value represents a valid media item
	 * @param int|object $media Attachment ID or Object to check
	 * @return bool TRUE if item is media, FALSE otherwise
	 */
	function is_media($media) {
		$media =& get_post($media);
		return ( ! empty($media) && 'attachment' == $media->post_type );
	}
	
	/**
	 * Retrieve raw parameters for current media selection/upload request
	 * @return array Parameters
	 */
	function get_request_args($url = null) {
		$q = array();
		$u = null;
		if ( is_string($url) && !empty($url) )
			$u = $url;
		//Use referrer for async uploads
		elseif ( 'async-upload' == basename($_SERVER['SCRIPT_NAME'], '.php') )
			$u = wp_get_referer();
		
		if ( !is_null($u) ) {
			//Parse referrer
			$u = parse_url($u);
			if ( isset($u['query']) )
				parse_str($u['query'], $q);
		} else {
			$q = $_REQUEST;
		}
		return $q;
	}
	
	/**
	 * Retrieve properties of current media request
	 * Retrieves current type as fallback
	 * @param string (optional) $url URL to parse
	 * @return object|bool Properties object (FALSE if no properties exist)
	 */
	function get_request_props($url = null) {
		$p = array();
		$q = $this->get_request_args($url);
		$c = array();
		//Get form post data (if set)
		if ( isset($q[$this->var_setmedia]) && isset($q[$this->var_query_data]) ) {
			$id = $this->util->array_key_first( $q[ $this->var_setmedia ] );
			//Save form data
			if ( isset($q[$this->var_query_data][$id]) ) {
				$c = $q[$this->var_query_data][$id];
				//Remove from args array
				unset($q[$this->var_query_data]);
			}
		}
		$prefix = $this->add_prefix('');
		foreach ( $q as $arg => $val ) {
			//Process all custom query args
			if ( 0 !== strpos($arg, $prefix) )
				continue;
			$arg = substr($arg, strlen($prefix));
			$p[$arg] = $val;
		}
		//Add form data
		if ( !empty($c) )
			$p = array_merge($p, $c);
		
		//Retrieve curren type as callback
		if ( empty($p) ) {
			$p = $this->get_type_current();
			if ( !!$p )
				$p = get_object_vars($p);
		}
			
		//Finalize
		if ( !empty($p) ) {
			//Remap properties
			$remap = array(
				'media'		=> 'type_name'
			);
			foreach ( $remap as $from => $to ) {
				if ( !isset($p[$from]) )
					continue;
				$p[$to] = $p[$from];
				unset($p[$from]);
			}
			
			//Add default properties
			$p = (object) wp_parse_args($p, array(
				'type_name'		=> 'media',
				'width'			=> 0,
				'height'		=> 0
			));
		} else {
			$p = false;
		}
		
		return $p;
	}
	
	/**
	 * Checks whether media upload/selection request is initiated by the plugin
	 * Checks current request by default
	 * @param string $url (optional) URL to check
	 * @return bool TRUE if URL is custom media
	 */
	function is_custom_media($url = null) {
		$type = $this->var_type;
		$q = $this->get_request_args($url);
		return ( isset($q['type']) && $type == $q['type'] ) ? true : false;
	}
	
	/**
	 * Builds URI for media upload iframe
	 * @param string $type Type of media to upload
	 * @return string Upload URI
	 */
	function get_upload_iframe_src($type = 'media', $args = null) {
		//Build Upload URI
		$ret = $this->upload_url(get_upload_iframe_src($type), $args);
		//Return URI
		return $ret;
	}
	
	/**
	 * Loads upload URL arguments into instance variable
	 * @param array $args Arguments for upload URL
	 */
	function load_upload_args($args) {
		if ( !is_array($args) )
			$args = array();
		$this->upload_url_args = $args;
	}
	
	/**
	 * Clears upload URL arguments from instance variable
	 * @uses load_upload_args()
	 */
	function unload_upload_args() {
		$this->load_upload_args(null);
	}
	
	/*-** Field-Specific **-*/
	
	/**
	 * Removes URL tab from media upload popup for fields
	 * Fields currently only support media stored @ website
	 * @param array $default_tabs Media upload tabs
	 * @see media_upload_tabs() for full $default_tabs array
	 * @return array Modified tabs array
	 */
	function upload_tabs($default_tabs) {
		if ( $this->is_custom_media() ) {
			unset($default_tabs['type_url']);
			$p = $this->get_request_props();
		}
		return $default_tabs;
	}
	
	/*-** Post Attachments **-*/
	
	/**
	 * Retrieves matching attachments for post
	 * @param object|int $post Post object or Post ID (Default: current global post)
	 * @param array $args (optional) Associative array of query arguments
	 * @see get_posts() for query arguments
	 * @return array|bool Array of post attachments (FALSE on failure)
	 */
	function post_get_attachments($post = null, $args = '', $filter_special = true) {
		if (!$this->util->check_post($post))
			return false;
		global $wpdb;
		
		//Default arguments
		$defaults = array(
						'post_type'			=>	'attachment',
						'post_parent'		=>	(int) $post->ID,
						'numberposts'		=>	-1
						);
		
		$args = wp_parse_args($args, $defaults);
		
		//Get attachments
		$attachments = get_children($args);
		
		//Filter special attachments
		if ( $filter_special ) {
			$start = '[';
			$end = ']';
			$removed = false;
			foreach ( $attachments as $i => $a ) {
				if ( $start == substr($a->post_title, 0, 1) && $end == substr($a->post_title, -1) ) {
					unset($attachments[$i]);
					$removed = true;
				}
			}
			if ( $removed )
				$attachments = array_values($attachments);
		}
		
		//Return attachments
		return $attachments;
	}
	
	/**
	 * Retrieve the attachment's path
	 * Path = Full URL to attachment - site's base URL
	 * Useful for filesystem operations (e.g. file_exists(), etc.)
	 * @param object|id $post Attachment object or ID
	 * @return string Attachment path
	 */
	function get_attachment_path($post = null) {
		if (!$this->util->check_post($post))
			return '';
		//Get Attachment URL
		$url = wp_get_attachment_url($post->ID);
		//Replace with absolute path
		$path = str_replace(get_bloginfo('wpurl') . '/', ABSPATH, $url);
		return $path;
	}
	
	/**
	 * Retrieves filesize of an attachment
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 * @return int|string Filesize in bytes (@see filesize()) or as formatted string based on parameters
	 */
	function get_attachment_filesize($post = null, $formatted = true) {
		$size = 0;
		if (!$this->util->check_post($post))
			return $size;
		//Get path to attachment
		$path = $this->get_attachment_path($post);
		//Get file size
		if (file_exists($path))
			$size = filesize($path);
		if ($size > 0 && $formatted) {
			$size = (int) $size;
			$label = 'b';
			$format = "%s%s";
			//Format file size
			if ($size >= 1024 && $size < 102400) {
				$label = 'kb';
				$size = intval($size/1024);
			}
			elseif ($size >= 102400) {
				$label = 'mb';
				$size = round(($size/1024)/1024, 1);
			}
			$size = sprintf($format, $size, $label);
		}
		
		return $size;
	}
	
	/**
	 * Prints the attachment's filesize 
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 */
	function the_attachment_filesize($post = null, $formatted = true) {
		echo esc_html( $this->get_attachment_filesize( $post, $formatted ) );
	}
	
	/**
	 * Build output for media item
	 * Based on media type and output type parameter
	 * @param int|obj $media Media object or ID
	 * @param string $type (optional) Output type (Default: source URL)
	 * @return string Media output
	 */
	function get_media_output($media, $type = 'url', $attr = array()) {
		$ret = '';
		$media =& get_post($media);
		//Continue processing valid media items
		if ( $this->is_media($media) ) {
			//URL - Same for all attachments
			if ( 'url' == $type ) {
				$ret = wp_get_attachment_url($media->ID);
			} elseif ( 'link' == $type ) {
				$ret = $this->get_link($media, $attr);
			} else {
				//Determine media type
				$mime = get_post_mime_type($media);
				$mime_main = substr($mime, 0, strpos($mime, '/'));
				
				//Pass to handler for media type + output type
				$handler = implode('_', array('get', $mime_main, 'output'));
				if ( method_exists($this, $handler))
					$ret = $this->{$handler}($media, $type, $attr);
				else {
					//Default output if no handler exists
					$ret = $this->get_image_output($media, $type, $attr);
				}
			}
		}
		
		
		return apply_filters($this->add_prefix('get_media_output'), $ret, $media, $type);
	}
	
	/**
	 * Build HTML for displaying media
	 * Output based on media type (image, video, etc.)
	 * @param int|obj $media (Media object or ID)
	 * @return string HTML for media
	 */
	function get_media_html($media) {
		$out = '';
		return $out;
	}
	
	function get_link($media, $attr = array()) {
		$ret = '';
		$media =& get_post($media);
		if ( $this->is_media($media) ) {
			$attr['href'] = wp_get_attachment_url($media->ID);
			$text = ( isset($attr['text']) ) ? $attr['text'] : basename($attr['href']);
			unset($attr['text']);
			//Build attribute string
			$attr = wp_parse_args($attr, array('href' => ''));
			$attr_string = $this->util->build_attribute_string($attr);
			$ret = '<a ' . $attr_string . '>' . $text . '</a>';
		}
		return $ret;
	}
	
	/**
	 * Builds output for image attachments
	 * @param int|obj $media Media object or ID
	 * @param string $type Output type
	 * @return string Image output
	 */
	function get_image_output($media, $type = 'html', $attr = array()) {
		$ret = '';
		$icon = !wp_attachment_is_image($media->ID);
		
		//Get image properties
		$attr = wp_parse_args($attr, array('alt' => trim(strip_tags( $media->post_excerpt ))));
		list($attr['src'], $attribs['width'], $attribs['height']) = wp_get_attachment_image_src($media->ID, '', $icon);
			
		switch ( $type ) {
			case 'html' :
				$attr_str = $this->util->build_attribute_string($attr);
				$ret = '<img ' . $attr_str . ' />';
				break;
		}
		
		return $ret;
	}
	
	/**
	 * Build HTML IMG element of an Image
	 * @param array $image Array of image properties
	 * 	0:	Source URI
	 * 	1:	Width
	 * 	2:	Height
	 * @return string HTML IMG element of specified image
	 */
	function get_image_html($image, $attributes = '') {
		$ret = '';
		if (is_array($image) && count($image) >= 3) {
			//Build attribute string
			if (is_array($attributes)) {
				$attribs = '';
				$attr_format = '%s="%s"';
				foreach ($attributes as $attr => $val) {
					$attribs .= sprintf($attr_format, $attr, esc_attr($val));
				}
				$attributes = $attribs;
			}
			$format = '<img src="%1$s" width="%2$d" height="%3$d" ' . $attributes . ' />';
			$ret = sprintf($format, $image[0], $image[1], $image[2]);
		}
		return $ret;
	}
	
	/* Media Types */
	
	/**
	 * Add media type to collection
	 * Saves properties as object
	 * @param string $name Type Name
	 * @param array|object $props Type Properties
	 * @return object Type properties
	 */
	function register_type($name, $props = null) {
		$defaults = array(
			'lbl_title' 	=> '',
			'lbl_set'		=> 'Set Media',
			'file_mime'		=> array('image/png', 'image/gif', 'image/jpeg'),
			'file_type'		=> array('png', 'gif', 'jpg'),
			'file_desc'		=> 'Icon Files',
			'width'			=> 0,
			'height'		=> 0
		);
		
		$props = wp_parse_args($props, $defaults);
		$props['type_name'] = $name;
		$this->types[$name] = (object) $props;
		return $this->types[$name];
	}
	
	/**
	 * Retrieve all registered media types
	 * @return array Media types (as a reference)
	 */
	function &get_types() {
		return $this->types;
	}
	
	/**
	 * Retrieve media type
	 * @param object|bool $type Type properties (FALSE if type not registered)
	 */
	function get_type($type) {
		//Normalize
		if ( is_object($type) ) {
			$type = get_object_vars($type);
		}
		if ( is_array($type) && isset($type['type_name']) ) {
			$type = $type['type_name'];
		}
		if ( !is_string($type) ) {
			$type = strval($type);
		}
			
		$types =& $this->get_types();
		//Fetch type
		if ( isset($types[$type]) )
			return $types[$type];
		//Return FALSE if type does not exist
		return false;
	}

	/**
	 * Set type for current request
	 * @param mixed $type Type to set
	 * @return object Current type (normalized)
	 */
	function set_type_current($type) {
		$type = $this->get_type($type);
		if ( !$type )
			$this->clear_type_current();
		else {
			$this->type_current = $type->type_name;	
		}
		return $type;
	}
	
	/**
	 * Retrieve current type
	 * @return object|bool Current type (FALSE if no type set)
	 */
	function get_type_current() {
		return $this->get_type($this->type_current);
	}
	
	/**
	 * Clear type from current request
	 */
	function clear_type_current() {
		$this->type_current = null;
	}
}
