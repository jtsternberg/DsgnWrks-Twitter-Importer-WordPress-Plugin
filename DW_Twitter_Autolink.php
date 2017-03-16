<?php
/**
 * @author     Mike Cochrane <mikec@mikenz.geek.nz>
 * @author     Nick Pope <nick@nickpope.me.uk>
 * @copyright  Copyright © 2010, Mike Cochrane, Nick Pope
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License v2.0
 * @package    Twitter
 */

require_once( _DWTW_PATH .'TwitterText/lib/Twitter/Autolink.php' );

/**
 * Twitter Autolink Class
 *
 * Parses tweets and generates HTML anchor tags around URLs, usernames,
 * username/list pairs and hashtags.
 *
 * Originally written by {@link http://github.com/mikenz Mike Cochrane}, this
 * is based on code by {@link http://github.com/mzsanford Matt Sanford} and
 * heavily modified by {@link http://github.com/ngnpope Nick Pope}.
 *
 * @author     Mike Cochrane <mikec@mikenz.geek.nz>
 * @author     Nick Pope <nick@nickpope.me.uk>
 * @copyright  Copyright © 2010, Mike Cochrane, Nick Pope
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License v2.0
 * @package    Twitter
 */
class DW_Twitter_Autolink extends Twitter_Autolink {
	protected $urls = array();

	public static function create($tweet, $full_encode = false) {
		return new self($tweet, $full_encode);
	}

	public function setTweet( $tweet ) {
		if ( ! empty( $tweet->entities->urls ) && is_array( $tweet->entities->urls ) ) {
			$this->urls = $tweet->entities->urls;
		}

		return $this;
	}

	/**
	 * Callback used by the method that adds links to URLs.
	 * Override and replace URLs with the URL entity provided by the Twitter API.
	 *
	 * @see  addLinksToURLs()
	 *
	 * @param  array  $matches  The regular expression matches.
	 *
	 * @return  string  The link-wrapped URL.
	 */
	protected function _addLinksToURLs( $matches ) {

		list( $all, $before, $url, $protocol, $domain, $path, $query ) = array_pad( $matches, 7, '' );
		$entity = array_shift( $this->urls );

		if (
			! empty( $entity->url )
			&& ! empty( $entity->expanded_url )
			&& false !== strpos( $all, $entity->url )
		) {

			$all = str_replace( $entity->url, $entity->expanded_url, $all );
			preg_match_all( self::$REGEX_VALID_URL, $all, $matches );
			$matches = array_pad( $matches, 7, '' );
			foreach ( $matches as &$match ) {
				$match = $match[0];
			}

			list( $new_all, $before, $url, $protocol, $domain, $path, $query ) = $matches;

			$url = ! empty( $entity->display_url ) ? $entity->display_url : $url;
			$url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8', false );
			$href = htmlspecialchars( $entity->expanded_url, ENT_QUOTES, 'UTF-8', false );

		} else {
			$url = $href = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8', false );
		}

		if ( ! $protocol && ! preg_match( self::REGEX_PROBABLE_TLD, $domain ) ) {
			return $all;
		}

		$href = ( ( ! $protocol || strtolower( $protocol ) === 'www.' ) ? 'http://' . $href : $href );

		return $before . $this->wrap( $href, $this->class_url, $url );
	}

	/**
	 * Callback used by the method that adds links to username/list pairs.
	 * Modified to put the "@" symbol _inside_ the link tag.
	 *
	 * @see  addLinksToUsernamesAndLists()
	 *
	 * @param  array  $matches  The regular expression matches.
	 *
	 * @return  string  The link-wrapped username/list pair.
	 */
	protected function _addLinksToUsernamesAndLists($matches) {
		list($all, $before, $at, $username, $slash_listname, $after) = array_pad($matches, 6, '');
		# If $after is not empty, there is an invalid character.
		if (!empty($after)) return $all;
		if (!empty($slash_listname)) {
			# Replace the list and username
			$element = $username . substr($slash_listname, 0, 26);
			$class = $this->class_list;
			$url = $this->url_base_list . $element;
			$postfix = substr($slash_listname, 26);
		} else {
			# Replace the username
			$element = $username;
			$class = $this->class_user;
			$url = $this->url_base_user . $element;
			$postfix = '';
		}
		return $before . $this->wrap($url, $class, $at . $element) . $postfix . $after;
	}

}
