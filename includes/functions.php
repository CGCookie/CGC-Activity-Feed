<?php

/**
 * Grabs the HTML output for the activity feed
 * @param  int $user_id Optional user ID for feed. Defaults to current user ID
 * @return string HTML output string
 */
function cgcaf_activity_feed( $tag = 'ul', $class = '' ){
	global $cgcaf_plugin;
	return $cgcaf_plugin->activity_feed( $tag, $class );
}

/**
 * Inserts an item into a users feed.
 * @param  mixed $user User ID or array of User IDs
 * @param  array $args Array of arguments
 * @return bool Whether or not it was added
 */
function cgcaf_add_item( $user, $args ){
	global $cgcaf_plugin;
	return $cgcaf_plugin->add_item( $user, $args );
}

/**
 * The items below are for backbone integration, which doesn't exist yet.
 */

/**
 * Outputs Link feed item template
 * @return void
 */
function cgcaf_link(){
	$template = '<li class="cgcaf-link"><a href="#">text</a></li>';
	$template = apply_filters( 'cgcaf_link_template', $template );
	echo '<script id="cgcaf-link">' . $template . '</script>';
}

/**
 * Outputs Image feed item template
 * @return void
 */
function cgcaf_image(){
	$template = '<li class="cgcaf-image"><a href="#"><img src="#" class="cgcaf-left" /><span class="image-text">text</span></a></li>';
	$template = apply_filters( 'cgcaf_image_template', $template );
	echo '<script id="cgcaf-image">' . $template . '</script>';
}