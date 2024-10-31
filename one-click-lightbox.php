<?php
/*
Plugin Name: One Click Lightbox
Plugin URI:  https://oneclicklightbox.de
Description: Adds a lightbox to your site
Version:     1.1.2
Author:      Dominik Probst
Author URI:  https://webdesign-probst.de/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: one-click-lightbox
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class One_Click_Lightbox {
	private static $instance;
	const FIELD_PREFIX = 'OCLightbox_';
	const TEXT_DOMAIN = 'one-click-lightbox';

	public static function getInstance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * One_Click_Lightbox constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'ocl_load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_inline_scripts' ) );

		// Add Menu
		add_filter( 'admin_menu', array( $this, 'admin_menu' ) );
		// Add Optionspage
		add_filter( 'admin_init', array( $this, 'options_update' ) );
		// Add Error Output to Settings Page
        add_action( 'admin_notices', array( $this, 'errors_for_admin_notices' ) );
        // Add Success Message to Settings Page
		//add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
		// Plugin love
		add_filter( 'plugin_row_meta', array( $this, 'donate_link' ), 10, 2 );


	}

	/**
	 * Adding Scripts to the Plugin
	 */
	function add_scripts() {
		wp_register_script( 'lightbox', plugin_dir_url( __FILE__ ) . 'js/lightbox.js', '', '', TRUE );
		wp_enqueue_script( 'lightbox' );

		wp_register_style( 'lightbox', plugin_dir_url( __FILE__ ) . 'css/lightbox.css' );
		wp_enqueue_style( 'lightbox' );
	}

	/**
	 * Adding Inline-Scripts to the Plugin
     *
	 */
	function add_inline_scripts() {
		// get the options
	    $options = get_option( 'one-click-lightbox' );

	    // get our variables from the options
		$transparency   = $options['transparency'];
		$activate_automatic_adding = $options['activateautomaticadding'];

		// multiply $transparency (between 0.0 and 1.0) with 100 to get a value between 0 and 100
		$transparency_without_dot = $this->multiply( $transparency );

		// if $transparency is set and not empty, we add a stylesheet with the values
        // from the settings page for the opacity
		if ( isset( $transparency ) && ! empty( $transparency ) ) {
		    $custom_css = '.lightboxOverlay{filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=' . $transparency_without_dot .'); opacity: ' . $transparency . ';}';
			wp_add_inline_style( 'lightbox', $custom_css );
		} else {
			wp_add_inline_style( 'lightbox', 'filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=80);
                                              opacity: 0.8;' );
		}
		// if the automatic adding of the lightbox attribute is activated just add this jQuery
		if ( $activate_automatic_adding == true ) {
			wp_add_inline_script( 'lightbox', (
			'jQuery("a[href$=\'.jpg\'],a[href$=\'.png\'],a[href$=\'.gif\'],a[href$=\'.jpeg\'],a[href$=\'.bmp\']").attr(\'data-lightbox\', \'roadtrip\');'
			)
			);
		}
		// add jQuery for the album label
		wp_add_inline_script( 'lightbox', (
			'jQuery(".no-lightbox").removeAttr(\'data-lightbox\');
            lightbox.option({\'albumLabel\': "' . __( 'Picture %1 of %2', 'one-click-lightbox' ) . '"});'
		)
		);
	}

	/**
	 * Admin Menu
	 */
	function admin_menu() {
		add_menu_page(
			__( 'One Click Lightbox Options', 'one-click-lightbox' ), //Settings Page Title
			'One Click Lightbox', //Title of the Menu Item
			'manage_options',
			'oneclicklightbox_opts',
			array( $this, 'options_page' ),
			'dashicons-format-gallery'
		);
	}

	/**
	 * Update the Options
	 */
	public function options_update() {
		register_setting( 'one-click-lightbox', 'one-click-lightbox', array( $this, 'validate' ) );
	}

	/**
     * validate input
     *
	 * @param $input = input we get from the settings page
	 *
	 * @return array = valid data for the plugin
	 */
	public function validate( $input ) {
		//All inputs
		$valid = array();

		//If activateautomaticadding = TRUE, the Script will be loaded. Otherwise not
		$valid['activateautomaticadding']  = ( isset( $input['activateautomaticadding'] ) && ! empty( $input['activateautomaticadding'] ) ) ? 1 : 0;

		//transparency: between 0.0 and 1.0
        $valid['transparency'] = ( isset( $input['transparency'] ) && preg_match("/^(0(?:\.\d+)?|1(?:\.0+)?)$/", $input['transparency'] ) ) ? sanitize_text_field( $input['transparency'] ) : $input['transparency'];
        if ( !empty( $valid['transparency'] ) && !preg_match("/^(0(?:\.\d+)?|1(?:\.0+)?)$/", $valid['transparency'] ) ) { // if user insert a Number thats not between 0.0 and 1.0, or maybe with a ","
            add_settings_error(
                'lightbox_errors',                     // Setting title
                'transparency_lightbox_numbererror',   // Error ID
                __( 'Transparency Error: Please enter a valid number between 0.0 and 1.0. Resetting value to default: 0.8', 'one-click-lightbox' ),     // Error message
                'error'                                // Type of message
            );
            //setting the default value for transparency
            $valid['transparency'] = 0.8;
        }
		return $valid;
	}

	/**
	 * Adding the function to display errors on settings page
	 */
	function errors_for_admin_notices() {
		settings_errors('lightbox_errors');
	}

	/**
	 * Displays a Success-Message on the Settings Page
	 */
	/*
	function admin_notice_success() {
		global $pagenow;
		if ( $pagenow == 'admin.php' ) { ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Saved Changes!', 'one-click-lightbox' ); ?></p>
            </div>
			<?php
		}
	}*/

	/**
     * Multiply input with 100
     *
	 * @param $input = value of transparency
	 *
	 * @return int = transparency multiplied with 100 and without dot
	 */
	function multiply( $input ){
	    return absint( $input * 100 );
    }
	/**
	 * Callback for options page
	 */
	function options_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'One Click Lightbox Options', 'one-click-lightbox' ); ?></h1>
            <hr />
            <h3><?php _e( 'You have two ways to enable the lightbox:', 'one-click-lightbox' ); ?></h3>
            <div class="postbox">
                <div class="inside">
                    <h2><span><?php _e('#1 - If you want the Lightbox for a small amount of images', 'one-click-lightbox'); ?></span></h2>
                     <p><?php _e( 'You manually add this attribute <code>data-lightbox="roadtrip"</code> to all anchors that point to an image.', 'one-click-lightbox'); ?></p>
                     <p><?php _e( 'Example: <code>&lt;a href="link-to-your-image.jpg" data-lightbox="roadtrip"&gt;Here is your content, maybe an &lt;img&gt;-tag or whatever&lt;/a&gt;</code>', 'one-click-lightbox'); ?></p>
                </div>
            </div>

            <form method="post" action="options.php">

				<?php
				//Grab all options
				$options = get_option( 'one-click-lightbox' );

				//Lightbox
                $transparency = $options['transparency'];
				$activate_automatic_adding = $options['activateautomaticadding'];

				?>

				<?php
				settings_fields( 'one-click-lightbox' );
				do_settings_sections( 'one-click-lightbox' );
				?>
                <div class="postbox">
                    <div class="inside">
                        <h2><span><?php _e('#2 - If you need the Lightbox for every image', 'one-click-lightbox'); ?></span></h2>
                        <p><?php _e( 'You check the following checkbox. That will automatically add the attribute from above to all anchors that point to an image.', 'one-click-lightbox'); ?></p>
                        <p><?php _e ( 'If that should not work for you, you can simply add <code>data-lightbox="roadtrip"</code> to the specific anchor. Normally that should not be necessary.', 'one-click-lightbox'); ?></p>
                        <p><?php _e( 'It is also possible to deactivate the Lightbox for specific images. Simply add <code>class="no-lightbox"</code> to your anchor like this: <pre><code>&lt;a href="link-to-your-image.jpg" class="no-lightbox"&gt;Here is your content&lt;/a&gt;</code></pre>', 'one-click-lightbox' ); ?></p>
                        <hr />
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php _e( 'Activate automatic adding', 'one-click-lightbox' ); ?></span>
                            </legend>
                            <label for="<?php echo 'one-click-lightbox'; ?>-activateautomaticadding">
                                <span><?php esc_attr_e( 'Activate to add data-lightbox="roadtrip" to every anchor automatically:', 'one-click-lightbox' ); ?></span>
                                <input type="checkbox" id="<?php echo 'one-click-lightbox'; ?>-activateautomaticadding"
                                       name="<?php echo 'one-click-lightbox'; ?>[activateautomaticadding]"
                                       value="1" <?php checked($activate_automatic_adding, 1); ?> />
                            </label>
                        </fieldset>
                    </div>
                </div>
				<hr />
                <h2><?php _e('For Advanced Users only!', 'one-click-lightbox'); ?></h2>
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php _e( 'Transparency', 'one-click-lightbox' ); ?></span>
					</legend>
					<label for="<?php echo 'one-click-lightbox'; ?>-transparency">
                        <span><?php esc_attr_e( 'Transparency (between 0.0 and 1.0):', 'one-click-lightbox' ); ?></span>
                        <input type="text" id="<?php echo 'one-click-lightbox'; ?>-transparency"
						       name="<?php echo 'one-click-lightbox'; ?>[transparency]"
                               class="small-text"
						       value="<?php if( !empty( $transparency ) ) {echo $transparency;} else echo '0.8'; ?>" />
					</label>
				</fieldset>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adding Translation Files
	 */
	function ocl_load_textdomain() {
		// modified slightly from https://gist.github.com/grappler/7060277#file-plugin-name-php
		$domain = self::TEXT_DOMAIN;
		$locale = apply_filters('plugin_locale', get_locale(), $domain);

		// wp-content/languages/plugin-name/plugin-name-de_DE.mo
		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );

		// wp-content/plugins/plugin-name/languages/plugin-name-de_DE.m
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Activate Function
	 */
	static function activate() {
		flush_rewrite_rules();
	}

	/**
	 * Add donate link to plugin description in /wp-admin/plugins.php
	 *
	 * @param  array  $plugin_meta
	 * @param  string $plugin_file
	 *
	 * @return array
	 */
	public function donate_link( $plugin_meta, $plugin_file ) {

		if ( plugin_basename( __FILE__ ) == $plugin_file ) {
			$plugin_meta[ ] = sprintf(
				'&hearts; <a href="%s">%s</a>',
				'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=BJC2PX56MX2ZG',
				__( 'Donate', 'one-click-lightbox' )
			);
		}

		return $plugin_meta;
	}
}

One_Click_Lightbox::getInstance();

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
register_activation_hook( __FILE__, 'One_Click_Lightbox::activate' );