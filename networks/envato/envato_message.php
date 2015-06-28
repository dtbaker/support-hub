<?php
if(!isset($shub_envato_id) || !isset($shub_envato_message_id)){
	exit;
} ?>

	<?php

if($shub_envato_id && $shub_envato_message_id){
	$envato = new shub_envato_account($shub_envato_id);
    if($shub_envato_id && $envato->get('shub_envato_id') == $shub_envato_id){
	    $envato_message = new shub_envato_message( $envato, false, $shub_envato_message_id );
	    if($shub_envato_message_id && $envato_message->get('shub_envato_message_id') == $shub_envato_message_id && $envato_message->get('shub_envato_id') == $shub_envato_id){

		    $comments         = $envato_message->get_comments();
		    $envato_message->mark_as_read();

		    $shub_product_id = $envato_message->get('envato_item')->get('shub_product_id');
		    $product_data = array();
			if($shub_product_id) {
				$shub_product = new SupportHubProduct();
				$shub_product->load( $shub_product_id );
				$product_data = $shub_product->get( 'product_data' );
			}
		    $envato_item_data = $envato_message->get('envato_item')->get('envato_data');
		    if(!is_array($envato_item_data))$envato_item_data = array();
		    ?>

			<form action="" method="post" id="envato_edit_form">
				<section class="message_sidebar">
					<header>
						<?php if($envato_item_data && isset($envato_item_data['url']) && $envato_item_data['url']){ ?>
						<a href="<?php echo $envato_item_data['url'];?>/comments/<?php echo $envato_message->get('envato_id');?>" class="socialenvato_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
						<?php } ?>
					    <?php if($envato_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
						    <a href="#" class="socialenvato_message_action shub_message_action btn btn-default btn-xs button"
						       data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
								'network' => 'envato',
								'shub_envato_message_id' => $envato_message->get('shub_envato_message_id'),
							)));?>"><?php _e( 'Inbox' ); ?></a>
					    <?php }else{ ?>
						    <a href="#" class="socialenvato_message_action shub_message_action btn btn-default btn-xs button"
						       data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
								'network' => 'envato',
								'shub_envato_message_id' => $envato_message->get('shub_envato_message_id'),
							)));?>"><?php _e( 'Archive' ); ?></a>
					    <?php } ?>
					</header>

					<img src="<?php echo plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="shub_message_account_icon"> <br/>

				    <strong><?php _e('Account:');?></strong> <a href="<?php echo $envato_message->get_link(); ?>" target="_blank"><?php echo htmlspecialchars( $envato_message->get('envato_account') ? $envato_message->get('envato_account')->get( 'envato_name' ) : 'N/A' ); ?></a> <br/>

					<strong><?php _e('Date:');?></strong> <?php echo shub_print_date( $envato_message->get('last_active'), false ); ?>  <br/>

				    <?php
					if($envato_item_data){
						?>
						<strong><?php _e('Envato Item:');?></strong>
						<a href="<?php echo isset( $envato_item_data['url'] ) ? $envato_item_data['url'] : $envato_message->get_link(); ?>"
						   target="_blank"><?php
							echo htmlspecialchars( $envato_item_data['item'] ); ?></a>
						<br/>
					<?php
					}
					// find out the user details, purchases and if they have any other open messages.
				    $user_hints = array();
				    $first_comment = current($comments);
				    if(isset($first_comment['shub_envato_user_id']) && $first_comment['shub_envato_user_id']){
					    $user_hints['shub_envato_user_id'] = $first_comment['shub_envato_user_id'];
				    }
				    $message_from = @json_decode($first_comment['message_from'],true);
				    if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $envato_message->get('envato_account')->get( 'envato_name' )){
					    // this wont work if user changes their username, oh well.
					    $user_hints['envato_username'] = $message_from['username'];
				    }
					SupportHub::getInstance()->message_user_summary($user_hints, 'envato', $envato_message);
					do_action('supporthub_message_header', 'envato', $envato_message);
					?>

					<a href="#" class="shub_request_extra btn btn-default btn-xs button" data-modaltitle="<?php _e( 'Request Extra Details' ); ?>" data-action="request_extra_details" data-network="envato" data-envato-message-id="<?php echo $envato_message->get('shub_envato_message_id');?>"><?php _e( 'Request Extra Details' ); ?></a>

				</section>
				<section class="message_content">
				    <?php
				    $envato_message->full_message_output(true);
				    ?>
				</section>
				<section class="message_request_extra">
					<?php
					SupportHubExtra::form_request_extra(array(
						'network' => 'envato',
						'network-account-id' => $envato->get('shub_envato_id'),
						'network-message-id' => $envato_message->get('shub_envato_message_id'),
					));
					?>
				</section>
		    </form>

	    <?php }
    }
}

