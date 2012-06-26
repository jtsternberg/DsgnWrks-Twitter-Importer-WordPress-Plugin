<?php
/*
Plugin Name: DsgnWrks Twitter Importer
Plugin URI: http://dsgnwrks.pro/twitter-importer/
Description: Allows you to backup your twitter photos while allowing you to have a site to display your twitter archive.
Author URI: http://dsgnwrks.pro
Author: DsgnWrks
Donate link: http://dsgnwrks.pro/give/
Version: 1.0
*/

define( 'DSTWEETS_ID', 'dw-twitter-importer-settings');


add_action('admin_init','dw_twitter_init');
function dw_twitter_init() {

	if ( isset( $_GET['tweetimport'] ) ) {
		set_transient( $_GET['tweetimport'] .'-tweetimportdone', date_i18n( 'l F jS, Y @ h:i:s A', strtotime( current_time('mysql') ) ), 14400 );
	}

	// delete_option( 'dsgnwrks_tweet_options' );
	register_setting(
		'dsgnwrks_twitter_importer_users',
		'dsgnwrks_tweet_registration',
		'dw_twitter_users_validate'
	);
	register_setting(
		'dsgnwrks_twitter_importer_settings',
		'dsgnwrks_tweet_options',
		'dw_twitter_settings_validate'
	);

}

function dw_twitter_users_validate( $opts ) {

	if ( !empty( $opts['user'] ) ) {

		$response = dw_tweet_authenticate( $opts['user'], false );

		$opts['badauth'] = $response['badauth'];
		$opts['noauth'] = $response['noauth'];
	}
	return $opts;
}

function dw_twitter_settings_validate( $opts ) {

	if ( empty( $opts ) ) return;
	foreach ( $opts as $user => $useropts ) {
		foreach ( $useropts as $key => $opt ) {

			if ( $key === 'date-filter' ) {
				$opts[$user][$key] = dw_tweet_filter( $opt, '', '0' );
			} elseif ( $key === 'post-type' ) {
				$opts[$user][$key] = dw_tweet_filter( $opt, '', 'post' );
			} elseif ( $key === 'draft' ) {
				$opts[$user][$key] = dw_tweet_filter( $opt, '', 'draft' );
			} elseif ( $key === 'yy' || $key === 'mm' || $key === 'dd' ) {
				$opts[$user][$key] = dw_tweet_filter( $opt, 'absint', '' );
			} else {
				$opts[$user][$key] = dw_tweet_filter( $opt );
			}

		}
	}

	return $opts;
}

add_action('admin_menu', 'dw_twitter_settings');
function dw_twitter_settings() {

	$plugin_page = add_submenu_page( 'tools.php', 'DsgnWrks Twitter Import Settings', 'Twitter Importer', 'manage_options', DSTWEETS_ID, 'dw_twitter_importer_settings' );
	add_action('admin_print_styles-' . $plugin_page, 'dw_twitter_importer_styles');
	add_action('admin_print_scripts-' . $plugin_page, 'dw_twitter_importer_scripts');
	add_action( 'admin_head-'. $plugin_page, 'dw_twitter_fire_importer' );
}

function dw_twitter_importer_settings() { require_once('settings.php'); }

function dw_twitter_importer_styles() {
	wp_enqueue_style( 'dw-twitter-admin', plugins_url( 'css/admin.css', __FILE__ ) );
}

function dw_twitter_importer_scripts() {
	wp_enqueue_script( 'dw-twitter-admin', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ) );

	$args = array(
	  'public'   => true,
	);
	$cpts = get_post_types( $args );
	foreach ($cpts as $key => $cpt) {
		$taxes = get_object_taxonomies( $cpt );
		if ( !empty( $taxes ) ) $data['cpts'][$cpt][] = $taxes;
	}

	if ( !empty( $data ) ) wp_localize_script( 'dw-twitter-admin', 'dwtwitter', $data );

}

function dw_twitter_fire_importer() {

	if ( isset( $_GET['tweetimport'] ) && isset( $_POST['username'] ) ) {
		add_action( 'all_admin_notices', 'dw_twitter_import' );
	}
}

