<?php
if(!isset($shub_google_id) || !isset($shub_google_message_id)){
	exit;
} ?>

<?php

if($shub_google_id && $shub_google_message_id){
	$google = new shub_google_account($shub_google_id);
    if($shub_google_id && $google->get('shub_google_id') == $shub_google_id){
	    $google_message = new shub_google_message( $google, $shub_google_message_id );
	    if($shub_google_message_id && $google_message->get('shub_google_message_id') == $shub_google_message_id && $google_message->get('shub_google_id') == $shub_google_id){

		    $google_message->mark_as_read();
		    ?>
			<form action="" method="post" id="google_edit_form">
				<div id="google_message_header">
					<div style="float:right; text-align: right; margin-top:-4px;">
						<small><?php echo shub_print_date( $google_message->get('message_time'), true ); ?> </small><br/>
						    <?php if($google_message->get('shub_status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
							    <a href="#" class="socialgoogle_message_action btn btn-default btn-xs button"
							       data-action="set-unanswered" data-id="<?php echo (int)$google_message->get('shub_google_message_id');?>" data-shub_google_id="<?php echo (int)$google_message->get('shub_google_id');?>"><?php _e( 'Inbox' ); ?></a>
						    <?php }else{ ?>
							    <a href="#" class="socialgoogle_message_action btn btn-default btn-xs button"
							       data-action="set-answered" data-id="<?php echo (int)$google_message->get('shub_google_message_id');?>" data-shub_google_id="<?php echo (int)$google_message->get('shub_google_id');?>"><?php _e( 'Archive' ); ?></a>
						    <?php } ?>
					</div>
					<img src="<?php echo plugins_url('extensions/google/google-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="google_icon">
					<strong><?php _e('Account:');?></strong> <a href="<?php echo $google_message->get_link(); ?>"
					           target="_blank"><?php echo htmlspecialchars( $google_message->get('google_account')->get( 'account_name' ) ); ?></a> <br/>
						    <strong><?php _e('Type:');?></strong> <?php echo htmlspecialchars( $google_message->get_type_pretty() ); ?>
				</div>
				<div id="google_message_holder">
			    <?php
			    $google_message->full_message_output(true);
			    ?>
				</div>
		    </form>

		    <script type="text/javascript">
			    setTimeout(function(){ jQuery('#TB_ajaxContent').scrollTop(jQuery('.google_comment_current').offset().top) },100);
			    ucm.social.google.init();
		    </script>
	    <?php }
    }
}

if($shub_google_id && !$shub_google_message_id){
	$google = new shub_google_account($shub_google_id);
    if($shub_google_id && $google->get('shub_google_id') == $shub_google_id){

	    ?>

	    <form action="" method="post" enctype="multipart/form-data">
		    <input type="hidden" name="_process" value="send_google_message">
		    <?php wp_nonce_field( 'send-google' . (int) $google->get( 'shub_google_id' ) ); ?>
		    <?php
		    $fieldset_data = array(
			    'heading' => array(
				    'type' => 'h3',
				    'title' => 'Compose Tweet',
				),
			    'class' => 'tableclass tableclass_form tableclass_full',
			    'elements' => array(
			       'google_account' => array(
			            'title' => __('Google Account'),
			            'fields' => array(),
			        ),
				    'message' => array(
					    'title' => __('Message'),
					    'field' => array(
						    'type' => 'textarea',
						    'name' => 'message',
						    'id' => 'google_compose_message',
						    'value' => '',
					    ),
				    ),
				    'type' => array(
					    'title' => __('Type'),
					    'fields' => array(
						    '<input type="radio" name="post_type" id="post_type_wall" value="wall" checked> ',
						    '<label for="post_type_wall">',
						    __('Normal Tweet'),
						    '</label>',
						    '<input type="radio" name="post_type" id="post_type_picture" value="picture"> ',
						    '<label for="post_type_picture">',
						    __('Picture Tweet'),
						    '</label>',
					    ),
				    ),
				    /*'track' => array(
					    'title' => __('Track clicks'),
					    'field' => array(
						    'type' => 'check',
						    'name' => 'track_links',
						    'value' => '1',
						    'help' => 'If this is selected, the links will be automatically shortened so we can track how many clicks are received.',
						    'checked' => false,
					    ),
				    ),*/
				    'picture' => array(
					    'title' => __('Picture'),
					    'fields' => array(
						    '<input type="file" name="picture" value=""> (ensure picture is smaller than 1200x1200)',
						    '<span class="google-type-picture google-type-option"></span>', // flag for our JS hide/show hack
					    ),
				    ),
				    /*'schedule' => array(
					    'title' => __('Schedule'),
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
						    __('Currently: %s', 'support_hub'date('c')),
						    _hr('Leave blank to send now. Pick a date in the future to send this message. When the CRON job runs it will process this message.'),
					    ),
				    ),*/
				    'debug' => array(
					    'title' => __('Debug'),
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
		    //foreach($accounts as $google_account_id => $account){
		    // do we have a picture?

			    $fieldset_data['elements']['google_account']['fields'][] =
				    '<div id="google_compose_account_select">' .
				    '<input type="checkbox" name="compose_account_id['.$google->get('shub_google_id').']" value="1" checked> ' .
				    ($google->get_picture() ? '<img src="'.$google->get_picture().'">' : '' ).
				    htmlspecialchars($google->get('google_name')) .
				    '</div>'
			    ;
		    //}
			echo shub_module_form::generate_fieldset($fieldset_data);

		    $form_actions = array(
			    'class' => 'action_bar action_bar_center',
			    'elements' => array(),
			);
			echo shub_module_form::generate_form_actions($form_actions);

			$form_actions['elements'][] = array(
		        'type' => 'save_button',
		        'name' => 'butt_save',
		        'value' => __('Send'),
		    );

			// always show a cancel button
			/*$form_actions['elements'][] = array(
			    'type' => 'button',
			    'name' => 'cancel',
			    'value' => __('Cancel'),
			    'class' => 'submit_button',
			    'onclick' => "window.location.href='".$module->link_open_message_view($shub_google_id)."';",
			);*/
			echo shub_module_form::generate_form_actions($form_actions);
		    ?>
	    </form>

	    <script type="text/javascript">
		    function change_post_type(){
			    var currenttype = $('[name=post_type]:checked').val();
			    $('.google-type-option').each(function(){
				    $(this).parents('tr').first().hide();
			    });
			    $('.google-type-'+currenttype).each(function(){
				    $(this).parents('tr').first().show();
			    });

		    }
		    $(function(){
			    $('[name=post_type]').change(change_post_type);
			    change_post_type();
		    })
	    </script>

	    <?php
    }
}