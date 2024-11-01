<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * SliderMe Administration Class
 *
 * All functionality pertaining to the administration sections of SliderMe.
 *
 * @package WordPress
 * @subpackage SliderMe
 * @category Administration
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * - __construct()
 * - admin_styles_global()
 * - add_media_tab()
 * - media_tab_handle()
 * - media_tab_process()
 * - media_tab_js()
 * - popup_fields()
 * - display_special_settings()
 * - add_default_conditional_fields()
 * - conditional_fields_attachments()
 * - conditional_fields_posts()
 * - conditional_fields_slides()
 * - generate_field_by_type()
 * - generate_default_conditional_fields()
 * - generate_conditional_fields_slides()
 * - generate_conditional_fields_posts()
 */
class SliderMe_Admin {
	/**
	 * Constructor.
	 * @since  1.0.0
	 * @return  void
	 */
	public function __construct () {
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_styles_global' ) );
		add_filter( 'media_upload_tabs', array( &$this, 'add_media_tab' ) );
		add_action( 'media_upload_slider_me', array( &$this, 'media_tab_handle' ) );

		add_action( 'admin_print_scripts', array( &$this, 'media_tab_js' ) );
		add_action( 'slider_me_popup_conditional_fields', array( &$this, 'add_default_conditional_fields' ) );
	} // End __construct()

	/**
	 * Load the global admin styles for the menu icon and the relevant page icon.
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_styles_global () {
		global $slider_me;
		wp_register_style( $slider_me->token . '-global', $slider_me->plugin_url . 'assets/css/global.css', '', '1.0.6', 'screen' );
		wp_enqueue_style( $slider_me->token . '-global' );
	} // End admin_styles_global()

	/**
	 * Filter the "Add Media" popup's tabs, to add our own.
	 * @since  1.0.0
	 * @param array $tabs The existing array of tabs.
	 */
	public function add_media_tab ( $tabs ) {
		$tabs['slider_me'] = __( 'Slideshows', 'slider_me' );
		return $tabs;
	} // End add_media_tab()

	/**
	 * Display the tab content in a WordPress iframe.
	 * @since  1.0.0
	 * @return void
	 */
	public function media_tab_handle () {
		wp_iframe( array( &$this, 'media_tab_process' ) );
	} // End media_tab_handle()

	/**
	 * Create the tab content to be displayed.
	 * @since  1.0.0
	 * @uses  global $slider_me Global $slider_me object
	 * @return void
	 */
	public function media_tab_process () {
		global $slider_me;
		media_upload_header();
		$slider_me->post_types->setup_slide_pages_taxonomy();
?>
<form action="media-new.php" method="post" id="slider_me-insert">
	<?php submit_button( __( 'Insert Slideshow', 'slider_me' ) ); ?>
	<?php $this->popup_fields(); ?>
	<p class="hide-if-no-js"><a href="#advanced-settings" class="advanced-settings button"><?php _e( 'Advanced Settings', 'slider_me' ); ?></a></p>
	<div id="slider_me-advanced-settings">
		<div class="updated fade"><p><?php _e( 'Optionally override the default slideshow settings using the fields below.', 'slider_me' ); ?></p></div>
		<?php
			$this->display_special_settings();
			settings_fields( $slider_me->settings->token );
			do_settings_sections( $slider_me->settings->token );
		?>
	</div><!--/#slider_me-advanced-settings-->
	<?php submit_button( __( 'Insert Slideshow', 'slider_me' ) ); ?>
</form>
<?php
	} // End media_tab_process()

	/**
	 * Load the JavaScript to handle the media tab in the "Add Media" popup.
	 * @since  1.0.0
	 * @return void
	 */
	public function media_tab_js () {
		global $slider_me, $pagenow;
		if ( 'media-upload.php' != $pagenow ) return; // Execute only in the Media Upload popup.

		$slider_me->settings->enqueue_field_styles();

		$slider_me->settings->enqueue_scripts();

		wp_enqueue_script( 'slider_me-settings-ranges' );
		wp_enqueue_script( 'slider_me-settings-imageselectors' );

		wp_enqueue_style( 'slider_me-settings-ranges' );
		wp_enqueue_style( 'slider_me-settings-imageselectors' );

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( $slider_me->token . '-media-tab', esc_url( $slider_me->plugin_url . 'assets/js/shortcode-creator' . $suffix . '.js' ), array( 'jquery' ), '1.0.7', false );
		wp_enqueue_script( $slider_me->token . '-media-tab' );

		$settings = $slider_me->settings->get_settings();

		// Allow themes/plugins to filter here.
		$settings['category'] = '';
		$settings['tag'] = '';
		$settings['slide_page'] = '';
		$settings['slider_type'] = '';
		$settings['theme'] = 'default';
		$settings['layout'] = '';
		$settings['overlay'] = '';
		$settings['limit'] = '5';
		$settings['thumbnails'] = '';
		$settings['link_title'] = '';
		$settings['display_excerpt'] = '1';
		$settings['id'] = '';
		$settings['sync'] = '';
		// $settings['as_nav_for'] = '';
		$settings = (array)apply_filters( 'slider_me_popup_settings', $settings );

		wp_localize_script( $slider_me->token . '-media-tab', $slider_me->token . '_settings', $settings );
	} // End media_tab_js()

