<?php

if( class_exists( 'CGC_Activity_Feed' ) ) return;

class CGC_Activity_Feed {

	var $feed_var = '_cgcaf_feed_data';
	var $delete_flags_var = '_cgcaf_delete_flags';

	function __construct(){

	}

	function initialize(){

		if( ! is_user_logged_in() )
			return;

		add_action( 'init', array( $this, '_init') );
	}

	function _init(){
		add_action( 'wp_enqueue_scripts', array( $this, 'resources' ) );

		add_filter( 'cgcaf_feed_item', array( $this, 'default_feed_item' ), 10, 3 );
		add_filter( 'cgcaf_feed_item_link', array( $this, 'link_feed_item' ), 10, 3 );
		add_filter( 'cgcaf_feed_item_image', array( $this, 'image_feed_item' ), 10, 3 );

		add_action( 'wp_ajax_cgcaf_initialize', array( $this, 'init_feed') );
		add_action( 'wp_ajax_nopriv_cgcaf_initialize', array( $this, 'init_feed') );

		add_filter( 'heartbeat_received', array( $this, 'hb_latest_activity' ), 10, 3 );
		add_filter( 'heartbeat_nopriv_received', array( $this, 'hb_latest_activity' ), 10, 3 );

		add_action( 'wp_ajax_cgcaf_mark_read', array( $this, 'mark_read') );
		add_action( 'wp_ajax_nopriv_cgcaf_mark_read', array( $this, 'mark_read') );

		add_action( 'delete_post', array( $this, '_autodelete' ) );
	}

	function resources(){
		#wp_register_script( 'cgcaf-init', CGCAF_DIR . '/js/cgcaf.js', array( 'heartbeat' ), CGCAF_VERSION );
		wp_register_script( 'cgcaf-init', CGCAF_DIR . '/js/cgcaf.max.js', array( 'heartbeat' ), CGCAF_VERSION );
	}

	function enable(){
		wp_enqueue_script( 'cgcaf-init' );
	}

	function add_item( $user, $item ){
		if( ! $item['type'] )
			return false;

		$new_key = uniqid();
		$item['_read'] = false;
		$item['_key'] = $new_key;
		$item['_timestamp'] = current_time( 'timestamp' );

		$defaults = array(
			'post_id'	=> '',
			'id'		=> '',
			'class'		=> '',
			'href'		=> '',
			'content'	=> ''
		);

		$item = array_merge( $defaults, $item );

		if ( ! is_array( $user ) )
			$user = array( $user );

		foreach( $user as $user_id ){
			$feed = $this->get_items( $user_id );
			$item = apply_filters( 'cgcaf_add_item', $item, $user_id );
			if( ! in_array( $item, $feed ) ){
				$new_feed = $feed;
				array_unshift( $new_feed, $item ); // we want this at the beginning
				update_user_meta( $user_id, $this->feed_var, $new_feed, $feed );
			}
		}
		return $new_key;
	}

	function remove_item( $user, $key ){
		if ( ! is_array( $user ) )
			$user = array( $user );

		foreach( $user as $user_id ){
			$feed = $this->get_items( $user_id );
			$new_feed = $this->_remove_item( $feed, $key );
			update_user_meta( $user_id, $this->feed_var, $new_feed, $feed );
		}
	}

	private function _remove_item( $feed, $item_key ){
		foreach( $feed as $key => $item ){
			if( $item['_key'] == $item_key ){
				unset( $feed[ $key ] );
			}
		}
		return $feed;
	}

	function _autodelete( $post_id ){
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT `user_id`, `meta_value` FROM {$wpdb->usermeta} WHERE `meta_key` = '%s'", $this->feed_var );
		$rows = $wpdb->get_results( $sql );
		$delete_flags = array();
		foreach( $rows as $row ){
			$feed = unserialize( $row->meta_value );
			$update = false;
			foreach( $feed as $key => $item ){
				if( isset( $item['post_id'] ) && $item['post_id'] == $post_id ){
					$delete_flags[] = $item['_key'];
					unset( $feed[ $key ] );
					$update = true;
				}
			}
			if( $update )
				update_user_meta( $row->user_id, $this->feed_var, $feed );
		}

		$this->add_delete_flags( $delete_flags );
	}

	function add_delete_flags( $flags ){
		if( ! $flags )
			return;

		switch_to_blog( 1 ); // store everything on main blog

		$delete_flags = array();
		foreach( $flags as $flag ){
			$delete_flags[$flag] = current_time( 'timestamp' ) + ( 60 * 60 * 24 * 30 ); // expire after 30 days.
		}

		$orig_flags = $this->get_delete_flags();

		if( $orig_flags ){
			foreach( $orig_flags as $flag => $expiration ){
				if( $expiration <= current_time( 'timestamp' ) ){
					unset( $orig_flags[ $flag ] );
				}
			}
		}

		update_option( $this->delete_flags_var, array_merge( $orig_flags, $delete_flags ) );

		restore_current_blog();
	}

