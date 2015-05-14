<?php
// load up some defaults here if the post_Id is used

$defaults = array();
if(isset($_GET['post_id']) && (int)$_GET['post_id']>0){
	$post = get_post($_GET['post_id']);
	if($post && $post->ID == $_GET['post_id']){
		// woo!
		$defaults['post_id'] = $post->ID;
		$defaults['facebook_type'] = 'link';
		$defaults['facebook_link'] = get_permalink($post->ID);
		if ( has_post_thumbnail($post->ID)) {
			$large_image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
			if($large_image_url[0]){
				$defaults['facebook_link_picture'] = $large_image_url[0];
			}
		}
		$defaults['facebook_title'] = get_the_title($post->ID);
		$defaults['facebook_description'] = substr( strip_tags(strip_shortcodes($post->post_excerpt ? $post->post_excerpt : $post->post_content)) , 0 , 50 );
		$defaults['facebook_message'] = trim(strip_tags(strip_shortcodes($post->post_excerpt ? $post->post_excerpt : $post->post_content)));
		$defaults['google_message'] = trim(strip_tags(strip_shortcodes($post->post_excerpt ? $post->post_excerpt : $post->post_content)))  . ' ' . get_permalink($post->ID);
		$defaults['twitter_message'] = trim(substr( strip_tags(strip_shortcodes($post->post_excerpt ? $post->post_excerpt : $post->post_content)) , 0 , 118 )) . ' ' . get_permalink($post->ID);
		$defaults['facebook_caption'] = get_bloginfo('description');
	}
}
?><div class="wrap">
	<h2>
		<?php _e('Support Hub Compose','support_hub');?>
	</h2>

<div class="metabox-holder">
<div id="support_hub_compose" class="postbox " >
	<div class="inside">

	<?php
	$SupportHub = SupportHub::getInstance();
	?>


	<form action="" method="post" enctype="multipart/form-data">
	    <input type="hidden" name="_process" value="send_shub_message">
	    <input type="hidden" name="post_id" value="<?php echo isset($defaults['post_id']) ? (int)$defaults['post_id'] : 0;?>">
		<?php wp_nonce_field( 'shub_send-message' ); ?>
		<table class="" id="support_hub_compose_table">
			<thead>
				<tr>
					<th class="shub_column">Network</th>
					<th class="shub_column">Message</th>
					<th class="shub_column last">Options</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach($SupportHub->message_managers as $message_manager){
				if(!$message_manager->get_accounts())continue;
				?>
				<tr>
					<td class="shub_space" colspan="3"></td>
				</tr>
				<tr>
				    <th class="shub_compose_label">
					    <span><?php echo $message_manager->friendly_name;?></span>
					    <?php $message_manager->compose_to();?>
				    </th>
					<td class="shub_column">
					    <?php $message_manager->compose_message($defaults);?>
				    </td>
					<td class="shub_column last">
					    <?php $message_manager->compose_type($defaults);?>
				    </td>
				</tr>
			<?php } ?>
			<tr>
				<td class="shub_space" colspan="3"></td>
			</tr>
			</tbody>
			<thead>
				<tr>
					<th class="shub_column last" colspan="3">Settings</th>
				</tr>
			</thead>
			<tbody>
			<tr>
				<td class="shub_space" colspan="3"></td>
			</tr>
		    <tr>
			    <th class="shub_compose_label">
				    Schedule
			    </th>
			    <td colspan="2" class="shub_column last">
				    <input type="radio" name="schedule_send" id="schedule_send_now" value="now" checked>
					<label for="schedule_send_now">Send Now</label>
				    <input type="radio" name="schedule_send" id="schedule_send_later" value="later">
					<label for="schedule_send_later">Send Later</label>

				    <div id="schedule_send_later_box" style="display:none;">
					    <input type="text" name="schedule_date" value="" class="support_hub_date_field">
					    <input type="text" name="schedule_time" value="" class="support_hub_time_field">
					    <br/><strong>Please note:</strong> you cannot schedule Picture posts/tweets.
					    <br/><small> Currently: <?php echo shub_print_date(current_time('timestamp'),true);?> (Leave blank to send now, or pick a date in the future.)</small>
				    </div>
			    </td>
		    </tr>
		    <tr>
			    <th class="shub_compose_label">
				    Track Clicks
			    </th>
			    <td colspan="2" class="shub_column last">
				    <input type="checkbox" name="track_links" value="1" checked> Yes, track link clicks.
				    <br/><small>If enabled, all links in above messages will be automatically changed (eg: <?php
					    $new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_TWITTER_LINK_REWRITE_PREFIX . '=123ABC';
					    echo $new_link;?>) for tracking.</small>
			    </td>
		    </tr>
		    <tr>
			    <th class="shub_compose_label last">
				    Debug
			    </th>
			    <td class="shub_column last" colspan="2">
				    <input type="checkbox" name="debug" value="1">
			    </td>
		    </tr>
		    </tbody>
		</table>

	    <p class="submit">
				<input name="butt_send" type="submit" class="button-primary"
				       value="<?php echo esc_attr( __( 'Send Message', 'support_hub' ) ); ?>"/>
		</p>


	    <?php



	    ?>
	</form>

	<script type="text/javascript">
	    function facebook_change_post_type(){
		    var currenttype = jQuery('[name=facebook_post_type]:checked').val();
		    jQuery('.facebook-type-option').each(function(){
			    jQuery(this).parents('tr').first().hide();
		    });
		    jQuery('.facebook-type-'+currenttype).each(function(){
			    jQuery(this).parents('tr').first().show();
		    });
	    }

	    function schedule_send_change(){
		    var currenttype = jQuery('[name=schedule_send]:checked').val();
		    if(currenttype == 'later'){
			    jQuery('#schedule_send_later_box').show();
		    }else{
			    jQuery('#schedule_send_later_box').hide();
		    }
	    }
	    jQuery(function(){
		    jQuery('[name=facebook_post_type]').change(facebook_change_post_type);
		    jQuery('[name=schedule_send]').change(schedule_send_change);
		    jQuery('#message_link_url').change(function(){
			    jQuery('#facebook_link_loading_message').html('<?php _e('Loading URL information...');?>');
			    jQuery.ajax({
				    url: ucm.social.facebook.api_url,
				    data: {
					    action:'support_hub_fb_url_info',
					    url: jQuery(this).val(),
                        wp_nonce: support_hub.wp_nonce
				    },
				    dataType: 'json',
				    success: function(res){
					    jQuery('.facebook-type-link').each(function(){
						    var elm = jQuery(this).parent().find('input');
						    if(res && typeof res[elm.attr('name')] != 'undefined' && res[elm.attr('name')].length > 0){
							    elm.val(res[elm.attr('name')]);
						    }
					    });
				    },
				    complete: function(){
					    jQuery('#facebook_link_loading_message').html('');
				    }
			    });
		    });
		    facebook_change_post_type();
		    ucm.social.init(); // to get datepicker
		    <?php foreach($this->message_managers as $message_id => $message_manager){
				$message_manager->init_js();
			} ?>
	    });

		
	</script>


	</div>
</div>
</div>

</div>