if($shub_envato_id && !(int)$shub_envato_message_id){
	$envato = new shub_envato_account($shub_envato_id);
    if($shub_envato_id && $envato->get('shub_envato_id') == $shub_envato_id){

	    /* @var $groups shub_envato_item[] */
	    $groups = $envato->get('groups');
	    //print_r($groups);
	    ?>
	    <form action="" method="post" enctype="multipart/form-data">
		    <input type="hidden" name="_process" value="send_envato_message">
			<?php wp_nonce_field( 'send-envato' . (int) $envato->get( 'shub_envato_id' ) ); ?>
		    <?php
		    $fieldset_data = array(
			    'heading' => array(
				    'type' => 'h3',
				    'title' => 'Compose message',
				),
			    'class' => 'tableclass tableclass_form tableclass_full',
			    'elements' => array(
			       'envato_item' => array(
			            'title' => __('envato Group', 'support_hub'),
			            'fields' => array(),
			        ),
				    'message' => array(
					    'title' => __('message', 'support_hub'),
					    'field' => array(
						    'type' => 'textarea',
						    'name' => 'message',
						    'id' => 'envato_compose_message',
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
						    '<div id="envato_link_loading_message"></div>',
						    '<span class="envato-type-link envato-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="envato-type-link envato-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="envato-type-link envato-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="envato-type-link envato-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="envato-type-link envato-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="envato-type-picture envato-type-option"></span>', // flag for our JS hide/show hack
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
		    foreach($groups as $envato_item_id => $group){
			    $fieldset_data['elements']['envato_item']['fields'][] =
				    '<div id="envato_compose_group_select">' .
				    '<input type="checkbox" name="compose_group_id['.$envato_item_id.']" value="1" checked> ' .
				    '<img src="//graph.envato.com/'.$envato_item_id.'/picture"> ' .
				    htmlspecialchars($group->get('item_name')) .
				    '</div>'
			    ;
		    }
			echo shub_module_form::generate_fieldset($fieldset_data);


		    ?>
	    </form>

	    <script type="text/javascript">
		    function change_post_type(){
			    var currenttype = jQuery('[name=post_type]:checked').val();
			    jQuery('.envato-type-option').each(function(){
				    jQuery(this).parents('tr').first().hide();
			    });
			    jQuery('.envato-type-'+currenttype).each(function(){
				    jQuery(this).parents('tr').first().show();
			    });

		    }
		    jQuery(function(){
			    jQuery('[name=post_type]').change(change_post_type);
			    jQuery('#message_link_url').change(function(){
				    jQuery('#envato_link_loading_message').html('<?php _e('Loading URL information...');?>');
				    jQuery.ajax({
					    url: '<?php echo '';?>',
					    data: {_process:'ajax_envato_url_info', url: jQuery(this).val()},
					    dataType: 'json',
					    success: function(res){
						    jQuery('.envato-type-link').each(function(){
							    var elm = jQuery(this).parent().find('input');
							    if(res && typeof res[elm.attr('name')] != 'undefined'){
								    elm.val(res[elm.attr('name')]);
							    }
						    });
					    },
					    complete: function(){
						    jQuery('#envato_link_loading_message').html('');
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