function dw_twitter_import() {

	$opts = get_option( 'dsgnwrks_tweet_options' );
	$id = $_POST['username'];
	if ( !isset( $_GET['tweetimport'] ) || empty( $id ) ) return;

	$response = dw_tweet_authenticate( $id );

	if ( empty( $response['response'] ) ) {
		echo '<div id="message" class="error"><p>Couldn\'t find a twitter feed. Please check the username.</p></div>';
		$opts[$id]['noauth'] = true;
		update_option( 'dsgnwrks_tweet_options', $opts );
		return;
	} else {

		$body = apply_filters( 'dw_twitter_api', $response['response'] );
	}

	wp_die( '<pre>'. htmlentities( print_r( $body, true ) ) .'</pre>' );

	if ( isset( $body->channel->item ) ) {
		echo '<div id="message" class="updated">';

		$messages = dw_tweet_messages( 'https://api.twitter.com/v1/users/'. $body->user->id .'/media/recent?access_token='. $body->access_token .'&count=80', $opts[$id] );

		while ( !empty( $messages['next_url'] ) ) {
			$messages = dw_tweet_messages( $messages['next_url'], $opts[$id], $messages['message'] );
		}

		foreach ( $messages['message'] as $key => $message ) {
			echo $message;
		}
		echo '</div>';

	}

}

function dw_tweet_messages( $api_url, $opts, $prevmessages = array() ) {

	$api = wp_remote_retrieve_body( wp_remote_get( $api_url ) );
	$data = json_decode( $api );

	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/media.php');
	set_time_limit(300);

	$messages = dw_tweet_loop( $data, $opts );

	$next_url = ( !isset( $data->pagination->next_url ) || $messages['nexturl'] == 'halt' ) ? '' : $data->pagination->next_url;

	$messages = ( isset( $messages['messages'] ) ) ? array_merge( $prevmessages, $messages['messages'] ) : $prevmessages;
	if ( empty( $messages ) && empty( $prevmessages ) ) {
		return array(
			'message' => array( '<p>No new Twitter shots to import</p>' ),
			'next_url' => $next_url,
		);
	} else {
		return array(
			'message' => $messages,
			'next_url' => $next_url,
		);
	}
}

function dw_tweet_loop( $data = array(), $opts = array() ) {

	foreach ($data->data as $tweets) {

		if ( $opts['date-filter'] > $tweets->created_time ) {
			$messages['nexturl'] = 'halt';
			break;
		}

		if ( !empty( $opts['tag-filter'] ) ) {
			$tags = explode( ', ', $opts['tag-filter'] );
			$in_title = false;
			if ( $tags ) {
			    foreach ($tags as $tag) {
			        if ( strpos( $tweets->caption->text, $tag ) ) $in_title = true;
			    }
			}

			if ( !$in_title ) continue;
		}


		$alreadyInSystem = new WP_Query(
			array(
				'post_type' => $opts['post-type'],
				'meta_query' => array(
					array(
						'key' => 'twitter_created_time',
						'value' => $tweets->created_time
					)
				)
			)
		);
		if ( $alreadyInSystem->have_posts() ) {
			continue;
		}

		$messages['messages'][] = jts_twitter_img( $tweets, $opts );
	}
	return $messages;
}

