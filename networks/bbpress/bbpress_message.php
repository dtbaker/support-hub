<?php
if(!isset($shub_bbpress_id) || !isset($shub_bbpress_message_id)){
	exit;
} ?>

	<?php

if($shub_bbpress_id && $shub_bbpress_message_id){
	$bbpress = new shub_bbpress_account($shub_bbpress_id);
    if($shub_bbpress_id && $bbpress->get('shub_bbpress_id') == $shub_bbpress_id){
	    $bbpress_message = new shub_bbpress_message( $bbpress, false, $shub_bbpress_message_id );
	    if($shub_bbpress_message_id && $bbpress_message->get('shub_bbpress_message_id') == $shub_bbpress_message_id && $bbpress_message->get('shub_bbpress_id') == $shub_bbpress_id){

		    $comments         = $bbpress_message->get_comments();
		    $bbpress_message->mark_as_read();

		    $shub_product_id = $bbpress_message->get('bbpress_forum')->get('shub_product_id');
		    $product_data = array();
			if($shub_product_id) {
				$shub_product = new SupportHubProduct();
				$shub_product->load( $shub_product_id );
				$product_data = $shub_product->get( 'product_data' );
			}
		    ?>

			<form action="" method="post" id="bbpress_edit_form">
				<div id="bbpress_message_header">
					<div style="float:right; text-align: right; margin-top:-4px;">
						<small><?php echo shub_print_date( $bbpress_message->get('last_active'), true ); ?> </small><br/>
						<?php if($product_data && isset($product_data['bbpress_forum_data']['url']) && $product_data['bbpress_forum_data']['url']){ ?>
						<a href="<?php echo $product_data['bbpress_forum_data']['url'];?>/comments/<?php echo $bbpress_message->get('bbpress_id');?>" class="socialbbpress_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
						<?php } ?>
					    <?php if($bbpress_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
						    <a href="#" class="socialbbpress_message_action  btn btn-default btn-xs button"
						       data-action="set-unanswered" data-id="<?php echo (int)$bbpress_message->get('shub_bbpress_message_id');?>"><?php _e( 'Inbox' ); ?></a>
					    <?php }else{ ?>
						    <a href="#" class="socialbbpress_message_action  btn btn-default btn-xs button"
						       data-action="set-answered" data-id="<?php echo (int)$bbpress_message->get('shub_bbpress_message_id');?>"><?php _e( 'Archive' ); ?></a>
					    <?php } ?>
					</div>
					<img src="<?php echo plugins_url('networks/bbpress/bbpress-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="bbpress_icon">
				    <strong><?php _e('Account:');?></strong> <a href="<?php echo $bbpress_message->get_link(); ?>"
			           target="_blank"><?php echo htmlspecialchars( $bbpress_message->get('bbpress_account') ? $bbpress_message->get('bbpress_account')->get( 'bbpress_name' ) : 'N/A' ); ?></a> <br/>
				    <?php
					if(isset($product_data['bbpress_forum_data'])){
						// todo: generalise this so it doesn't rely on products that are only from bbpress.
						?>
						<strong><?php _e('Product:');?></strong>
						<a href="<?php echo isset( $product_data['bbpress_forum_data']['url'] ) ? $product_data['bbpress_forum_data']['url'] : $bbpress_message->get_link(); ?>"
						   target="_blank"><?php
							echo htmlspecialchars( $shub_product->get('product_name') ); ?></a>
						<br/>
					<?php
					}
					// find out the user details, purchases and if they have any other open messages.
				    $user_hints = array();
				    $first_comment = current($comments);
				    if(isset($first_comment['shub_user_id']) && $first_comment['shub_user_id']){
					    $user_hints['shub_user_id'] = $first_comment['shub_user_id'];
				    }
				    $message_from = @json_decode($first_comment['message_from'],true);
				    if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $bbpress_message->get('bbpress_account')->get( 'bbpress_name' )){
					    // this wont work if user changes their username, oh well.
					    $user_hints['bbpress_username'] = $message_from['username'];
				    }
					SupportHub::getInstance()->message_user_summary($user_hints, 'bbpress', $bbpress_message);
					do_action('supporthub_message_header', 'bbpress', $bbpress_message);
					?>
				</div>
				<div id="bbpress_message_holder">
		    <?php
		    $bbpress_message->full_message_output(true);
		    ?>
					</div>
		    </form>

	    <?php }
    }
}

