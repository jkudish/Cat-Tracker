<?php

/**
Plugin Name: Cat Tracker Modifier for catmapper.ca
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Additional modifiers for the Cat Tracking Software
Version: 1.0
Author: Joachim Kudish
Author URI: http://jkudish.com/
License: GPLv2
*/

/**
 * @package Cat Mapper
 * @author Joachim Kudish
 * @version 1.0
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
 * remove admin menus that we don't need for the Cat Tracker
 *
 * @since 1.0
 * @return void
 */
add_action( 'admin_menu', 'cat_mapper_remove_admin_menus' );
function cat_mapper_remove_admin_menus() {
	if ( is_network_admin() )
		return;

	remove_menu_page( 'edit.php' ); // Posts
	remove_menu_page( 'upload.php' ); // Media
	remove_menu_page( 'edit-comments.php' ); // Comments
	remove_menu_page( 'tools.php' ); // Tools
	remove_menu_page( 'plugins.php' ); // Plugins
	remove_submenu_page( 'options-general.php', 'options-writing.php' ); // Writing options
	remove_submenu_page( 'options-general.php', 'options-discussion.php' ); // Discussion options
	remove_submenu_page( 'options-general.php', 'options-reading.php' ); // Reading options
	remove_submenu_page( 'options-general.php', 'options-media.php' ); // Media options
	remove_submenu_page( 'options-general.php', 'options-permalink.php' ); // Permalink options

	if ( ! is_main_site() )
		remove_menu_page( 'edit.php?post_type=page' ); // pages

}

/**
 * remove all help screens
 *
 * @since 1.0
 * @return void
 */
add_action( 'admin_head', 'cat_mapper_remove_screen_help' );
function cat_mapper_remove_screen_help() {
	get_current_screen()->remove_help_tabs();
}

/**
 * filter the map source to display with the custom styles
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_map_source', 'cat_mapper_map_source' );
function cat_mapper_map_source() {
	if ( Cat_Tracker::is_submission_mode() || is_admin() )
		return 'http://b.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/75872/256/{z}/{x}/{y}.png';

	return 'http://b.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/75869/256/{z}/{x}/{y}.png';
}

/**
 * filter the map attribution to display a notice about Cloudmade
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_map_attribution', 'cat_mapper_map_attribution' );
function cat_mapper_map_attribution( $map_attribution ) {
	$map_attribution .= ' &mdash; Map Styles © Cloudmade';
	return $map_attribution;
}

/**
 * deregister post types from main site aka blog ID #1
 *
 * @since 1.0
 * @return void
 */
add_action( 'init', 'catmapper_deregister_post_types', 20 );
function catmapper_deregister_post_types() {
	if ( 1 !== get_current_blog_id() )
		return;

	global $wp_post_types;
	foreach ( array( Cat_Tracker::MAP_POST_TYPE, Cat_Tracker::MARKER_POST_TYPE ) as $post_type ) {
		if ( isset( $wp_post_types[$post_type] ) )
			unset( $wp_post_types[$post_type] );
	}
}

/**
 * do default stuff when a new community is created
 *
 * @since 1.0
 * @param (int) $blog_id, the newly created community's blog id
 * @param (int) $user_id, the newly created community's user id
 * @return void
 */
add_action( 'wpmu_new_blog', 'catmapper_new_community_created', 100, 2 );
function catmapper_new_community_created( $blog_id, $user_id ) {
	global $wp_rewrite, $wpdb, $current_site;
	switch_to_blog( $blog_id );

	// delete default links
	foreach( range( 1, 7 ) as $link_id )
		wp_delete_link( $link_id );

	// delete first comment
	wp_delete_comment( 1 );

	// delete first post & first page
	wp_delete_post( 1 );
	wp_delete_post( 2 );

	// create default sighting types
	wp_create_term( 'TNR Colony', Cat_Tracker::MARKER_TAXONOMY );
	wp_create_term( 'Group of community cats', Cat_Tracker::MARKER_TAXONOMY );
	wp_create_term( 'Community cat', Cat_Tracker::MARKER_TAXONOMY );
	wp_create_term( 'Community cat with kittens', Cat_Tracker::MARKER_TAXONOMY );
	wp_create_term( 'BC SPCA unowned intake - Cat', Cat_Tracker::MARKER_TAXONOMY );
	wp_create_term( 'BC SPCA unowned intake - Kitten', Cat_Tracker::MARKER_TAXONOMY );

	// set default options
	$default_options = array(
		'blogdescription' => 'Cat Mapper by the BC SPCA',
		'timezone_string' => 'America/Vancouver',
		'permalink_structure' => '/%postname%/',
		'default_pingback_flag' => false,
		'default_ping_status' => false,
		'default_comment_status' => false,
		'comment_moderation' => true,
		'sidebars_widgets' => array(),
	);

	foreach ( $default_options as $option_key => $option_value )
		update_option( $option_key, $option_value );

	flush_rewrite_rules();

	// set a default/empty menu
	$menu_id = wp_create_nav_menu( 'blank' );
	$theme = wp_get_theme();
	$theme = $theme->Name;
	$theme_options = get_option( "mods_$theme" );
	$theme_options['nav_menu_locations']['primary'] = $menu_id;
	update_option( "mods_$theme", $theme_options );

	wp_redirect( add_query_arg( array( 'post_type' => Cat_Tracker::MAP_POST_TYPE, 'message' => 11 ), admin_url( 'post-new.php' ) ) );
	exit;
}

