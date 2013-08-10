<?php
/*
Plugin Name: DsgnWrks Twitter Importer
Plugin URI: http://dsgnwrks.pro/twitter-importer/
Description: Helps you to backup your tweets while allowing you to have a site to display your twitter archive. Built-in support for importing to custom post types and attaching custom taxonomies.
Author URI: http://dsgnwrks.pro
Author: DsgnWrks
Donate link: http://dsgnwrks.pro/give/
Version: 1.1.0
*/

class DsgnWrksTwitter {

	protected $plugin_name = 'DsgnWrks Twitter Importer';
	protected $plugin_id = 'dw-twitter-importer-settings';
	protected $pre = 'dsgnwrks_tweet_';
	protected $optkey = 'dsgnwrks_tweet_options';
	protected $plugin_page;

	function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_setup' ) );
		add_action( 'current_screen', array( $this, 'redirect' ) );
	}

	public function init() {

		$this->plugin_page = add_query_arg( 'page', $this->plugin_id, admin_url( '/tools.php' ) );

		if ( isset( $_GET['tweetimport'] ) ) {
			set_transient( sanitize_title( urldecode( $_GET['tweetimport'] ) ) .'-tweetimportdone', date_i18n( 'l F jS, Y @ h:i:s A', strtotime( current_time('mysql') ) ), 14400 );
		}

		// delete_option( $this->optkey );
		register_setting(
			'dsgnwrks_twitter_importer_users',
			$this->pre.'registration',
			array( $this, 'users_validate' )
		);
		register_setting(
			'dsgnwrks_twitter_importer_settings',
			$this->optkey,
			array( $this, 'settings_validate' )
		);
	}

	public function users_validate( $opts ) {

		if ( !empty( $opts['user'] ) ) {
			$validated = $this->validate_user( $opts['user'] );

			if ( $validated ) {
				$response = $this->authenticate_user( $opts['user'] );

				if ( is_wp_error( $response ) ) {
					$opts['badauth'] = 'error';
					$opts['noauth'] = true;
				} else {
					$opts['badauth'] = 'good';
					$opts['noauth'] = '';

					$settings = get_option( $this->optkey );
					$settings['username'] = $opts['user'];
					update_option( $this->optkey, $settings );
				}

			} else {
				// unset( $opts['user'] );
				$opts['badauth'] = 'error';
				$opts['noauth'] = true;
			}
		}
		return $opts;
	}

	protected function validate_user( $username ) {
		return preg_match( '/^[A-Za-z0-9_]+$/', $username );
	}

	public function settings_validate( $opts ) {

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
					$opts[$user][$key] = $this->filter( $opt, '', 'post' );
				} elseif ( $key === 'draft' ) {
					$opts[$user][$key] = $this->filter( $opt, '', 'draft' );
				} elseif ( $key === 'yy' || $key === 'mm' || $key === 'dd' ) {
					if ( empty( $opts[$user]['mm'] ) && empty( $opts[$user]['dd'] ) && empty( $opts[$user]['yy'] ) || !empty( $opts[$user]['remove-date-filter'] ) ) {
						$opts[$user][$key] = '';
					}
					else {
						$opts[$user][$key] = $this->filter( $opt, 'absint', '' );
					}
				} else {
					$opts[$user][$key] = $this->filter( $opt );
				}

			}
		}

		// wp_die( '<pre>'. htmlentities( print_r( $opts, true ) ) .'</pre>' );

		return $opts;
	}

	public function admin_setup() {
		$page = add_submenu_page( 'tools.php', 'DsgnWrks Twitter Import Settings', 'Twitter Importer', 'manage_options', $this->plugin_id, array( $this, 'settings' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( $this, 'scripts' ) );
		add_action( 'admin_head-'. $page, array( $this, 'fire_importer' ) );
	}

	public function settings() { require_once( 'settings.php' ); }

	public function styles() {
		wp_enqueue_style( 'dw-twitter-admin', plugins_url( 'css/admin.css', __FILE__ ) );
	}

	public function scripts() {
		wp_enqueue_script( 'dw-twitter-admin', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), '1.1' );
		$cpts = get_post_types( array( 'public' => true ) );
		foreach ( $cpts as $cpt ) {
			$taxes = get_object_taxonomies( $cpt );
			if ( !empty( $taxes ) ) $data['cpts'][$cpt][] = $taxes;
		}
		if ( !empty( $data ) ) wp_localize_script( 'dw-twitter-admin', 'dwtwitter', $data );
	}

	public function fire_importer() {
		if ( isset( $_GET['tweetimport'] ) && isset( $_POST[$this->optkey]['username'] ) ) {
			add_action( 'all_admin_notices', array( $this, 'import' ) );
		}
	}

	public function import() {

		$opts = get_option( $this->optkey );
		$id = $_POST[$this->optkey]['username'];
		if ( !isset( $_GET['tweetimport'] ) || empty( $id ) ) return;

		// @TODO https://dev.twitter.com/docs/working-with-timelines
		$tweets = $this->get_tweets( $id, 200 );

		if ( is_wp_error( $tweets ) ) {
			echo '<div id="message" class="error"><p>'. implode( '<br/>', $tweets->get_error_messages( 'twitterwp_error' ) ) . '</p></div>';

			$opts[$id]['noauth'] = true;
			update_option( $this->optkey, $opts );
			return;
		}

		// pre-import filter
		$tweets = apply_filters( 'dw_twitter_api', $tweets );

		echo '<div id="message" class="updated">';

		$pre = date('e');
		date_default_timezone_set( get_option( 'timezone_string' ) );

		$messages = $this->messages( $tweets, $opts[$id] );

		while ( !empty( $messages['next_url'] ) ) {
			$messages = $this->messages( $messages['next_url'], $opts[$id], $messages['message'] );
		}

		foreach ( $messages['message'] as $key => $message ) {
			echo $message;
		}

		date_default_timezone_set( $pre );

		echo '</div>';
	}

	protected function messages( $tweets, $opts, $prevmessages = array() ) {

		$messages = $this->loop( $tweets, $opts );

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

	protected function loop( $tweets = array(), $opts = array() ) {

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

			$messages['messages'][] = $this->save_tweet( $tweet, $opts );
		}
		return !empty( $messages ) ? $messages : array();
	}

	protected function save_tweet( $tweet, $opts = array() ) {

		global $user_ID;

		$opts = ( empty( $opts ) ) ? get_option( $this->optkey ) : $opts;

		if ( !isset( $opts['draft'] ) ) $opts['draft'] = 'draft';
		if ( !isset( $opts['author'] ) ) $opts['author'] = $user_ID;

		$post_date = date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) );
		$post = array(
		  'post_author' => $opts['author'],
		  'post_content' => iconv( 'UTF-8', 'ISO-8859-1//IGNORE', $tweet->text ),
		  'post_date' => $post_date,
		  'post_date_gmt' => $post_date,
		  'post_status' => $opts['draft'],
		  'post_title' => $post_date,
		  'post_type' => $opts['post-type'],
		);
		$new_post_id = wp_insert_post( $post, true );

		apply_filters( 'dw_twitter_post_save', $new_post_id, $tweet );

		// Set taxonomy terms from options
		$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxes as $key => $tax ) {

			if ( $tax->label == 'Format' && !current_theme_supports( 'post-formats' ) ) continue;

			$opts[$tax->name] = !empty( $opts[$tax->name] ) ? esc_attr( $opts[$tax->name] ) : '';

			$terms = explode( ', ', $opts[$tax->name] );

			if ( !empty( $terms ) )
				wp_set_object_terms( $new_post_id, $terms, $tax->name );
		}

		// If requested, set tweet hashtags as taxonomy terms
		if ( isset( $opts['hashtags_as_tax'] ) && $opts['hashtags_as_tax'] ) {
			$terms = array();
			foreach ( $tweet->entities->hashtags as $tag ) {
				$terms[] = $tag->text;
			}
			wp_set_object_terms( $new_post_id, $terms, $opts['hashtags_as_tax'] );
		} else {

			// otherwise, we'll save it as postmeta
			update_post_meta( $new_post_id, 'tweet_hashtags', $tweet->entities->hashtags );

		}

		// tweet urls
		update_post_meta( $new_post_id, 'tweet_urls', $tweet->entities->urls );

		// user mentions
		update_post_meta( $new_post_id, 'tweet_user_mentions', $tweet->entities->user_mentions );

		// media entities @TODO option to sideload media to WP
		update_post_meta( $new_post_id, 'tweet_media', $tweet->entities->media );

		// app/site used for tweeting
		update_post_meta( $new_post_id, 'tweet_source', $tweet->source );
		// tweet id
		update_post_meta( $new_post_id, 'tweet_id', $tweet->id_str );
		// tweet @replys
		if ( !empty( $tweet->in_reply_to_status_id_str ) )
			update_post_meta( $new_post_id, 'in_reply_to_status_id_str', $tweet->in_reply_to_status_id_str );
		if ( !empty( $tweet->in_reply_to_user_id ) )
			update_post_meta( $new_post_id, 'in_reply_to_user_id', $tweet->in_reply_to_user_id );
		if ( !empty( $tweet->in_reply_to_screen_name ) )
			update_post_meta( $new_post_id, 'in_reply_to_screen_name', $tweet->in_reply_to_screen_name );

		return '<p><strong><em>&ldquo;'. wp_trim_words( strip_tags( $tweet->text ), 10 ) .'&rdquo; </em> imported and created successfully.</strong></p>';
	}

	public function redirect() {

		if ( isset( $_GET['delete-twitter-user'] ) ) {
			$users = get_option( $this->pre.'users' );
			foreach ( $users as $key => $user ) {
				if ( $user == $_GET['delete-twitter-user'] ) $delete = $key;
			}
			unset( $users[$delete] );
			update_option( $this->pre.'users', $users );

			$opts = get_option( $this->optkey );
			unset( $opts[$_GET['delete-twitter-user']] );
			if ( isset( $opts['username'] ) && $opts['username'] == $_GET['delete-twitter-user'] )
				unset( $opts['username'] );
			update_option( $this->optkey, $opts );

			wp_redirect( remove_query_arg( 'delete-twitter-user' ), 307 );
			exit;
		}
	}

	protected function filter( $opt = '', $filter = '', $else = '' ) {
		if ( empty( $opt ) ) return $else;
		if ( $filter == 'absint' ) return absint( $opt );
		else return esc_attr( $opt );
	}

	protected function user_form( $reg, $message = 'Enter another Twitter username to import their tweets.' ) {

		$id = $this->pre.'registration[user]';
		$message = $message ? $message : '<p>Click to be taken to Twitter\'s site to securely authorize this plugin for use with your account.</p><p><em>(If you have already authorized an account, You will first be logged out of Twitter.)</em></p>';
		?>
		<form class="twitter-importer" method="post" action="options.php">
			<?php
			settings_fields( 'dsgnwrks_twitter_importer_users' );
			// echo $message;
			// $class = !empty( $users ) ? 'logout' : '';
			?>
			<!-- <p class="submit">
				<input type="submit" name="save" class="button-primary authenticate <?php echo $class; ?>" value="<?php _e( 'Secure Authentication with Instagram' ) ?>" />
			</p> -->

			<table class="form-table">
				<p><?php echo $message; ?></p>
				<tr valign="top">
				<th scope="row"><label for="<?php echo $id; ?>"><strong>Twitter Username:</strong></label></th>
				<td><strong class="atsymbol">@</strong><input type="text" id="<?php echo $id; ?>" name="<?php echo $id; ?>" value="" /></td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="save" class="button-primary" value="<?php echo _e( 'Save' ) ?>" />
			</p>
		</form>

		<?php
	}

	protected function import_link( $id ) {
		// return add_query_arg( 'tweetimport', $id, add_query_arg( 'page', $this->plugin_id, admin_url( $GLOBALS['pagenow'] ) ) );
		return add_query_arg( array( 'page' => $this->plugin_id, 'tweetimport' => 'true' ), admin_url( $GLOBALS['pagenow'] ) );
	}

}