	function get_delete_flags(){
		switch_to_blog( 1 ); // store everything on main blog
		$flags = get_option( $this->delete_flags_var );
		restore_current_blog();

		if( ! is_array( $flags ) )
			$flags = array();

		return $flags;
	}

	function get_items( $user = NULL, $limit = NULL, $offset = 0 ){
		if( ! $user )
			$user = get_current_user_id();

		$feed_data = get_user_meta( $user, $this->feed_var, true );
		$total = count( $feed_data );

		if( $limit && $limit > $feed_data ){
			$feed_data = array_slice( $feed_data, $offset, $limit );
		}

		if( ! $feed_data )
			$feed_data = array();

		return $feed_data;
	}

	function get_latest_items( $user = NULL, $reference = NULL ){
		if( ! $user )
			$user = get_current_user_id();

		$feed_data = $this->get_items( $user );

		if( ! $reference )
			return $feed_data;

		$latest_data = array();

		foreach( $feed_data as $item ){
			if( $item['_key'] == $reference )
				break;

			$latest_data[] = $item;
		}

		return $latest_data;

	}

	function activity_feed( $tag = 'ul', $class = '' ){
		$this->enable();
		if( ! $tag ) $tag = 'ul';
		return '<' . $tag . ' class="cgcaf-activity-feed ' . $class . '"></' . $tag . '>';
	}

	function hb_latest_activity( $response, $data, $screen_id ){
		if( isset( $data['cgcaf_hb_activity'] ) ) {

			if( $data['cgcaf_hb_activity']['action'] == 'latest' ){
				$last = $data['cgcaf_hb_activity']['last'];

				$latest = $this->get_latest_items( NULL, $last );

				$activity = array();
				foreach( $latest as $item ){
					if( ! $item['_read'] && strpos( $item['class'], 'unread' ) === false ) {
						$item['class'] .= ' unread';
					}
					$activity[] = $this->build_item( $item, $user );
				}

				$response['cgcaf-data']['activity'] = $activity;
				$response['cgcaf-data']['delete_flags'] = array_keys( $this->get_delete_flags() );
			}
		}
		return $response;
	}

	function build_item( $item, $user ){
		$item = apply_filters( 'cgcaf_item_args', $item, $user );
		$element = apply_filters( 'cgcaf_feed_item', '', $item, $user );
		$element = apply_filters( 'cgcaf_feed_item_' . $item['type'], $element, $item, $user );
		return $element;
	}

	function init_feed(){
		$feed = $this->get_items( NULL, 7 );

		$activity = array();

		foreach( $feed as $item ){
			if( ! $item['_read'] && strpos( $item['class'], 'unread' ) === false ) {
				$item['class'] .= ' unread';
			}
			$activity[] = $this->build_item( $item, $user );
		}

		$response = array(
			'activity' => $activity
		);

		echo json_encode( $response );
		exit();
	}

	function template_link(){
		$template = '<li data-key="%1$s" class="cgcaf-link %4$s"%5$s><a href="%3$s">%2$s</a></li>';
		$template = apply_filters( 'cgcaf_link_template', $template );
		return $template;
	}

	function template_image(){
		$template = '<li data-key="%1$s" class="cgcaf-image %4$s"%5$s><a href="%3$s"><img src="%6$s" class="%7$s" /><span class="image-text">%2$s</span></a></li>';
		$template = apply_filters( 'cgcaf_image_template', $template );
		return $template;
	}

	function default_feed_item( $element, $item, $user ){
		$id = $item['id'] ? ' id="' . $item['id'] . '"' : '';
		$element = sprintf( $this->template_link(),
			$item['_key'],
			$item['content'],
			$item['href'],
			$item['class'],
			$id
		);
		return $element;
	}

	function link_feed_item( $element, $item, $user ){
		// we shouldn't have to do anything, link is the default.
		return $element;
	}

	function image_feed_item( $element, $item, $user ){
		$defaults = array(
			'image' => '',
			'image_class' => 'cgcaf-left'
		);

		$item = array_merge( $defaults, $item );

		$id = $item['id'] ? ' id="' . $item['id'] . '"' : '';

		$element = sprintf( $this->template_image(),
			$item['_key'],
			$item['content'],
			$item['href'],
			$item['class'],
			$id,
			$item['image'],
			$item['image_class']
		);

		return $element;
	}

	function mark_read(){
		if( isset( $_POST['keys'] ) ){
			$has_read = $_POST['keys'];
			if( !is_array( $has_read ))
				exit();

			$user_id = get_current_user_id();
			$feed = $this->get_items( $user_id );
			$new_feed = $feed;
			foreach( $new_feed as &$item ){
				$item['_read'] = current_time( 'timestamp' );
			}
			update_user_meta( $user_id, $this->feed_var, $new_feed, $feed );
		}
		exit();
	}
}
