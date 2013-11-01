jQuery(function($){
	/*******************************
	initialize activity
	*******************************/

	cgcaf_initialize_feed();

	function cgcaf_initialize_feed(){
		$.ajax({
			type: "POST",
			url: cgc_scripts.ajaxurl,
			dataType: 'json',
			data: {
				action:'cgcaf_initialize'
			},
			success: function( response ){
				cgcaf_update_feed( response );
			}
		});
	}

	/*******************************
	update activity via heartbeat-api
	*******************************/

	var cgcaf_hb_activity = function(){
		var last = $( '.cgcaf-activity-feed li:first' ).data('key');

		if( ! last )
			last = '';

		var obj = {
			action: 'latest',
			last: last
		};

		return obj;
	};

	wp.heartbeat.enqueue( 'cgcaf-activity', 'cgcaf_hb_activity', false );

	$(document).on('heartbeat-send', function(e, data) {
		var last = $( '.cgcaf-activity-feed li:first' ).data('key');

		if( ! last )
			last = '';

		data['cgcaf_hb_activity'] = {
			action: 'latest',
			last: last
		};
	});

	$(document).on( 'heartbeat-tick.cgcaf-activity', function( e, data ) {
		if( ! data.hasOwnProperty( 'cgcaf-data' ) )
			return;

		cgcaf_update_feed( data['cgcaf-data'] );

	});

	function cgcaf_update_count_icon(){
		var $activity_feed = $( '.cgcaf-activity-feed' );
		var count = $activity_feed.find('li.unread').length;
		var $parent = $activity_feed.parents('li').find('.activity-feed');
		var $unread_count = $( '.unread-feed-count', $parent );

		if( count <= 0 ){
			$unread_count.fadeOut( 'fast' );
			$parent.removeClass( 'unread-items' );
			return;
		}

		$parent.addClass('unread-items');

		// insert the element, we need it.
		if( ! $unread_count.length ){
			$unread_count = $('<b />').addClass('unread-feed-count').html( count ).hide();
			$parent.append( $unread_count );
			$unread_count.fadeIn( 'fast' );
		} else {
			var current_count = parseInt( $unread_count.text(), 10 );

			// be sure there was a change.
			if( current_count == count )
				return;

			$unread_count.wrapInner('<span class="old-count"/>');
			var $new_count = $('<span />').text( count ).css( 'font-weight', 'bold' ).hide();
			$unread_count.append($new_count);

			$unread_count.find('.old-count').slideUp('fast');
			$new_count.slideDown('fast');

			setTimeout( function(){
				$new_count.css( 'font-weight', 'normal' );
			}, 5000 );
		}
	}

	function cgc_fade_out_and_remove( $obj ){
		$obj.fadeOut('fast', function(){
			$obj.remove();
		});
	}

	function cgcaf_update_feed( cgcaf_data ){
		cgcaf_data = cgcaf_data ? cgcaf_data : false;

		var $activity_feed = $( '.cgcaf-activity-feed' );

		if( ! cgcaf_data || ! cgcaf_data.activity.length ){
			// teach about new activity feed.
			if( ! $activity_feed.find('li').length ){
				var $learn_li = $('<li />').addClass('feed-help').html('This is your activity feed. When users you follow upload or favorite images, or if you have a new follower, you will be notified here.');
				$activity_feed.append( $learn_li );
			}
			return;
		}

		if( cgcaf_data.delete_flags && cgcaf_data.delete_flags.length ){
			var total_deletes = cgcaf_data.delete_flags.length;
			for( var d=0; d <= total_deletes; d++ ){
				var $removal = $('li[data-key="' + cgcaf_data.delete_flags[d] + '"]', $activity_feed);
				cgc_fade_out_and_remove( $removal );
			}
			cgcaf_update_count_icon();
		}

		if( $activity_feed.find('.feed-help').length ){
			$activity_feed.find('.feed-help').remove();
		}

		var max_display = cgcaf_data.max_display ? cgcaf_data.max_display : 10;
		var new_activity_count = cgcaf_data.activity.length;
		var current_count = $activity_feed.find('li').length;

		var $append = $('<ul/>');
		for( var i=0; i < new_activity_count; i++ ){
			var new_key = $(cgcaf_data.activity[i]).data('key');
			if( ! $('li[data-key="' + new_key + '"]', $activity_feed).length ) { // don't duplicate items.
				$append.append( cgcaf_data.activity[i] );
			}
		}

		$activity_feed.append( $append.html() );

		if( $activity_feed.find('li').length > max_display ){
			$activity_feed.find('li').slice(max_display).remove();
		}

		$activity_feed.find('.last').removeClass('last').find('li:last').addClass('last');

		cgcaf_update_count_icon();
	}

	/*******************************
	mark items as read upon opening
	*******************************/

	$('.activity-feed').on('click', function(){
		// collect the IDs
		var $activity_feed = $(this);
		var keys = [];

		$.each( $activity_feed.next('.cgcaf-activity-feed').find('li.unread'), function(i, el){
			var $li = $(el);
			var key = $li.data('key');
			if( key ){
				keys[keys.length] = key;

				setTimeout( function(){
					//TODO: Fade out or something here.
					$li.removeClass('unread');
				}, 2000 );
			}
		});

		if( keys.length ){
			$.ajax({
				type: "POST",
				url: cgc_scripts.ajaxurl,
				data: {
					action:'cgcaf_mark_read',
					keys: keys
				},
				success: function(response){
					var $unread_count = $('.unread-feed-count', $activity_feed);
					$unread_count.fadeOut( 'fast' );
				}
			});
		}
	});

});