<?php

/**
 * bp_core_add_admin_bar_css()
 *
 * Add the CSS needed for the admin bar on blogs (other than the root) and in the admin area.
 * 
 * @package BuddyPress Core
 * @uses get_option() Selects a site setting from the DB.
 */
function bp_core_add_admin_bar_css() {
	global $current_blog;
	
	if ( defined( 'BP_DISABLE_ADMIN_BAR' ) )
		return false;

	/* Fetch the admin bar css from the active theme location */
	if ( file_exists( WP_CONTENT_DIR . '/themes/' . get_blog_option( BP_ROOT_BLOG, 'stylesheet' ) . '/_inc/css/adminbar.css' ) )
		$admin_bar_css = WP_CONTENT_URL . '/themes/' . get_blog_option( BP_ROOT_BLOG, 'stylesheet' ) . '/_inc/css/adminbar.css';
	else if ( file_exists( WP_CONTENT_DIR . '/' . get_blog_option( BP_ROOT_BLOG, 'template' ) . '/_inc/css/adminbar.css' ) )
		$admin_bar_css = WP_CONTENT_URL . '/themes/' . get_blog_option( BP_ROOT_BLOG, 'template' ) . '/_inc/css/adminbar.css';
	else
		$admin_bar_css = BP_PLUGIN_URL . '/bp-core/deprecated/css/admin-bar.css';

	wp_enqueue_style( 'bp-admin-bar', apply_filters( 'bp_core_admin_bar_css', $admin_bar_css ) );
}
add_action( 'admin_menu', 'bp_core_add_admin_bar_css' );
add_action( 'template_redirect', 'bp_core_add_admin_bar_css' );

/**
 * bp_core_add_admin_bar_js()
 *
 * Add the minor JS needed for the admin bar.
 * 
 * @package BuddyPress Core
 * @uses get_option() Selects a site setting from the DB.
 */
function bp_core_add_admin_bar_js() {
	wp_enqueue_script( 'bp-admin-bar-js', BP_PLUGIN_URL . '/bp-core/js/admin-bar.js', array( 'jquery' ) );
}
add_action( 'admin_menu', 'bp_core_add_admin_bar_js' );
add_action( 'wp', 'bp_core_add_admin_bar_js' );

/**
 * bp_core_admin_menu_icon_css()
 *
 * Add a hover-able icon to the "BuddyPress" wp-admin area menu.
 * 
 * @package BuddyPress Core
 */
function bp_core_admin_menu_icon_css() {
	global $bp;
?>
	<style type="text/css">
		ul#adminmenu li.toplevel_page_bp-general-settings .wp-menu-image a { background-image: url( <?php echo BP_PLUGIN_URL . '/bp-core/images/admin_menu_icon.png' ?> ) !important; background-position: -1px -32px; }
		ul#adminmenu li.toplevel_page_bp-general-settings:hover .wp-menu-image a { background-position: -1px 0; }
		ul#adminmenu li.toplevel_page_bp-general-settings .wp-menu-image a img { display: none; }
	</style>
<?php
}
add_action( 'admin_head', 'bp_core_admin_menu_icon_css' );
	
function bp_core_confirmation_js() {
?>
	<script type="text/javascript"> jQuery(document).ready( function() { jQuery("a.confirm").click( function() { if ( confirm( '<?php _e( 'Are you sure?', 'buddypress' ) ?>' ) ) return true; else return false; }); });</script>
<?php
}
add_action( 'wp_head', 'bp_core_confirmation_js', 100 );

/**
 * bp_core_add_jquery_cropper()
 *
 * Makes sure the jQuery jCrop library is loaded.
 * 
 * @package BuddyPress Core
 */
function bp_core_add_jquery_cropper() {
	wp_enqueue_script( 'jcrop', array( 'jquery' ) );
	add_action( 'wp_head', 'bp_core_add_cropper_inline_js' ); 
	add_action( 'wp_head', 'bp_core_add_cropper_inline_css' ); 
}

/**
 * bp_core_add_cropper_inline_js()
 *
 * Adds the inline JS needed for the cropper to work on a per-page basis.
 * 
 * @package BuddyPress Core
 */