new DsgnWrksTwitter;

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



class DsgnWrksTwitterAuth {

	protected $url = 'https://api.twitter.com/1.1/';

	protected function authenticate( $user, $return = true ) {

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

	function get_tweets( $user = '', $count = 1 ) {

		$this->user = $user;


		$url = $this->twAPIurl( array( 'screen_name' => $user, 'count'=> $count ) );
		$args = $this->header_args( array( 'count' => $count ) );
		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) )
		   return '<strong>ERROR:</strong> '. $response->get_error_message();

		$error = 'Could not access Twitter feed.';
		return $this->returnData( $response, $error );

	}

	function authenticate_user( $user = '' ) {

		$this->user = $user;

		$url = $this->twAPIurl( array( 'screen_name' => $user ), 'users/lookup.json' );
		$args = $this->header_args();
		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) )
		   return false;

		$error = 'Could not access Twitter user.';
		return $this->returnData( $response, $error );

	}

	protected function returnData( $response, $error_message = '' ) {

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body );

		if ( isset( $json->errors ) ) {
			$errors = new WP_Error( 'twitterwp_error', $error_message );

			foreach ( $json->errors as $key => $error ) {

				$errors->add( 'twitterwp_error', '<strong>ERROR '. $error->code .':</strong> '. $error->message );
			}
			return $errors;
		}

		return $json;
	}

	protected function header_args( $args = array() ) {

		if ( !isset( $this->user ) || ! $this->user )
			return null;

		// Set our oauth data
		$defaults = array(
			'screen_name' => $this->user,
			'oauth_consumer_key' => 'YrPAw3bqVq6P99TPx1VHug',
			'oauth_nonce' => time(),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_token' => '24203273-MqOWFPQZZLGf4RaZSEVLOxalZAa9rCg1NCMEoCYMw',
			'oauth_timestamp' => time(),
			'oauth_version' => '1.0'
		);

		$oauth = wp_parse_args( $args, $defaults );

		$base_info = $this->build_base( $this->base_url(), $oauth );
		$composite_key = 'EHMuLykgzg5xam8eG1mnkBIvHsHcwNoYh9QhjbA&12Ya5GLGgiHFV3YK6GnixUx50dvEEf2vMita2kOoFQ';

		// create our oauth signature
		$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_info, $composite_key, true ) );

		$auth_args = array(
			'sslverify' => false,
			'headers' => array(
				'Authorization' => 'OAuth '. $this->authorize_header( $oauth ),
				'Expect' => false,
				'Accept-Encoding' => false
			),
		);

		return $auth_args;
	}

	protected function build_base( $baseURI, $params ) {
		$base = array();
		ksort( $params );
		foreach( $params as $key => $value ){
			$base[] = $key .'='. rawurlencode( $value );
		}

		return 'GET&'. rawurlencode( $baseURI ) .'&'. rawurlencode( implode( '&', $base ) );

	}

	protected function authorize_header( $oauth ) {
		$header = '';
		$values = array();
		foreach( $oauth as $key => $value ) {
			if ( $key == 'screen_name' || $key == 'count' )
				continue;
			$values[] = $key .'="'. rawurlencode( $value ) .'"';
		}

		$header .= implode( ', ', $values );

		return $header;
	}

	protected function twAPIurl( $params = false, $trail = 'statuses/user_timeline.json' ) {

		// append trailing path
		$this->base_url = $this->url . $trail;
		// append query args
		return $params ? add_query_arg( $params, $this->base_url ) : $this->base_url;
	}

	protected function base_url() {

		// set it up
		if ( !isset( $this->base_url ) )
			$this->twAPIurl();

		return $this->base_url;
	}
}