function jts_twitter_img( $tweets, $opts = array(), $tags='' ) {

	global $user_ID;

	$opts = ( empty( $opts ) ) ? get_option( 'dsgnwrks_tweet_options' ) : $opts;

	$loc = ( isset( $tweets->location->name ) ) ? $tweets->location->name : null;

	if ( $loc ) $loc = ' at '. $loc;
	$title = wp_trim_words( $tweets->caption->text, 12 );
	if ( $tags ) {
		$tags = '#'. $tags;
		$title = str_replace( $tags, '', $title );
	}
	$title = ($title) ? $title : 'Untitled';
	$imgurl = $tweets->images->standard_resolution->url;

	$excerpt = $tweets->caption->text;
	if ( $tags ) {
		$tags = '#'. $tags;
		$excerpt = str_replace( $tags, '', $excerpt );
	}
	$excerpt .= ' (Taken with Twitter'. $loc .')';

	$content = '';
	if ( $opts['image'] == 'content' || $opts['image'] == 'both' )
		$content .= '<a href="'. $imgurl .'" ><img src="'. $imgurl .'"/></a>';
	$content .= '<p>'. $excerpt .'</p>';
	$content .= '<p>Twitter filter used: '. $tweets->filter .'</p>';
	$content .= '<p><a href="'. esc_url( $tweets->link ) .'" target="_blank">View in Twitter &rArr;</a></p>';

	if ( !$opts['draft'] ) $opts['draft'] = 'draft';
	if ( !$opts['author'] ) $opts['author'] = $user_ID;

	$post = array(
	  'post_author' => $opts['author'],
	  'post_content' => $content,
	  'post_date' => date( 'Y-m-d H:i:s', $tweets->created_time ),
	  'post_date_gmt' => date( 'Y-m-d H:i:s', $tweets->created_time ),
	  'post_excerpt' => $excerpt,
	  'post_status' => $opts['draft'],
	  'post_title' => $title,
	  'post_type' => $opts['post-type'],
	);
	$new_post_id = wp_insert_post( $post, true );

	apply_filters( 'dw_twitter_post_save', $new_post_id, $tweets );

	$args = array(
		'public' => true,
	);
	$taxs = get_taxonomies( $args, 'objects' );

	foreach ( $taxs as $key => $tax ) {

		if ( $tax->label == 'Format' && !current_theme_supports( 'post-formats' ) ) continue;

		$opts[$tax->name] = !empty( $opts[$tax->name] ) ? esc_attr( $opts[$tax->name] ) : '';

		$taxonomies = explode( ', ', $opts[$tax->name] );

		if ( !empty( $taxonomies ) )
		wp_set_object_terms( $new_post_id, $taxonomies, $tax->name );

	}

	update_post_meta( $new_post_id, 'twitter_created_time', $tweets->created_time );
	update_post_meta( $new_post_id, 'twitter_id', $tweets->id );
	update_post_meta( $new_post_id, 'twitter_location', $tweets->location );
	update_post_meta( $new_post_id, 'twitter_link', esc_url( $tweets->link ) );

	return '<p><strong><em>&ldquo;'. $title .'&rdquo; </em> imported and created successfully.</strong></p>';
}


add_action('current_screen','dw_tweet_redirect_on_deleteuser');
function dw_tweet_redirect_on_deleteuser() {

	if ( isset( $_GET['deleteuser'] ) ) {
		$users = get_option( 'dsgnwrks_tweet_users' );
		foreach ( $users as $key => $user ) {
			if ( $user == $_GET['deleteuser'] ) $delete = $key;
		}
		unset( $users[$delete] );
		update_option( 'dsgnwrks_tweet_users', $users );

		$opts = get_option( 'dsgnwrks_tweet_options' );
		unset( $opts[$_GET['deleteuser']] );
		update_option( 'dsgnwrks_tweet_options', $opts );

		wp_redirect( remove_query_arg( 'deleteuser' ), 307 );
		exit;
	}
}

if ( !function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		if ( null === $more )
			$more = __( '...' );
		$original_text = $text;
		$text = wp_strip_all_tags( $text );
		$words_array = preg_split( "/[\n\r\t ]+/", $text, $num_words + 1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words_array ) > $num_words ) {
			array_pop( $words_array );
			$text = implode( ' ', $words_array );
			$text = $text . $more;
		} else {
			$text = implode( ' ', $words_array );
		}
		return apply_filters( 'wp_trim_words', $text, $num_words, $more, $original_text );
	}

}

function dw_tweet_filter( $opt = '', $filter = '', $else = '' ) {

	if ( empty( $opt ) ) return $else;

	if ( $filter == 'absint' ) return absint( $opt );
	else return esc_attr( $opt );
}

function dw_tweet_authenticate( $user, $return = true ) {

	$feed_url = 'http://twitter.com/statuses/user_timeline/'. $user .'.rss';
	$feed_url = 'https://api.twitter.com/1/statuses/user_timeline.json?screen_name='. $user .'&count=200';
	$response = wp_remote_get( $feed_url );
	// wp_die( '<pre>'. htmlentities( print_r( $response, true ) ) .'</pre>' );
	$body = wp_remote_retrieve_body( $response );
	// $body = simplexml_load_string( $body, "SimpleXMLElement", LIBXML_NOCDATA );
	$body = json_decode( $body );

	if ( $body && !empty( $response['headers']['status'] ) && $response['headers']['status'] == '200 OK' ) {
		if ( $return == false ) $body = null;
		$noauth = '';
		$badauth = 'good';
	} else {
		$body = null;
		$badauth = 'error';
		$noauth = true;
	}

	return array(
		'response' => $body,
		'badauth' => $badauth,
		'noauth' => $noauth,
	);

}