	/**
	 * Fields specific to the "Add Media" popup.
	 * @since  1.0.0
	 * @return void
	 */
	public function popup_fields () {
		$types = SliderMe_Utils::get_slider_types();

	    $slider_types = array();
	    foreach ( (array)$types as $k => $v ) {
	    	$slider_types[$k] = $v['name'];
	    }
?>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e( 'Slideshow Type', 'slider_me' ); ?></th>
				<td><select id="slider_type" name="slider_me-settings[slider_type]">
					<?php
						foreach ( (array)$slider_types as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '">' . $v . '</option>' . "\n";
						}
					?>
					</select>
					<p><span class="description"><?php _e( 'The type of slideshow to insert', 'slider_me' ); ?></span></p>
				</td>
			</tr>
			<?php
				// Theming engine integration.
				$themes = SliderMe_Utils::get_slider_themes();

				if ( is_array( $themes ) && ( 1 < count( $themes ) ) ) {
			?>
			<tr valign="top">
				<th scope="row"><?php _e( 'Slideshow Theme', 'slider_me' ); ?></th>
				<td><select id="theme" name="slider_me-settings[theme]">
					<?php
						foreach ( (array)$themes as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '">' . $v['name'] . '</option>' . "\n";
						}
					?>
					</select>
					<p><span class="description"><?php _e( 'The desired slideshow theme', 'slider_me' ); ?></span></p>
				</td>
			</tr>
			<?php
				}
			?>
			<tr valign="top">
				<th scope="row"><?php _e( 'Slideshow ID', 'slider_me' ); ?></th>
				<td><input type="text" name="slider_me-settings[id]" id="id" value="" />
					<p><span class="description"><?php _e( 'Give this slideshow a specific ID (optional)', 'slider_me' ); ?></span></p>
				</td>
			</tr>
		</tbody>
	</table>
<?php
		// Allow themes/plugins to act here.
		do_action( 'slider_me_popup_conditional_fields', $types );
	} // End popup_fields()

	/**
	 * Display special settings that can apply to all slideshow types.
	 * @since  1.0.7
	 * @return void
	 */
	private function display_special_settings () {
?>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e( 'Sync', 'slider_me' ); ?></th>
				<td><input type="text" name="slider_me-settings[sync]" id="sync" value="" />
					<p><span class="description"><?php _e( 'Slideshow ID: Mirror the actions performed on this slideshow with another slideshow. Use with care.', 'slider_me' ); ?></span></p>
				</td>
			</tr>
<?php /*
			<tr valign="top">
				<th scope="row"><?php _e( 'As Navigation For', 'slider_me' ); ?></th>
				<td><input type="text" name="slider_me-settings[as_nav_for]" id="as_nav_for" value="" />
					<p><span class="description"><?php _e( 'Slideshow ID: Use this slideshow as navigation for another slideshow. Make sure the number of slides matches.', 'slider_me' ); ?></span></p>
				</td>
			</tr>
*/ ?>
		</tbody>
	</table>
<?php
		// Allow themes/plugins to act here.
		do_action( 'slider_me_popup_special_settings_fields' );
	} // End display_special_settings()

	/**
	 * Setup the conditional fields for the default slider types.
	 * @since  1.0.0
	 * @param  array $types The supported slideshow types.
	 * @return void
	 */
	public function add_default_conditional_fields ( $types ) {
		global $pagenow;
		if ( 'media-upload.php' != $pagenow ) return; // Execute only in the Media Upload popup.

		foreach ( (array)$types as $k => $v ) {
			if ( method_exists( $this, 'conditional_fields_' . $k ) ) {
				echo '<div class="conditional conditional-' . esc_attr( $k ) . '">' . "\n";
				$this->{'conditional_fields_' . $k}();
				echo '</div>' . "\n";
			}
		}
	} // End add_default_conditional_fields()