function bp_core_add_cropper_inline_js() {
	global $bp;
	
	$image = apply_filters( 'bp_inline_cropper_image', getimagesize( $bp->avatar_admin->image->dir ) );

	$aspect_ratio = 1; 
	
	/* Calculate Aspect Ratio */
	if ( (int) constant( 'BP_AVATAR_FULL_HEIGHT' ) && ( (int) constant( 'BP_AVATAR_FULL_WIDTH' ) != (int) constant( 'BP_AVATAR_FULL_HEIGHT' ) ) ) 
	     $aspect_ratio = (int) constant( 'BP_AVATAR_FULL_WIDTH' ) / (int) constant( 'BP_AVATAR_FULL_HEIGHT' );
?>
	<script type="text/javascript">
		jQuery(window).load( function(){
			jQuery('#avatar-to-crop').Jcrop({
				onChange: showPreview,
				onSelect: showPreview,
				onSelect: updateCoords,
				aspectRatio: <?php echo $aspect_ratio ?>,
				setSelect: [ 50, 50, 200, 200 ]
			});
		});

		function updateCoords(c) {
			jQuery('#x').val(c.x);
			jQuery('#y').val(c.y);
			jQuery('#w').val(c.w);
			jQuery('#h').val(c.h);
		};

		function showPreview(coords) {
			if ( parseInt(coords.w) > 0 ) {
				var rx = <?php echo (int) constant( 'BP_AVATAR_FULL_WIDTH' ) ?> / coords.w;
				var ry = <?php echo (int) constant( 'BP_AVATAR_FULL_HEIGHT' ) ?> / coords.h;

				jQuery('#avatar-crop-preview').css({
				<?php if ( $image ) : ?>
					width: Math.round(rx * <?php echo $image[0] ?>) + 'px',
					height: Math.round(ry * <?php echo $image[1] ?>) + 'px',
				<?php endif; ?>
					marginLeft: '-' + Math.round(rx * coords.x) + 'px',
					marginTop: '-' + Math.round(ry * coords.y) + 'px'
				});
			}
		}	
	</script>
<?php
}

/**
 * bp_core_add_cropper_inline_css()
 *
 * Adds the inline CSS needed for the cropper to work on a per-page basis.
 * 
 * @package BuddyPress Core
 */
function bp_core_add_cropper_inline_css() {
	global $bp;
?>
	<style type="text/css">
		.jcrop-holder { text-align: left; }
		.jcrop-vline, .jcrop-hline { font-size: 0; position: absolute; background: white top left repeat url( <?php echo BP_PLUGIN_URL ?>/bp-core/images/Jcrop.gif ); }
		.jcrop-vline { height: 100%; width: 1px !important; }
		.jcrop-hline { width: 100%; height: 1px !important; }
		.jcrop-handle { font-size: 1px; width: 7px !important; height: 7px !important; border: 1px #eee solid; background-color: #333; *width: 9px; *height: 9px; }
		.jcrop-tracker { width: 100%; height: 100%; }
		.custom .jcrop-vline, .custom .jcrop-hline { background: yellow; }
		.custom .jcrop-handle { border-color: black; background-color: #C7BB00; -moz-border-radius: 3px; -webkit-border-radius: 3px; }
		#avatar-crop-pane { width: <?php echo BP_AVATAR_FULL_WIDTH ?>px; height: <?php echo BP_AVATAR_FULL_HEIGHT ?>px; overflow: hidden; }
		#avatar-crop-submit { margin: 20px 0; }
		#avatar-upload-form img { border: none !important; }
	</style>
<?php
}

/**
 * bp_core_add_ajax_url_js()
 *
 * Adds AJAX target URL so themes can access the WordPress AJAX functionality.
 * 
 * @package BuddyPress Core
 */
function bp_core_add_ajax_url_js() {
	global $bp;
	
	echo 
'<script type="text/javascript">var ajaxurl = "' . $bp->root_domain . str_replace( 'index.php', 'wp-load.php', $_SERVER['SCRIPT_NAME'] ) . '";</script>
';
}
add_action( 'wp_head', 'bp_core_add_ajax_url_js' );

/**
 * bp_core_override_adminbar_css()
 *
 * Overrides the theme's admin bar CSS to hide the adminbar if disabled.
 * 
 * @package BuddyPress Core
 */
function bp_core_override_adminbar_css() { 
	if ( defined( 'BP_DISABLE_ADMIN_BAR' ) || ( get_site_option( 'hide-loggedout-adminbar' ) && !is_user_logged_in() ) ) {
	?>
<style type="text/css">body { padding-top: 0 !important; } #wp-admin-bar { display: none; }</style>
	<?php }
}
add_action( 'wp_footer', 'bp_core_override_adminbar_css' );
?>