if($shub_bbpress_id && !(int)$shub_bbpress_message_id){
	$bbpress = new shub_bbpress_account($shub_bbpress_id);
    if($shub_bbpress_id && $bbpress->get('shub_bbpress_id') == $shub_bbpress_id){

	    /* @var $groups shub_bbpress_forum[] */
	    $groups = $bbpress->get('groups');
	    //print_r($groups);
	    ?>
	    <form action="" method="post" enctype="multipart/form-data">
		    <input type="hidden" name="_process" value="send_bbpress_message">
			<?php wp_nonce_field( 'send-bbpress' . (int) $bbpress->get( 'shub_bbpress_id' ) ); ?>
		    <?php
		    $fieldset_data = array(
			    'heading' => array(
				    'type' => 'h3',
				    'title' => 'Compose message',
				),
			    'class' => 'tableclass tableclass_form tableclass_full',
			    'elements' => array(
			       'bbpress_forum' => array(
			            'title' => __('bbpress Group', 'support_hub'),
			            'fields' => array(),
			        ),
				    'message' => array(
					    'title' => __('message', 'support_hub'),
					    'field' => array(
						    'type' => 'textarea',
						    'name' => 'message',
						    'id' => 'bbpress_compose_message',
						    'value' => '',
					    ),
				    ),
				    'type' => array(
					    'title' => __('Type', 'support_hub'),
					    'fields' => array(
						    '<input type="radio" name="post_type" id="post_type_wall" value="wall" checked> ',
						    '<label for="post_type_wall">',
						    __('Wall Post', 'support_hub'),
						    '</label>',
						    '<input type="radio" name="post_type" id="post_type_link" value="link"> ',
						    '<label for="post_type_link">',
						    __('Link Post', 'support_hub'),
						    '</label>',
						    '<input type="radio" name="post_type" id="post_type_picture" value="picture"> ',
						    '<label for="post_type_picture">',
						    __('Picture Post', 'support_hub'),
						    '</label>',
					    ),
				    ),
				    'link' => array(
					    'title' => __('Link', 'support_hub'),
					    'fields' => array(
						    array(
							    'type' => 'text',
							    'name' => 'link',
							    'id' => 'message_link_url',
							    'value' => '',
						    ),
						    '<div id="bbpress_link_loading_message"></div>',
						    '<span class="bbpress-type-link bbpress-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    'link_picture' => array(
					    'title' => __('Link Picture', 'support_hub'),
					    'fields' => array(
						    array(
							    'type' => 'text',
							    'name' => 'link_picture',
							    'value' => '',
						    ),
						    ('Full URL (eg: http://) to the picture to use for this link preview'),
						    '<span class="bbpress-type-link bbpress-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    'link_name' => array(
					    'title' => __('Link Title', 'support_hub'),
					    'fields' => array(
						    array(
							    'type' => 'text',
							    'name' => 'link_name',
							    'value' => '',
						    ),
						    ('Title to use instead of the automatically generated one from the Link page'),
						    '<span class="bbpress-type-link bbpress-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    'link_caption' => array(
					    'title' => __('Link Caption', 'support_hub'),
					    'fields' => array(
						    array(
							    'type' => 'text',
							    'name' => 'link_caption',
							    'value' => '',
						    ),
						    ('Caption to use instead of the automatically generated one from the Link page'),
						    '<span class="bbpress-type-link bbpress-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    'link_description' => array(
					    'title' => __('Link Description', 'support_hub'),
					    'fields' => array(
						    array(
							    'type' => 'text',
							    'name' => 'link_description',
							    'value' => '',
						    ),
						    ('Description to use instead of the automatically generated one from the Link page'),
						    '<span class="bbpress-type-link bbpress-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    /*'track' => array(
					    'title' => __('Track clicks', 'support_hub'),
					    'field' => array(
						    'type' => 'check',
						    'name' => 'track_links',
						    'value' => '1',
						    'help' => 'If this is selected, the links will be automatically shortened so we can track how many clicks are received.',
						    'checked' => false,
					    ),
				    ),*/
				    'picture' => array(
					    'title' => __('Picture', 'support_hub'),
					    'fields' => array(
						    '<input type="file" name="picture" value="">',
						    '<span class="bbpress-type-picture bbpress-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    'schedule' => array(
					    'title' => __('Schedule', 'support_hub'),
					    'fields' => array(
						    array(
							    'type' => 'date',
							    'name' => 'schedule_date',
							    'value' => '',
						    ),
						    array(
							    'type' => 'time',
							    'name' => 'schedule_time',
							    'value' => '',
						    ),
						    ' ',
						    sprintf(__('Currently: %s','support_hub'),date('c')),
						    ' (Leave blank to send now, or pick a date in the future.)',
					    ),
				    ),
				    'debug' => array(
					    'title' => __('Debug', 'support_hub'),
					    'field' => array(
						    'type' => 'check',
						    'name' => 'debug',
						    'value' => '1',
						    'checked' => false,
						    'help' => 'Show debug output while posting the message',
					    ),
				    ),
			    )
			);
		    foreach($groups as $bbpress_forum_id => $group){
			    $fieldset_data['elements']['bbpress_forum']['fields'][] =
				    '<div id="bbpress_compose_group_select">' .
				    '<input type="checkbox" name="compose_group_id['.$bbpress_forum_id.']" value="1" checked> ' .
				    '<img src="//graph.bbpress.com/'.$bbpress_forum_id.'/picture"> ' .
				    htmlspecialchars($group->get('forum_name')) .
				    '</div>'
			    ;
		    }
			echo shub_module_form::generate_fieldset($fieldset_data);


		    ?>
	    </form>

	    <script type="text/javascript">
		    function change_post_type(){
			    var currenttype = jQuery('[name=post_type]:checked').val();
			    jQuery('.bbpress-type-option').each(function(){
				    jQuery(this).parents('tr').first().hide();
			    });
			    jQuery('.bbpress-type-'+currenttype).each(function(){
				    jQuery(this).parents('tr').first().show();
			    });

		    }
		    jQuery(function(){
			    jQuery('[name=post_type]').change(change_post_type);
			    jQuery('#message_link_url').change(function(){
				    jQuery('#bbpress_link_loading_message').html('<?php _e('Loading URL information...');?>');
				    jQuery.ajax({
					    url: '<?php echo '';?>',
					    data: {_process:'ajax_bbpress_url_info', url: jQuery(this).val()},
					    dataType: 'json',
					    success: function(res){
						    jQuery('.bbpress-type-link').each(function(){
							    var elm = jQuery(this).parent().find('input');
							    if(res && typeof res[elm.attr('name')] != 'undefined'){
								    elm.val(res[elm.attr('name')]);
							    }
						    });
					    },
					    complete: function(){
						    jQuery('#bbpress_link_loading_message').html('');
					    }
				    });
			    });
			    change_post_type();
		    });
	    </script>

	    <?php
    }
}
?>