	/**
	 * Conditional fields, displayed only for the "attachments" slideshow type.
	 * @since  1.0.0
	 * @return void
	 */
	private function conditional_fields_attachments () {
		global $slider_me;

		$fields = $this->generate_conditional_fields_attachments();
?>
	<table class="form-table">
		<tbody>
<?php foreach ( $fields as $k => $v ) { ?>
			<tr valign="top">
				<th scope="row"><?php echo $v['name']; ?></th>
				<td>
					<?php $this->generate_field_by_type( $v['type'], $v['args'] ); ?>
					<?php if ( $v['description'] != '' ) { ?><p><span class="description"><?php echo $v['description']; ?></span></p><?php } ?>
				</td>
			</tr>
<?php } ?>
		</tbody>
	</table>
<?php
	} // End conditional_fields_attachments()

	/**
	 * Conditional fields, displayed only for the "posts" slideshow type.
	 * @since  1.0.0
	 * @return void
	 */
	private function conditional_fields_posts () {
		$fields = $this->generate_conditional_fields_posts();
?>
	<table class="form-table">
		<tbody>
<?php foreach ( $fields as $k => $v ) { ?>
			<tr valign="top">
				<th scope="row"><?php echo $v['name']; ?></th>
				<td>
					<?php $this->generate_field_by_type( $v['type'], $v['args'] ); ?>
					<?php if ( $v['description'] != '' ) { ?><p><span class="description"><?php echo $v['description']; ?></span></p><?php } ?>
				</td>
			</tr>
<?php } ?>
		</tbody>
	</table>
<?php
	} // End conditional_fields_posts()

	/**
	 * Conditional fields, displayed only for the "slides" slideshow type.
	 * @since  1.0.0
	 * @return void
	 */
	private function conditional_fields_slides () {
		global $slider_me;

		$fields = $this->generate_conditional_fields_slides();
?>
	<table class="form-table">
		<tbody>
<?php foreach ( $fields as $k => $v ) { ?>
			<tr valign="top">
				<th scope="row"><?php echo $v['name']; ?></th>
				<td>
					<?php $this->generate_field_by_type( $v['type'], $v['args'] ); ?>
					<?php if ( $v['description'] != '' ) { ?><p><span class="description"><?php echo $v['description']; ?></span></p><?php } ?>
				</td>
			</tr>
<?php } ?>
		</tbody>
	</table>
<?php
	} // End conditional_fields_slides()

