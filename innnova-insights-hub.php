<?php

/*
Plugin Name: Innova Insights Hub
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: The Innova Insights Hub plugin provides an automatic meta-dating for trends, press releases, and webinars. It provides a configuration option for the chatGPT API key.
Version: 0.6
Author: More Awesome B.V.
Author URI: http://moreawesome.co
License: GPL2
*/



require_once plugin_dir_path( __FILE__ ) . 'autoterm.php';
require_once plugin_dir_path( __FILE__ ) . 'InnovaInsightsHubPluginOptions.php';

// Add the 'Auto Meta Data' bulk action to specific post types
function insights_hub_bulk_actions($actions) {
	// Define an array of post types where you want to add the bulk action
		$actions['auto_meta_data'] = 'Add Insights Hub Meta Data';
	return $actions;
}

add_filter('bulk_actions-edit-trends', 'insights_hub_bulk_actions');
add_filter('bulk_actions-edit-webinars', 'insights_hub_bulk_actions');
add_filter('bulk_actions-edit-press-releases', 'insights_hub_bulk_actions');
add_filter('bulk_actions-edit-reports', 'insights_hub_bulk_actions');


// Handle the 'Auto Meta Data' bulk action
function handle_custom_bulk_action() {
	// Check for the action and post IDs.
	$action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
	$post_ids = isset($_REQUEST['post']) ? array_map('intval', $_REQUEST['post']) : array();

	// Get the current screen and check for the post type.
	$screen = get_current_screen();
	if (!$screen) {
		return; // Return early if there's no screen object.
	}

	$post_type = $screen->post_type;

	// Check if our custom action is triggered and there are post IDs to process.
	if ($action === 'auto_meta_data' && !empty($post_ids)) {
		foreach ($post_ids as $post_id) {
			$post = get_post($post_id);
			if ($post) {
				post_auto_term($post);
			}
		}

		// Redirect back with a success message.
		$redirect_to = admin_url('edit.php?post_type=' . $post_type . '&bulk_action=auto_meta_data&message=1');
		wp_redirect($redirect_to);
		exit;
	}
}

add_action('admin_action_auto_meta_data', 'handle_custom_bulk_action');

// Add a success message for the 'Auto Meta Data' bulk action
function add_bulk_action_success_message($messages) {
	if (isset($_REQUEST['bulk_action']) && $_REQUEST['bulk_action'] === 'auto_meta_data' && isset($_REQUEST['message']) && $_REQUEST['message'] == '1') {
		$messages['post'][1] = __('Insights Hub Meta Data updated successfully.');
	}
	return $messages;
}

add_filter('post_updated_messages', 'add_bulk_action_success_message');

add_shortcode ( 'get_term_list', 'innova_get_term_list' );

function innova_get_term_list($atts){

	$a = shortcode_atts( array(
		'taxonomy' => 'category' // defaults to category when attributes are empty
	), $atts );

	$terms = get_terms(array(
		'taxonomy'   => $a['taxonomy'],
		'hide_empty' => 0
	));

	$output = '<ul class="innova-no-dots">';

	foreach ( $terms as $term ) {

		// The $term is an object, so we don't need to specify the $taxonomy.
		$term_link = get_term_link( $term );

		// If there was an error, continue to the next term.
		if ( is_wp_error( $term_link ) ) {
			continue;
		}

		// We successfully got a link. Print it out.
		$output .= '<li><div class="innova-animated-link"><a href="' . esc_url($term_link) . '"><span class="dot"></span>' . esc_html($term->name) . '</a></div></li>';

	}

	$output.= '</ul>';

	return $output;

}

function auto_term_on_new_post($post_id,$post,$update) {

	// Check if this is a new post (not an update)
	if ($post->post_date == $post->post_modified) {
		// Check if the post status is 'publish'
		if ($post->post_status === 'publish')
		post_auto_term( $post );
	}
}

add_action('save_post', 'auto_term_on_new_post',10,3);

register_activation_hook(__FILE__, 'innova_insights_hub_activation' );

function innova_insights_hub_activation() {
	// Code to run on plugin activation

}