/**
 * filter enter title here text on new maps to be the name of the current community
 *
 * @since 1.0
 * @param (string) $title the title to filter
 * @param (object) $post the post object for the current post
 * @return void
 */
add_filter( 'enter_title_here', 'cat_mapper_enter_title_here', 10, 2 );
function cat_mapper_enter_title_here( $title, $post ) {
	if ( Cat_Tracker::MAP_POST_TYPE == get_post_type( $post ) )
		$title = get_bloginfo( 'name' );

	return $title;
}

/**
 * adjust which dashboard widgets show and which don't
 *
 * @since 1.0
 * @return void
 */
add_action( 'wp_dashboard_setup', 'catmapper_adjust_dashboard_widgets' );
function catmapper_adjust_dashboard_widgets() {
	global $wp_meta_boxes;
	// unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']); // right now [content, discussion, theme, etc]
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins'] ); // plugins
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links'] ); // incoming links
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] ); // wordpress blog
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary'] ); // other wordpress news
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] ); // quickpress
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts'] ); // drafts
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments'] ); // comments
}

/**
 * add new fields specific to catmapper
 *
 * @since 1.0
 * @return void
 */
add_action( 'cat_tracker_did_custom_fields', 'cat_mapper_custom_fields' );
function cat_mapper_custom_fields() {
	x_add_metadata_group( 'bcspca_extra_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'BC SCPA Import Info', 'priority' => 'high' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'animal_id', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal ID', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'animal_name', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal name', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'source', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal source', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'breed', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal breed', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'color', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal color', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'gender', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal gender', 'readonly' => true ) );
}

/**
 * exclude bcspca
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_submission_form_dropdown_categories_args', 'cat_mapper_excluded_types_from_submission' );
function cat_mapper_excluded_types_from_submission( $args ) {
	$type_of_sightings = get_terms( Cat_Tracker::MARKER_TAXONOMY, array( 'hide_empty' => false ) );
	if ( empty( $type_of_sightings ) )
		return $args;

	$sighting_ids_and_slugs = array_combine( wp_list_pluck( $type_of_sightings, 'term_id' ), wp_list_pluck( $type_of_sightings, 'slug' ) );
	if ( empty( $sighting_ids_and_slugs ) )
		return $args;

	$excluded_slugs = array(
		'bcspca-unowned-intake-cat',
		'bcspca-unowned-intake-kitten',
		'katies-place-unowned-intake-cat',
		'katies-place-unowned-intake-kitten',
	);

	$excluded_ids = array();
	foreach ( $excluded_slugs as $excluded_slug )
		$excluded_ids[] = array_search( $excluded_slug, $sighting_ids_and_slugs );

	if ( ! empty( $excluded_ids ) )
		$args['exclude'] = $excluded_ids;

	return $args;

/**
 * load css for admin bar
 *
 * @since 1.0
 * @return void
 */
add_action( 'wp_enqueue_scripts', 'cat_mapper_admin_bar_css', 100 );
add_action( 'admin_enqueue_scripts', 'cat_mapper_admin_bar_css', 100 );
add_action( 'login_enqueue_scripts', 'cat_mapper_admin_bar_css', 100 );
function cat_mapper_admin_bar_css() {
	wp_enqueue_style( 'catmapper-universal-styles', plugins_url( 'catmapper-universal-styles.css', __FILE__ ), array(), Cat_Tracker::VERSION );
}