	/**
	 * Generate a field from the settings API based on a provided field type.
	 * @since  1.0.0
	 * @param  string $type The type of field to generate.
	 * @param  array $args Arguments to be passed to the field.
	 * @return void
	 */
	public function generate_field_by_type ( $type, $args ) {
		if ( is_array( $args ) && isset( $args['key'] ) && isset( $args['data'] ) ) {
			global $slider_me;
			$default = '';
			if ( isset( $args['data']['default'] ) ) { $default = $args['data']['default']; }

			switch ( $type ) {
				// Text fields.
				case 'text':
					$html = '<input type="text" name="' . esc_attr( $args['key'] ) . '" id="' . esc_attr( $args['key'] ) . '" value="' . esc_attr( $default ) . '" />' . "\n";

					echo $html;
				break;

				// Select fields.
				case 'select':
					$html = '<select name="' . esc_attr( $args['key'] ) . '" id="' . esc_attr( $args['key'] ) . '">' . "\n";
					foreach ( $args['data']['options'] as $k => $v ) {
						$html .= '<option value="' . esc_attr( $k ) . '"' . selected( $k, $default, false ) . '>' . $v . '</option>' . "\n";
					}
					$html .= '</select>' . "\n";

					echo $html;
				break;

				// Single checkbox.
				case 'checkbox':
					$default = '';
					if ( isset( $args['data']['default'] ) ) { $default = $args['data']['default']; }
					$checked = checked( $default, 'true', false) ;
					$html = '<input type="checkbox" id="' . $args['key'] . '" name="' . $args['key'] . '" class="checkbox checkbox-' . esc_attr( $args['key'] ) . '" value="true"' . $checked . ' /> ' . "\n";
					echo $html;

				break;

				// Multiple checkboxes.
				case 'multicheck':
				if ( isset( $args['data']['options'] ) && ( count( (array)$args['data']['options'] ) > 0 ) ) {
					$html = '<div class="multicheck-container" style="height: 100px; overflow-y: auto;">' . "\n";
					foreach ( $args['data']['options'] as $k => $v ) {
						$checked = '';
						$html .= '<input type="checkbox" name="' . $args['key'] . '[]" class="multicheck multicheck-' . esc_attr( $args['key'] ) . '" value="' . esc_attr( $k ) . '"' . $checked . ' /> ' . $v . '<br />' . "\n";
					}
					$html .= '</div>' . "\n";
					echo $html;
				}

				break;

				// Image selectors.
				case 'images':
				if ( isset( $args['data']['options'] ) && ( count( (array)$args['data']['options'] ) > 0 ) ) {
					$html = '';
					foreach ( $args['data']['options'] as $k => $v ) {
						$image_url = $slider_me->plugin_url . '/assets/images/default.png';
						if ( isset( $args['data']['images'][$k] ) ) {
							$image_url = $args['data']['images'][$k];
						}
						$image = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $v ) . '" title="' . esc_attr( $v ) . '" class="radio-image-thumb" />';
						$html .= '<input type="radio" name="' . $args['key'] . '" value="' . esc_attr( $k ) . '" class="radio-images" /> ' . $image . "\n";
					}
					echo $html;
				}
				break;
			}
		}
	} // End generate_field_by_type()

	/**
	 * Generate an array of the conditional fields for the default slider types.
	 * @since  1.0.0
	 * @param  array $types The supported slideshow types.
	 * @return array $fields.
	 */
	public function generate_default_conditional_fields ( $types ) {
		$fields = array();
		foreach ( (array)$types as $k => $v ) {
			if ( method_exists( $this, 'generate_conditional_fields_' . $k ) ) {
				$fields[$k] = (array)$this->{'generate_conditional_fields_' . $k}();
			}
		}

		return $fields;
	} // End generate_default_conditional_fields()

	/**
	 * Generate conditional fields for the "attachments" slideshow type.
	 * @since  1.0.0
	 * @return array $fields An array of fields.
	 */
	private function generate_conditional_fields_attachments () {
		$fields = array();

		$limit_options = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$limit_options[$i] = $i;
		}
		$limit_args = array( 'key' => 'limit', 'data' => array( 'options' => $limit_options, 'default' => 5 ) );
		$thumbnails_args = array( 'key' => 'thumbnails', 'data' => array() );

		// Create final array.
		$fields['limit'] = array( 'name' => __( 'Number of Images', 'slider_me' ), 'type' => 'select', 'args' => $limit_args, 'description' => __( 'The maximum number of images to display', 'slider_me' ) );
		$fields['thumbnails'] = array( 'name' => __( 'Use thumbnails for Pagination', 'slider_me' ), 'type' => 'checkbox', 'args' => $thumbnails_args, 'description' => __( 'Use thumbnails for pagination, instead of "dot" indicators', 'slider_me' ) );

		return $fields;
	} // End generate_conditional_fields_attachments()

	/**
	 * Generate conditional fields for the "slides" slideshow type.
	 * @since  1.0.0
	 * @return array $fields An array of fields.
	 */
	private function generate_conditional_fields_slides () {
		$fields = array();

		// Categories.
		$terms = get_terms( 'slide-page' );
		$terms_options = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $k => $v ) {
				$terms_options[$v->slug] = $v->name;
			}
		}

		$categories_args = array( 'key' => 'slide_page', 'data' => array( 'options' => $terms_options ) );

		$limit_options = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$limit_options[$i] = $i;
		}
		$limit_args = array( 'key' => 'limit', 'data' => array( 'options' => $limit_options, 'default' => 5 ) );
		$thumbnails_args = array( 'key' => 'thumbnails', 'data' => array() );
		$display_featured_image_args = array( 'key' => 'display_featured_image', 'data' => array() );

		// Create final array.
		$fields['limit'] = array( 'name' => __( 'Number of Slides', 'slider_me' ), 'type' => 'select', 'args' => $limit_args, 'description' => __( 'The maximum number of slides to display', 'slider_me' ) );
		$fields['slide_page'] = array( 'name' => __( 'Slide Groups', 'slider_me' ), 'type' => 'multicheck', 'args' => $categories_args, 'description' => __( 'The slide groups from which to display slides', 'slider_me' ) );
		$fields['thumbnails'] = array( 'name' => __( 'Use thumbnails for Pagination', 'slider_me' ), 'type' => 'checkbox', 'args' => $thumbnails_args, 'description' => __( 'Use thumbnails for pagination, instead of "dot" indicators (uses featured image)', 'slider_me' ) );

		return $fields;
	} // End generate_conditional_fields_slides()

	/**
	 * Generate conditional fields for the "posts" slideshow type.
	 * @since  1.0.0
	 * @return array $fields An array of fields.
	 */
	private function generate_conditional_fields_posts () {
		global $slider_me;

		$images_url = $slider_me->plugin_url . '/assets/images/';
		$fields = array();

		// Categories.
		$terms = get_categories();
		$terms_options = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $k => $v ) {
				$terms_options[$v->slug] = $v->name;
			}
		}

		$categories_args = array( 'key' => 'category', 'data' => array( 'options' => $terms_options ) );

		// Tags.
		$terms = get_tags();
		$terms_options = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $k => $v ) {
				$terms_options[$v->slug] = $v->name;
			}
		}

		$tags_args = array( 'key' => 'tag', 'data' => array( 'options' => $terms_options ) );

		$layout_types = SliderMe_Utils::get_posts_layout_types();
		$layout_options = array();

		foreach ( (array)$layout_types as $k => $v ) {
			$layout_options[$k] = $v['name'];
		}

		$layout_images = array(
								'text-left' => esc_url( $images_url . 'text-left.png' ),
								'text-right' => esc_url( $images_url . 'text-right.png' ),
								'text-top' => esc_url( $images_url . 'text-top.png' ),
								'text-bottom' => esc_url( $images_url . 'text-bottom.png' )
							);
		$layouts_args = array( 'key' => 'layout', 'data' => array( 'options' => $layout_options, 'images' => $layout_images ) );

		$overlay_images = array(
								'none' => esc_url( $images_url . 'default.png' ),
								'full' => esc_url( $images_url . 'text-bottom.png' ),
								'natural' => esc_url( $images_url . 'overlay-natural.png' )
							);

		$overlay_options = array( 'none' => __( 'None', 'slider_me' ), 'full' => __( 'Full', 'slider_me' ), 'natural' => __( 'Natural', 'slider_me' ) );

		$overlay_args = array( 'key' => 'overlay', 'data' => array( 'options' => $overlay_options, 'images' => $overlay_images ) );

		$limit_options = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$limit_options[$i] = $i;
		}
		$limit_args = array( 'key' => 'limit', 'data' => array( 'options' => $limit_options, 'default' => 5 ) );
		$thumbnails_args = array( 'key' => 'thumbnails', 'data' => array() );
		$link_title_args = array( 'key' => 'link_title', 'data' => array() );
		$display_excerpt_args = array( 'key' => 'display_excerpt', 'data' => array('default' => '1') );

		// Create final array.
		$fields['limit'] = array( 'name' => __( 'Number of Posts', 'slider_me' ), 'type' => 'select', 'args' => $limit_args, 'description' => __( 'The maximum number of posts to display', 'slider_me' ) );
		$fields['thumbnails'] = array( 'name' => __( 'Use thumbnails for Pagination', 'slider_me' ), 'type' => 'checkbox', 'args' => $thumbnails_args, 'description' => __( 'Use thumbnails for pagination, instead of "dot" indicators (uses featured image)', 'slider_me' ) );
		$fields['link_title'] = array( 'name' => __( 'Link the post title to it\'s post', 'slider_me' ), 'type' => 'checkbox', 'args' => $link_title_args, 'description' => __( 'Link the post title to it\'s single post screen', 'slider_me' ) );
		$fields['display_excerpt'] = array( 'name' => __( 'Display the post\'s excerpt', 'slider_me' ), 'type' => 'checkbox', 'args' => $display_excerpt_args, 'description' => __( 'Display the post\'s excerpt on each slide', 'slider_me' ) );
		$fields['layout'] = array( 'name' => __( 'Layout', 'slider_me' ), 'type' => 'images', 'args' => $layouts_args, 'description' => __( 'The layout to use when displaying posts', 'slider_me' ) );
		$fields['overlay'] = array( 'name' => __( 'Overlay', 'slider_me' ), 'type' => 'images', 'args' => $overlay_args, 'description' => __( 'The type of overlay to use when displaying the post text', 'slider_me' ) );
		$fields['category'] = array( 'name' => __( 'Categories', 'slider_me' ), 'type' => 'multicheck', 'args' => $categories_args, 'description' => __( 'The categories from which to display posts', 'slider_me' ) );
		$fields['tag'] = array( 'name' => __( 'Tags', 'slider_me' ), 'type' => 'multicheck', 'args' => $tags_args, 'description' => __( 'The tags from which to display posts', 'slider_me' ) );

		return $fields;
	} // End generate_conditional_fields_posts()
} // End Class
?>
