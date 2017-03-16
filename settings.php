<?php
if ( !current_user_can( 'manage_options' ) )  {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

$opts = wp_parse_args( $this->options(), array(
	'frequency' => 'never',
	'username' => 'replaceme',
) );

$reg = get_option( $this->pre.'registration' );
$users = $this->users();
$schedules = wp_get_schedules();

// echo '<div id="message" class="updated"><p><pre>$opts: '. htmlentities( print_r( $opts, true ) ) .'</pre></p></div>';

$users = ( !empty( $users ) ) ? $users : array();

if ( !empty( $reg ) && $reg['badauth'] == 'good' && !in_array( $reg['user'], $users ) ) {
	$users[] = $reg['user'];

	update_option( $this->pre.'users', $users );
	update_option( $this->optkey, $opts );
}

if ( !empty( $users ) && is_array( $users ) ) {
	foreach ( $users as $key => $user ) {

		if ( isset( $opts[$user]['remove-date-filter'] ) && $opts[$user]['remove-date-filter'] == 'yes' ) {
			$opts[$user]['mm'] = '';
			$opts[$user]['dd'] = '';
			$opts[$user]['yy'] = '';
			$opts[$user]['date-filter'] = 0;
			$opts[$user]['remove-date-filter'] = '';
			update_option( $this->optkey, $opts );
		}

		if ( !empty( $opts[$user]['remove-tag-filter'] ) ) {
			$opts[$user]['tag-filter'] = '';
			$opts[$user]['remove-tag-filter'] = '';
			update_option( $this->optkey, $opts );
		}

		if ( !empty( $opts[$user]['tag-filter'] ) ) {
			$opts[$user]['remove-tag-filter'] = '';
		}

		$complete[$user] = ( !empty( $opts[$user]['mm'] ) && !empty( $opts[$user]['dd'] ) && !empty( $opts[$user]['yy'] ) ) ? true : false;
	}

}

?>

<div class="wrap">
	<div id="icon-tools" class="icon32"><br></div>
	<h2>DsgnWrks Twitter Importer Options</h2>
	<div id="screen-meta" style="display: block; ">
	<?php

	$nogo = $nofeed = false;
	// Setup our notifications
	if ( !empty( $reg['badauth'] ) && $reg['badauth'] == 'error' ) {
		echo '<div id="message" class="error"><p>Couldn\'t find a twitter feed. Please check the username.</p></div>';
		$nofeed = true;
	} elseif ( empty( $reg['user'] ) && empty( $users ) ) {
		$nogo = true;
	} elseif ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
		echo '<div id="message" class="updated"><p>Settings Updated</p></div>';
	}

	?>
	<div class="clear"></div>

		<div id="contextual-help-wrap" class="hidden" style="display: block; ">
			<div id="contextual-help-back"></div>
			<div id="contextual-help-columns">
				<div class="contextual-help-tabs">
					<?php if ( empty( $reg ) && empty( $opts ) ) { ?>
						<h2>Get Started</h2>
					<?php } else { ?>
						<h2>Users</h2>
					<?php } ?>

					<ul>
						<?php
						if ( !empty( $users ) && is_array( $users ) ) {
							foreach ( $users as $key => $user ) {
								$id = str_replace( ' ', '', strtolower( $user ) );
								$class = ( !empty( $class ) || $nofeed == true ) ? '' : ' active';
								if ( isset( $opts['username'] ) ) {
									$class = ( $opts['username'] == $id ) && ! $nofeed ? ' active' : '';
								}
								?>
								<li class="tab-twitter-user<?php echo $class; ?>" id="tab-twitter-user-<?php echo $id; ?>">
									<a href="#twitter-user-<?php echo $id; ?>"><?php echo $user; ?></a>
								</li>
								<?php
							}
						} else {
							$user = 'Create User';
							$class = str_replace( ' ', '', strtolower( $user ) );
							?>
							<li class="tab-twitter-user active" id="tab-twitter-user-<?php echo $class; ?>">
								<a href="#twitter-user-<?php echo $class; ?>"><?php echo $user; ?></a>
							</li>
							<?php
						}

						if ( !$nogo ) { ?>
							<li id="tab-add-another-user" <?php echo ( $nofeed == true ) ? 'class="active"' : ''; ?>>
								<a href="#add-another-user">Add Another User</a>
							</li>
							<li id="tab-universal-options" <?php echo 'replaceme' == $opts['username'] ? 'class="active"' : ''; ?>>
								<a href="#universal-options"><?php _e( 'Plugin Options', 'dsgnwrks' ); ?></a>
							</li>
						<?php } ?>
					</ul>
				</div>

				<div class="contextual-help-tabs-wrap">

				<?php
				if ( !empty( $users ) && is_array( $users ) ) {
					?>
					<form class="twitter-importer" method="post" action="options.php">
					<?php settings_fields( 'dsgnwrks_twitter_importer_settings' );
					wp_nonce_field( 'tweetimport-nonce', 'dw-tweetimporter' );

					foreach ( $users as $key => $user ) {
						$id = str_replace( ' ', '', strtolower( $user ) );
						$active = ( !empty( $active ) || $nofeed == true ) ? '' : ' active';
						if ( isset( $opts['username'] ) ) {
							$active = ( $opts['username'] == $id ) && !$nofeed ? ' active' : '';
						}
						?>
						<div id="twitter-user-<?php echo $id; ?>" class="help-tab-content<?php echo $active; ?>">

							<?php
							if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
								if ( !empty( $opts[$id]['mm'] ) || !empty( $opts[$id]['dd'] ) || !empty( $opts[$id]['yy'] ) ) {
									if ( !$complete[$id] ) echo '<div id="message" class="error"><p>Please select full date.</p></div>';
								}
							}

							?>

							<table class="form-table">

								<tr valign="top" class="info">
								<th colspan="2">
									<p>Ready to import from Twitter &mdash; <span><a id="delete-<?php echo $id; ?>" class="delete-twitter-user" href="<?php echo add_query_arg( 'delete-twitter-user', $id ); ?>">Delete User?</a></span></p>
									<p>Please select the import filter options below. If none of the options are selected, all tweets for <strong><?php echo $id; ?></strong> will be imported. <em>(This could take a long time if you have a lot of tweets)</em></p>
								</th>
								</tr>

								<tr valign="top">
								<th scope="row"><strong>Filter import by hashtag:</strong><br/>Will only import tweets with these hashtags.<br/>Please separate tags (without the <b>#</b> symbol) with commas.</th>
								<?php $tag_filter = isset( $opts[$id]['tag-filter'] ) ? $opts[$id]['tag-filter'] : ''; ?>
								<td><input type="text" placeholder="e.g. keeper, fortheblog" name="dsgnwrks_tweet_options[<?php echo $id; ?>][tag-filter]" value="<?php echo $tag_filter; ?>" />
								<?php
									if ( !empty( $opts[$id]['tag-filter'] ) ) {
										echo '<p><label><input type="checkbox" name="'.$this->optkey.'['.$id.'][remove-tag-filter]" value="yes" /> <em> Remove filter</em></label></p>';
									}
								?>
								</td>
								</tr>

								<tr valign="top">
								<th scope="row"><strong>Import from this date:</strong><br/>Select a date to begin importing your tweets.</th>

								<td class="curtime">


									<?php
									global $wp_locale;

									$date_filter = 0;
									if ( !empty( $opts[$id]['mm'] ) || !empty( $opts[$id]['dd'] ) || !empty( $opts[$id]['yy'] ) ) {
										if ( $complete[$id] ) {
											$date = '<strong>'. $wp_locale->get_month( $opts[$id]['mm'] ) .' '. $opts[$id]['dd'] .', '. $opts[$id]['yy'] .'</strong>';
												$opts[$id]['remove-date-filter'] = 'false';
												$date_filter = strtotime( $opts[$id]['mm'] .'/'. $opts[$id]['dd'] .'/'. $opts[$id]['yy'] );
										} else {
											$date = '<span style="color: #E0522E;">Please select full date</span>';
										}
									}
									else { $date = 'No date selected'; }
									$date = '<p style="padding-bottom: 2px; margin-bottom: 2px;" id="timestamp"> '. $date .'</p>';
									$date .= '<input type="hidden" name="'.$this->optkey.'['.$id.'][date-filter]" value="'. $date_filter .'" />';

									$month = '<select id="twitter-mm" name="'.$this->optkey.'['.$id.'][mm]">\n';
									$month .= '<option value="">Month</option>';
									for ( $i = 1; $i < 13; $i = $i +1 ) {
										$monthnum = zeroise($i, 2);
										$month .= "\t\t\t" . '<option value="' . $monthnum . '"';
										if ( isset( $opts[$id]['mm'] ) && $i == $opts[$id]['mm'] )
											$month .= ' selected="selected"';
										$month .= '>' . $monthnum . '-' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
									}
									$month .= '</select>';

									$day = '<select style="width: 5em;" id="twitter-dd" name="'.$this->optkey.'['.$id.'][dd]">\n';
									$day .= '<option value="">Day</option>';
									for ( $i = 1; $i < 32; $i = $i +1 ) {
										$daynum = zeroise($i, 2);
										$day .= "\t\t\t" . '<option value="' . $daynum . '"';
										if ( isset( $opts[$id]['dd'] ) && $i == $opts[$id]['dd'] )
											$day .= ' selected="selected"';
										$day .= '>' . $daynum;
									}
									$day .= '</select>';

									$year = '<select style="width: 5em;" id="twitter-yy" name="'.$this->optkey.'['.$id.'][yy]">\n';
									$year .= '<option value="">Year</option>';
									for ( $i = date( 'Y' ); $i >= 2010; $i = $i -1 ) {
										$yearnum = zeroise($i, 4);
										$year .= "\t\t\t" . '<option value="' . $yearnum . '"';
										if ( isset( $opts[$id]['yy'] ) && $i == $opts[$id]['yy'] )
											$year .= ' selected="selected"';
										$year .= '>' . $yearnum;
									}
									$year .= '</select>';


									echo '<div class="timestamp-wrap">';
									/* translators: 1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input */
									printf(__('%1$s %2$s %3$s %4$s'), $date, $month, $day, $year );

									if ( $complete[$id] == true ) {
										echo '<p><label><input type="checkbox" name="'.$this->optkey.'['.$id.'][remove-date-filter]" value="yes" /> <em> Remove filter</em></label></p>';
									}
									?>

								</td>
								</tr>

								<?php
								// Our auto-import interval text. "Manual" if not set
								$interval = ! $opts['frequency'] || 'never' == $opts['frequency'] || ! isset( $schedules[ $opts['frequency'] ]['display'] )
									? 'Manual'
									: strtolower( $schedules[ $opts['frequency'] ]['display'] );
								?>
								<tr valign="top"<?php echo $interval == 'Manual' ? ' class="disabled"' : ''; ?>>
								<th scope="row">
									<strong><?php _e( 'Auto-import future photos:', 'dsgnwrks' ); ?></strong><br/>
									<?php if ( $interval == 'Manual' ) { ?>
										<em><?php _e( 'Change import interval from "Manual" in the "Plugin Options" tab for this option to take effect.', 'dsgnwrks' ); ?></em>
									<?php } else {
										printf( __( 'Change import interval (%s) in the "Plugin Options" tab.', 'dsgnwrks' ), $interval );
									} ?>
								</th>
								<td>
									<input type="checkbox" name="<?php echo $this->optkey; ?>[<?php echo $id; ?>][auto_import]" <?php checked( isset( $opts[$id]['auto_import'] ) && 'yes' === $opts[$id]['auto_import'] ); ?> value="yes"/>
								</td>
								</tr>


								<tr valign="top" class="info">
								<th colspan="2">
									<p>Please select the post options for the imported tweets below.</em></p>
								</th>
								</tr>

								<?php
								// echo '<tr valign="top">
								// <th scope="row"><strong>Insert Twitter photo into:</strong></th>
								// <td>
								//     <select id="twitter-image" name="dsgnwrks_tweet_options['.$id.'][image]">';
								//         if ( $opts[$id]['image'] == 'feat-image') $selected1 = 'selected="selected"';
								//         echo '<option value="feat-image" '. $selected1 .'>Featured Image</option>';
								//         if ( $opts[$id]['image'] == 'content') $selected2 = 'selected="selected"';
								//         echo '<option value="content" '. $selected2 .'>Content</option>';
								//         if ( $opts[$id]['image'] == 'both') $selected3 = 'selected="selected"';
								//         echo '<option value="both" '. $selected3 .'>Both</option>';
								//     echo '</select>
								// </td>
								// </tr>';
								?>

								<tr valign="top">
								<th scope="row"><strong>Import to Post-Type:</strong></th>
								<td>
									<select class="twitter-post-type" id="twitter-post-type-<?php echo $id; ?>" name="dsgnwrks_tweet_options[<?php echo $id; ?>][post-type]">
										<?php
										$args = array(
										  'public' => true,
										);
										$post_types = get_post_types( $args );
										$cur_post_type = isset( $opts[$id]['post-type'] ) ? $opts[$id]['post-type'] : '';
										foreach ($post_types  as $post_type ) {
											?>
											<option value="<?php echo $post_type; ?>" <?php selected( $cur_post_type, $post_type ); ?>><?php echo $post_type; ?></option>
											<?php
										}
										?>
									</select>
								</td>
								</tr>


								<tr valign="top">
								<th scope="row"><strong>Imported posts status:</strong></th>
								<td>
									<select id="twitter-draft" name="dsgnwrks_tweet_options[<?php echo $id; ?>][draft]">
										<?php $draft_status = isset( $opts[$id]['draft'] ) ? $opts[$id]['draft'] : ''; ?>
										<option value="draft" <?php selected( $draft_status, 'draft' ); ?>>Draft</option>
										<option value="publish" <?php selected( $draft_status, 'publish' ); ?>>Published</option>
										<option value="pending" <?php selected( $draft_status, 'pending' ); ?>>Pending</option>
										<option value="private" <?php selected( $draft_status, 'private' ); ?>>Private</option>
									</select>

								</td>
								</tr>

								<tr valign="top">
								<th scope="row"><strong>Assign posts to an existing user:</strong></th>
								<td>
									<?php
									$selected_user = isset( $opts[$id]['author'] ) ? $opts[$id]['author'] : '';
									wp_dropdown_users( array( 'name' => $this->optkey.'['.$id.'][author]', 'selected' => $selected_user ) );
									?>

								</td>
								</tr>

								<?php
								if ( current_theme_supports( 'post-formats' ) && post_type_supports( 'post', 'post-formats' ) ) {
									$post_formats = get_theme_support( 'post-formats' );

									if ( is_array( $post_formats[0] ) ) {
										$opts[$id]['post_format'] = !empty( $opts[$id]['post_format'] ) ? esc_attr( $opts[$id]['post_format'] ) : '';

										// Add in the current one if it isn't there yet, in case the current theme doesn't support it
										if ( $opts[$id]['post_format'] && !in_array( $opts[$id]['post_format'], $post_formats[0] ) )
											$post_formats[0][] = $opts[$id]['post_format'];
										?>
										<tr valign="top" class="taxonomies-add">
										<th scope="row"><strong>Select Imported Posts Format:</strong></th>
										<td>

											<select id="dsgnwrks_tweet_options[<?php echo $id; ?>][post_format]" name="dsgnwrks_tweet_options[<?php echo $id; ?>][post_format]">
												<option value="0" <?php selected( $opts[$id]['post_format'], '' ); ?>>Standard</option>
												<?php foreach ( $post_formats[0] as $format ) : ?>
												<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $opts[$id]['post_format'], $format ); ?>><?php echo esc_html( get_post_format_string( $format ) ); ?></option>

												<?php endforeach; ?>

											</select>

										</td>
										</tr>
										<?php
									}
								}

								$taxs = get_taxonomies( array( 'public' => true ), 'objects' );
								$taxes = array();

								?>
								<tr valign="top">
								<th scope="row"><strong><?php _e( 'Save Tweet hashtags as one of your taxonomies (tags, categories, etc):', 'dsgnwrks' ); ?></strong></th>
								<td>
									<?php

									$hash_tax = isset( $opts[$id]['hashtags_as_tax'] ) ? $opts[$id]['hashtags_as_tax'] : '';

									echo '<select id="'.$this->optkey.'-'.$id.'-hashtags_as_tax" name="'.$this->optkey.'['.$id.'][hashtags_as_tax]">';
										echo '<option class="empty" value="" '. selected( $hash_tax, '', false ) .'>'. __( '&mdash; Select &mdash;', 'dsgnwrks' ) .'</option>';
										foreach ( $taxs as $key => $tax ) {

											$pt_taxes = get_object_taxonomies( $cur_post_type );
											$disabled = !in_array( $tax->name, $pt_taxes );
											echo '<option class="taxonomy-'. $tax->name .'" value="'. esc_attr( $tax->name ) .'" ', selected( $hash_tax, $tax->name ), ' ', disabled( $disabled ) ,'>'. esc_html( $tax->label ) .'</option>';

										}
									echo '</select>';
									?>

								</td>
								</tr>
								<?php


								foreach ( $taxs as $key => $tax ) {

									$opts[$id][$tax->name] = !empty( $opts[$id][$tax->name] ) ? esc_attr( $opts[$id][$tax->name] ) : '';

									$placeholder = 'e.g. Twitter, Life, dog, etc';

									if ( $tax->name == 'post_tag' )  $placeholder = 'e.g. beach, sunrise';

									$tax_section_label = '<strong>'.$tax->label.' to apply to imported posts.</strong><br/>Please separate '.strtolower( $tax->label ).' with commas'."\n";
									$tax_section_input = '<input type="text" placeholder="'.$placeholder.'" name="'.$this->optkey.'['.$id.']['.$tax->name.']" value="'.$opts[$id][$tax->name].'" />'."\n";

									?>
									<tr valign="top" class="taxonomies-add taxonomy-<?php echo $tax->name; ?>">
									<th scope="row">
										<?php echo apply_filters( 'dw_twitter_tax_section_label', $tax_section_label, $tax ); ?>
									</th>
									<td>
										<?php echo apply_filters( 'dw_twitter_tax_section_input', $tax_section_input, $tax, $id, $opts ); ?>
									</td>
									</tr>
									<?php

								}

								echo '<input type="hidden" name="'.$this->optkey.'[username]" value="replaceme" />';

								$last = isset( $opts[$id]['lastimport'] ) ? $opts[$id]['lastimport'] : '';
								echo '<input type="hidden" name="'.$this->optkey.'['.$id.'][lastimport]" value="'. esc_attr( $last ) .'" />';

								if ( $last ) { ?>
									<tr valign="top" class="info">
									<th colspan="2">
										<?php echo '<p>Last import: '. date_i18n( 'l F jS, Y @ h:i:s A', $last ) .'</p>'; ?>

									</th>
									</tr>
								<?php } ?>
							</table>

							<p class="submit">
								<?php
								$importlink = $this->import_link( $id );
								?>
								<input type="submit" id="save-<?php echo sanitize_title( $id ); ?>" name="save" class="button-primary" value="<?php _e( 'Save' ) ?>" />
								<input type="submit" id="import-<?php echo sanitize_title( $id ); ?>" name="<?php echo $importlink; ?>" class="button-secondary import-button" value="<?php _e( 'Import' ) ?>" />
								<!-- <a href="<?php echo $importlink; ?>" class="button-secondary import-button" id="import-<?php echo $id; ?>">Import</a> -->
							</p>
						</div>
						<?php
					}
					?>
					<div id="universal-options" class="help-tab-content <?php echo 'replaceme' == $opts['username'] ? ' active' : ''; ?>">
						<table class="form-table">
							<tbody>
								<tr valign="top" class="info">
									<th colspan="2">
										<h3><?php _e( 'Universal Import Options', 'dsgnwrks' ); ?></h3>
										<p><?php _e( 'Please select the general import options below.', 'dsgnwrks' ); ?></p>
									</th>
								</tr>
								<tr valign="top">
									<th scope="row"><strong><?php _e( 'Set Auto-import Frequency:', 'dsgnwrks' ); ?></strong></th>
									<td>

										<select id="<?php echo $this->optkey; ?>-frequency" name="<?php echo $this->optkey; ?>[frequency]">
											<option value="never" <?php echo selected( $opts['frequency'], 'never' ); ?>><?php _e( 'Manual', 'dsgnwrks' ); ?></option>
											<?php
											foreach ( $schedules as $key => $value ) {
												echo '<option value="'. $key .'"'. selected( $opts['frequency'], $key, false ) .'>'. $value['display'] .'</option>';
											}
											?>
										</select>
									</td>
								</tr>
							</tbody>
						</table>
						<p class="submit">
							<input type="submit" name="save" class="button-primary save" value="<?php _e( 'Save', 'dsgnwrks' ) ?>" />
						</p>

					</div>
					</form>

					<?php
				} else {
					// $message = '<p>Welcome to the Twitter Importer! Click to be taken to Twitter\'s site to securely authorize this plugin for use with your account.</p>';
					// $this->user_form( $users, $message );

					$message = 'Welcome to Twitter Importer! Enter a Twitter username, and we\'ll get started.';
					$this->user_form( $reg, $message );
				}

				if ( !$nogo ) { ?>
					<div id="add-another-user" class="help-tab-content <?php echo ( $nofeed == true ) ? ' active' : ''; ?>">
						<?php $this->user_form( $reg ); ?>
					</div>
				<?php } ?>
				</div>

				<div class="contextual-help-sidebar">
					<p class="jtsocial"><a class="jtpaypal" href="http://j.ustin.co/rYL89n" target="_blank">Contribute<span></span></a>
						<a class="jttwitter" href="http://j.ustin.co/wUfBD3" target="_blank">Follow me on Twitter<span></span></a>
						<a class="jtemail" href="http://j.ustin.co/scbo43" target="blank">Contact Me<span></span></a>
					</p>
				</div>

			</div>
		</div>
	</div>
</div>
<?php
delete_option( $this->pre.'registration' );
unset( $reg );
