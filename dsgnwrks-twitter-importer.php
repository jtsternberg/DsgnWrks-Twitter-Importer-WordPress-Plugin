<?php
/*
Plugin Name: DsgnWrks Twitter Importer
Plugin URI: http://dsgnwrks.pro/twitter-importer/
Description: Helps you to backup your tweets while allowing you to have a site to display your twitter archive. Built-in support for importing to custom post types and attaching custom taxonomies.
Author URI: http://dsgnwrks.pro
Author: DsgnWrks
Donate link: http://dsgnwrks.pro/give/
Version: 1.1.1
*/

define( '_DWTW_PATH', plugin_dir_path( __FILE__ ) );
define( '_DWTW_URL', plugins_url('/', __FILE__ ) );

class DsgnWrksTwitter {

	protected $plugin_name = 'DsgnWrks Twitter Importer';
	protected $plugin_id   = 'dw-twitter-importer-settings';
	protected $pre         = 'dsgnwrks_tweet_';
	protected $optkey      = 'dsgnwrks_tweet_options';
	protected $opts        = false;
	protected $users       = false;
	protected $tw          = false;
	protected $plugin_page;

	function __construct() {

		// i18n
		load_plugin_textdomain( 'dsgnwrks', false, dirname( plugin_basename( __FILE__ ) ) );

		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_setup' ) );
		add_action( 'current_screen', array( $this, 'redirect' ) );
		// Load the plugin settings link shortcut.
		add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'dsgnwrks-twitter-importer.php' ), array( $this, 'settings_link' ) );

		// @DEV adds a minutely schedule for testing cron
		add_filter( 'cron_schedules', array( $this, 'minutely' ) );
		add_action( 'all_admin_notices', array( $this, 'show_cron_notice' ) );

		// Make sure we have our Twitter class
		if ( ! class_exists( 'TwitterWP' ) )
			require_once( _DWTW_PATH .'TwitterWP/lib/TwitterWP.php' );
	}

	/**
	 * Hooks to 'all_admin_notices' and displays auto-imported photo messages
	 * @since  1.1.1
	 */
	function show_cron_notice() {

		// check if we have any saved notices from our cron auto-import
		$notices = get_option( 'dsgnwrks_imported_tweets_notice' );

		// $notices = array(
		// 	'jtsternberg' => array(
		// 		array(
		// 			'time' => 'December 2nd',
		// 			'notice' => '@Ceagon Ha, i have a very similar snippet:',
		// 		),
		// 		array(
		// 			'time' => 'July 30 2013',
		// 			'notice' => 'Hey looky.. I wrote another blog post. This one comes with a freebie: Sublime Text 2 debugging snippets: http://t.co/NJH7KPq1b6',
		// 		),
		// 	),
		// );
		if ( !$notices )
			return;

		// if so, loop through and display them
		echo '<div id="message" class="updated instagram-import-message"><h2>'. __( 'Tweets Imported', 'dsgnwrks' ) .'</h2><ul>';
		foreach ( $notices as $userid => $tweets ) {
			foreach ( $tweets as $key => $tweet ) {
				echo '<li><strong>@'. $userid .' &mdash; '. $tweet['time'] .'</strong>';
				echo $tweet['notice'] .'</li>';

			}
		}
		echo '</ul><br><a href="'. add_query_arg( array() ) .'">'. __( 'Hide', 'dsgnwrks' ) .'</a></div>';
		?>
		<style type="text/css">
		.updated.instagram-import-message {
			overflow: hidden;
			background: #F1F1F1;
			border-color: #ccc;
			padding: 0 0 10px 10px;
			margin: 0;
		}
		.updated.instagram-import-message ol {
			padding: 0;
			margin: 0;
			list-style-position: inside;
		}
		.updated.instagram-import-message li {
			border-bottom: 1px solid #aaa;
			margin-bottom: 10px;
		}
		.updated.instagram-import-message li, .updated.instagram-import-message p {
			margin: 0 10px 0 0;
			padding: 8px;
		}
		.updated.instagram-import-message strong {
			display: block;
		}
		.updated.instagram-import-message hr {
			display: block;
			width: 100%;
			clear: both;
		}
		</style>
		<?php
		// reset notices
		update_option( 'dsgnwrks_imported_tweets_notice', '' );
	}

	/**
	 * Add Settings page to plugin action links in the Plugins table.
	 *
	 * @since 1.1.0
	 * @param  array $links Default plugin action links.
	 * @return array $links Amended plugin action links.
	 */
	public function settings_link( $links ) {

		$setting_link = sprintf( '<a href="%s">%s</a>', $this->plugin_page(), __( 'Settings', 'dsgnwrks' ) );
		array_unshift( $links, $setting_link );

		return $links;

	}


	public function init() {

		// add_action( 'all_admin_notices', array( $this, 'testing' )  );

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
				$exists = $this->twitterwp()->user_exists( $opts['user'] );

				if ( ! $exists ) {
					$opts['badauth'] = 'error';
					$opts['noauth'] = true;
				} else {
					$opts['badauth'] = 'good';
					$opts['noauth'] = '';

					$this->update_options( 'username', $opts['user'] );
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

		$opts = $this->options();
		$id = $_POST[$this->optkey]['username'];
		if ( !isset( $_GET['tweetimport'] ) || empty( $id ) ) return;

		// @TODO https://dev.twitter.com/docs/working-with-timelines
		$tweets = $this->twitterwp()->get_tweets( $id, 200 );

		if ( is_wp_error( $tweets ) ) {
			echo '<div id="message" class="error"><p>'. implode( '<br/>', $tweets->get_error_messages( 'twitterwp_error' ) ) . '</p></div>';

			$opts[$id]['noauth'] = true;
			$this->update_options( $opts );
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

		$tweet_text = apply_filters( 'dw_twitter_clean_tweets', false ) ? iconv( 'UTF-8', 'ISO-8859-1//IGNORE', $tweet->text ) : $tweet->text;

		$post = array(
		  'post_author' => $opts['author'],
		  'post_content' => $tweet_text,
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
		if ( isset( $tweet->entities->media ) )
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
			$users = $this->users();
			foreach ( $users as $key => $user ) {
				if ( $user == $_GET['delete-twitter-user'] ) $delete = $key;
			}
			unset( $users[$delete] );
			update_option( $this->pre.'users', $users );

			$opts = $this->options();
			unset( $opts[$_GET['delete-twitter-user']] );
			if ( isset( $opts['username'] ) && $opts['username'] == $_GET['delete-twitter-user'] )
				unset( $opts['username'] );
			$this->update_options( $opts );

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

	public function twitterwp() {
		$this->tw = $this->tw ? $this->tw : TwitterWP::start( '0=m39J9KuiCEajGFwRA3VzxQ&1=jazlUeGiKPkQVzPHZMDqlEKM9pqv84l93zyhTR6pIng&2=24203273-MqOWFPQZZLGf4RaZSEVLOxalZAa9rCg1NCMEoCYMw&3=12Ya5GLGgiHFV3YK6GnixUx50dvEEf2vMita2kOoFQ' /*12Ya5GLGgiHFV3YK6GnixUx50dvEEf2vMita2kOoFQ */ );
		return $this->tw;
	}


	/**
	 * Retrieve plugin's options (or optionally a specific value by key)
	 * @param  string       $key key who's related value is desired
	 * @return array|string      whole option array or specific value by key
	 */
	protected function options( $key = '' ) {
		$this->opts = $this->opts ? $this->opts : get_option( $this->optkey );

		if ( $key )
			return isset( $this->opts[$key] ) ? $this->opts[$key] : false;

		return $this->opts;
	}

	/**
	 * Retrieve plugin's user option (or optionally a specific value by key)
	 * @param  string       $key key who's related value is desired
	 * @return array|string      whole option array or specific value by key
	 */
	protected function users( $key = '' ) {
		$this->users = $this->users ? $this->users : get_option( $this->pre.'users' );

		if ( $key )
			return isset( $this->users[$key] ) ? $this->users[$key] : false;

		return $this->users;
	}

	/**
	 * Retrieve plugin's options (or optionally a specific value by key)
	 * @param  string       $key key who's related value is desired
	 * @return array|string      whole option array or specific value by key
	 */
	protected function update_options( $keyoropts, $value = '' ) {
		$this->opts = $this->options();

		if ( $value )
			$this->opts[$keyoropts] = $value;
		elseif ( is_array( $keyoropts ) )
			$this->opts = $keyoropts;
		else
			return false;

		update_option( $this->optkey, $this->opts );
		return $this->opts;
	}

	/**
	 * Get's the url for the plugin admin page
	 * @since  1.1.0
	 * @return string plugin admin page url
	 */
	public function plugin_page() {
		// Set our plugin page parameter
		$this->plugin_page = $this->plugin_page ? $this->plugin_page : add_query_arg( 'page', $this->plugin_id, admin_url( '/tools.php' ) );
		return $this->plugin_page;
	}

	/**
	 * @DEV Adds once minutely to the existing schedules for easier cron testing.
	 * @since  1.1.1
	 */
	function minutely( $schedules ) {
		$schedules['minutely'] = array(
			'interval' => 60,
			'display'  => 'Once Every Minute'
		);
		return $schedules;
	}

	public function testing() {
		echo '<div id="message" class="updated"><p>';

		// $request = $this->twitterwp()->get_token();

		// if ( is_wp_error( $request ) )
		// 	$this->twitterwp()->show_wp_error( $request );


		// $status = $this->twitterwp()->rate_limit_status();
		// if ( is_wp_error( $status ) )
		// 	$this->twitterwp()->show_wp_error( $status );
		// print( '<pre>'. htmlentities( print_r( $status, true ) ) .'</pre>' );


		// require_once( 'sampledata.php' );
		// foreach ( $this->sample_tweets as $key => $tweet ) {
		// 	echo htmlentities( iconv( 'UTF-8', 'ISO-8859-1//IGNORE', $tweet->text ) ).'<br>';
		// }
		// wp_die( '<xmp>$this->sample_tweets: '. print_r( wp_list_pluck( $this->sample_tweets, 'text' ), true ) .'</xmp>' );

			$tw = $this->twitterwp();

			if ( is_wp_error( $tw ) ) {
				echo '<p>error: '. $tw->get_error_messages() .'</p>';
			} else {

				echo '<pre>'. htmlentities( print_r( $tw, true ) ) .'</pre><pre>';
				// $me = $tw->user_exists( 'jtsternberg' ) ? $tw->get_tweets( 'jtsternberg', 2 ) : false;
				$me = $tw->user_exists( 'jtsternberg' ) ? $tw->get_user() : false;

				// $search = $tw->get_search_results( '#wdschat', 10 );
				// wp_die( '<xmp>'. print_r( $search, true ) .'</xmp>' );

				if ( !$me || is_wp_error( $me ) ) {
					echo '<hr/>';
					echo '<h2>wp_error</h2>';
					echo implode( '<br/>', $me->get_error_messages( 'twitterwp_error' ) );
					echo '<pre>$me_error = '. var_export( $me->get_error_messages(), true ) .'</pre>';
					echo '<hr/>';
				}
				else {
					wp_die( '<xmp>'. print_r( $tw->get_list_tweets( 'jtsternberg', 'WDS', 3 ), true ) .'</xmp>' );
					// wp_die( '<pre>'. print_r( wp_list_pluck( $me, 'text' ), true ) .'</pre>' );
					wp_die( '<xmp> = '. str_replace( 'stdClass::__set_state', '(object) ', var_export( $me, true ) ) .'</xmp><xmp>$tweets: '. print_r( $tw->get_tweets( 'jtsternberg', 3), true ) .'</xmp>' );
					wp_die( '<pre>$this->sample_tweets = '. str_replace( 'stdClass::__set_state', '(object) ', var_export( $me, true ) ) .';</pre>' );
					echo '<hr/>';
					echo '<pre>$me: '. print_r( $me, true ) .'</pre>';
				}
			}


		echo '</p></div>';

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
