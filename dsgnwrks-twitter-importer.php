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
		$validated = dw_twitter_user_validate( $opts['user'] );

		if ( $validated ) {
			$response = dw_tweet_authenticate( $opts['user'], false );

			$opts['badauth'] = $response['badauth'];
			$opts['noauth'] = $response['noauth'];

			$settings = get_option( 'dsgnwrks_tweet_options' );
			$settings['username'] = $opts['user'];
			update_option( 'dsgnwrks_tweet_options', $settings );

		} else {
			// unset( $opts['user'] );
			$opts['badauth'] = 'error';
			$opts['noauth'] = true;
		}
	}
	return $opts;
}

function dw_twitter_user_validate( $username ) {
    return preg_match( '/^[A-Za-z0-9_]+$/', $username );
}

function dw_twitter_settings_validate( $opts ) {

	if ( empty( $opts ) ) return;
	foreach ( $opts as $user => $useropts ) {
		if ( $user == 'username' ) continue;
		foreach ( $useropts as $key => $opt ) {

			if ( $key === 'date-filter' ) {
				if ( empty( $opts[$user]['mm'] ) && empty( $opts[$user]['dd'] ) && empty( $opts[$user]['yy'] ) || !empty( $opts[$user]['remove-date-filter'] ) ) {
					$opts[$user][$key] = 0;
				}
				else {
					$opts[$user][$key] = strtotime( $opts[$user]['mm'] .'/'. $opts[$user]['dd'] .'/'. $opts[$user]['yy'] );
				}
			} elseif ( $key === 'post-type' ) {
				$opts[$user][$key] = dw_tweet_filter( $opt, '', 'post' );
			} elseif ( $key === 'draft' ) {
				$opts[$user][$key] = dw_tweet_filter( $opt, '', 'draft' );
			} elseif ( $key === 'yy' || $key === 'mm' || $key === 'dd' ) {
				if ( empty( $opts[$user]['mm'] ) && empty( $opts[$user]['dd'] ) && empty( $opts[$user]['yy'] ) || !empty( $opts[$user]['remove-date-filter'] ) ) {
					$opts[$user][$key] = '';
				}
				else {
					$opts[$user][$key] = dsgnwrks_filter( $opt, 'absint', '' );
				}
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
	wp_enqueue_script( 'dw-twitter-admin', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), '1.1' );

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

	if ( isset( $_GET['tweetimport'] ) && isset( $_POST['dsgnwrks_tweet_options']['username'] ) ) {
		add_action( 'all_admin_notices', 'dw_twitter_import' );
	}
}

function dw_twitter_import() {

	$opts = get_option( 'dsgnwrks_tweet_options' );
	$id = $_POST['dsgnwrks_tweet_options']['username'];
	if ( !isset( $_GET['tweetimport'] ) || empty( $id ) ) return;

	$response = dw_tweet_authenticate( $id );

	if ( empty( $response['response'] ) ) {
		echo '<div id="message" class="error"><p>Couldn\'t find a twitter feed. Please check the username.</p></div>';
		$opts[$id]['noauth'] = true;
		update_option( 'dsgnwrks_tweet_options', $opts );
		return;
	} else {

		$tweets = apply_filters( 'dw_twitter_api', $response['response'] );
	}

	echo '<div id="message" class="updated">';
	$messages = dw_tweet_messages( $tweets, $opts[$id] );

	while ( !empty( $messages['next_url'] ) ) {
		$messages = dw_tweet_messages( $messages['next_url'], $opts[$id], $messages['message'] );
	}

	foreach ( $messages['message'] as $key => $message ) {
		echo $message;
	}
	echo '</div>';
}

function dw_tweet_messages( $tweets, $opts, $prevmessages = array() ) {

	$messages = dw_tweet_loop( $tweets, $opts );

	$next_url = ( !isset( $tweets->pagination->next_url ) || $messages['nexturl'] == 'halt' ) ? '' : $tweets->pagination->next_url;

	$messages = ( isset( $messages['messages'] ) ) ? array_merge( $prevmessages, $messages['messages'] ) : $prevmessages;
	if ( empty( $messages ) && empty( $prevmessages ) ) {
		return array(
			'message' => array( '<p>No new tweets to import</p>' ),
			'next_url' => $next_url,
		);
	} else {
		return array(
			'message' => $messages,
			'next_url' => $next_url,
		);
	}
}

function dw_tweet_loop( $tweets = array(), $opts = array() ) {

	foreach ( $tweets as $tweet ) {

		if ( $opts['date-filter'] > strtotime( $tweet->created_at ) ) {
			$messages['nexturl'] = 'halt';
			break;
		}

		if ( !empty( $opts['tag-filter'] ) ) {
			$tags = explode( ', ', $opts['tag-filter'] );
			$in_title = false;
			if ( $tags ) {
			    foreach ($tags as $tag) {
			        if ( strpos( $tweet->text, '#'.$tag ) ) $in_title = true;
			    }
			}

			if ( !$in_title ) continue;
		}

		$alreadyInSystem = new WP_Query(
			array(
				'post_type' => $opts['post-type'],
				'meta_query' => array(
					array(
						'key' => 'tweet_id',
						'value' => $tweet->id_str
					)
				)
			)
		);
		if ( $alreadyInSystem->have_posts() ) {
			continue;
		}

		$messages['messages'][] = dw_tweet_save( $tweet, $opts );
	}
	return !empty( $messages ) ? $messages : array();
}

function dw_tweet_save( $tweet, $opts = array() ) {

	global $user_ID;

	$opts = ( empty( $opts ) ) ? get_option( 'dsgnwrks_tweet_options' ) : $opts;

	if ( !isset( $opts['draft'] ) ) $opts['draft'] = 'draft';
	if ( !isset( $opts['author'] ) ) $opts['author'] = $user_ID;

	$post = array(
	  'post_author' => $opts['author'],
	  'post_content' => $tweet->text,
	  'post_date' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
	  'post_date_gmt' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
	  'post_status' => $opts['draft'],
	  'post_title' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
	  'post_type' => $opts['post-type'],
	);
	$new_post_id = wp_insert_post( $post, true );

	apply_filters( 'dw_twitter_post_save', $new_post_id, $tweet );

	$taxs = get_taxonomies( array( 'public' => true ), 'objects' );
	foreach ( $taxs as $key => $tax ) {

		if ( $tax->label == 'Format' && !current_theme_supports( 'post-formats' ) ) continue;

		$opts[$tax->name] = !empty( $opts[$tax->name] ) ? esc_attr( $opts[$tax->name] ) : '';

		$taxonomies = explode( ', ', $opts[$tax->name] );

		if ( !empty( $taxonomies ) )
		wp_set_object_terms( $new_post_id, $taxonomies, $tax->name );
	}

	update_post_meta( $new_post_id, 'tweet_source', $tweet->source );
	update_post_meta( $new_post_id, 'tweet_id', $tweet->id_str );
	if ( !empty( $tweet->in_reply_to_status_id_str ) )
	update_post_meta( $new_post_id, 'in_reply_to_status_id_str', $tweet->in_reply_to_status_id_str );
	if ( !empty( $tweet->in_reply_to_user_id ) )
	update_post_meta( $new_post_id, 'in_reply_to_user_id', $tweet->in_reply_to_user_id );
	if ( !empty( $tweet->in_reply_to_screen_name ) )
	update_post_meta( $new_post_id, 'in_reply_to_screen_name', $tweet->in_reply_to_screen_name );

	return '<p><strong><em>&ldquo;'. wp_trim_words( strip_tags( $tweet->text ), 10 ) .'&rdquo; </em> imported and created successfully.</strong></p>';
}


add_action( 'current_screen', 'dw_tweet_redirect_on_deleteuser' );
function dw_tweet_redirect_on_deleteuser() {

	if ( isset( $_GET['delete-twitter-user'] ) ) {
		$users = get_option( 'dsgnwrks_tweet_users' );
		foreach ( $users as $key => $user ) {
			if ( $user == $_GET['delete-twitter-user'] ) $delete = $key;
		}
		unset( $users[$delete] );
		update_option( 'dsgnwrks_tweet_users', $users );

		$opts = get_option( 'dsgnwrks_tweet_options' );
		unset( $opts[$_GET['delete-twitter-user']] );
		if ( isset( $opts['username'] ) && $opts['username'] == $_GET['delete-twitter-user'] )
		unset( $opts['username'] );
		update_option( 'dsgnwrks_tweet_options', $opts );

		wp_redirect( remove_query_arg( 'delete-twitter-user' ), 307 );
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
