<?php
if(!isset($shub_twitter_id) || !isset($shub_twitter_message_id)){
	exit;
} ?>

<?php

if($shub_twitter_id && $shub_twitter_message_id){
	$twitter = new shub_twitter_account($shub_twitter_id);
    if($shub_twitter_id && $twitter->get('shub_twitter_id') == $shub_twitter_id){
	    $twitter_message = new shub_twitter_message( $twitter, $shub_twitter_message_id );
	    if($shub_twitter_message_id && $twitter_message->get('shub_twitter_message_id') == $shub_twitter_message_id && $twitter_message->get('shub_twitter_id') == $shub_twitter_id){

		    $twitter_message->mark_as_read();
		    ?>
			<form action="" method="post" id="twitter_edit_form">
				<div id="twitter_message_header">
					<div style="float:right; text-align: right; margin-top:-4px;">
						<small><?php echo shub_print_date( $twitter_message->get('message_time'), true ); ?> </small><br/>
						    <?php if($twitter_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
							    <a href="#" class="socialtwitter_message_action btn btn-default btn-xs button"
							       data-action="set-unanswered" data-id="<?php echo (int)$twitter_message->get('shub_twitter_message_id');?>" data-shub_twitter_id="<?php echo (int)$twitter_message->get('shub_twitter_id');?>"><?php _e( 'Inbox' ); ?></a>
						    <?php }else{ ?>
							    <a href="#" class="socialtwitter_message_action btn btn-default btn-xs button"
							       data-action="set-answered" data-id="<?php echo (int)$twitter_message->get('shub_twitter_message_id');?>" data-shub_twitter_id="<?php echo (int)$twitter_message->get('shub_twitter_id');?>"><?php _e( 'Archive' ); ?></a>
						    <?php } ?>
					</div>
					<img src="<?php echo plugins_url('networks/twitter/twitter-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="twitter_icon">
					<strong><?php _e('Account:');?></strong> <a href="<?php echo $twitter_message->get_link(); ?>"
					           target="_blank"><?php echo htmlspecialchars( $twitter_message->get('twitter_account')->get( 'account_name' ) ); ?></a> <br/>
						    <strong><?php _e('Type:');?></strong> <?php echo htmlspecialchars( $twitter_message->get_type_pretty() ); ?>
				</div>
				<div id="twitter_message_holder">
			    <?php
			    $twitter_message->full_message_output(true);
			    ?>
				</div>
		    </form>

		    <script type="text/javascript">
			    setTimeout(function(){ jQuery('#TB_ajaxContent').scrollTop(jQuery('.twitter_comment_current').offset().top) },100);
			    ucm.social.twitter.init();
		    </script>
	    <?php }
    }
}

if($shub_twitter_id && !$shub_twitter_message_id){
	$twitter = new shub_twitter_account($shub_twitter_id);
    if($shub_twitter_id && $twitter->get('shub_twitter_id') == $shub_twitter_id){

	    ?>

	    <form action="" method="post" enctype="multipart/form-data">
		    <input type="hidden" name="_process" value="send_twitter_message">
		    <?php wp_nonce_field( 'send-twitter' . (int) $twitter->get( 'shub_twitter_id' ) ); ?>
		    <?php
		    $fieldset_data = array(
			    'heading' => array(
				    'type' => 'h3',
				    'title' => 'Compose Tweet',
				),
			    'class' => 'tableclass tableclass_form tableclass_full',
			    'elements' => array(
			       'twitter_account' => array(
			            'title' => __('Twitter Account'),
			            'fields' => array(),
			        ),
				    'message' => array(
					    'title' => __('Message'),
					    'field' => array(
						    'type' => 'textarea',
						    'name' => 'message',
						    'id' => 'twitter_compose_message',
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
						    '<input type="file" name="twitter_picture" value=""> (ensure picture is smaller than 1200x1200)',
						    '<span class="twitter-type-picture twitter-type-option"></span>', // flag for our JS hide/show hack
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
		    //foreach($accounts as $twitter_account_id => $account){
		    // do we have a picture?

			    $fieldset_data['elements']['twitter_account']['fields'][] =
				    '<div id="twitter_compose_account_select">' .
				    '<input type="checkbox" name="compose_account_id['.$twitter->get('shub_twitter_id').']" value="1" checked> ' .
				    ($twitter->get_picture() ? '<img src="'.$twitter->get_picture().'">' : '' ).
				    htmlspecialchars($twitter->get('twitter_name')) .
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
			    'onclick' => "window.location.href='".$module->link_open_message_view($shub_twitter_id)."';",
			);*/
			echo shub_module_form::generate_form_actions($form_actions);
		    ?>
	    </form>
		<script type="text/javascript" src="<?php echo plugins_url('networks/twitter/jquery.charactercounter.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>"></script>

	    <script type="text/javascript">
		    function twitter_set_limit(limit){
			    $("#twitter_compose_message").characterCounter({
            	    counterFormat: '%1 characters remaining.',
				    limit: limit
                });
		    }
		    function change_post_type(){
			    var currenttype = $('[name=post_type]:checked').val();
			    $('.twitter-type-option').each(function(){
				    $(this).parents('tr').first().hide();
			    });
			    $('.twitter-type-'+currenttype).each(function(){
				    $(this).parents('tr').first().show();
			    });
			    if(currenttype == 'picture'){
				    twitter_set_limit(119);
			    }else{
				    twitter_set_limit(140);
			    }

		    }
		    $(function(){
			    $('[name=post_type]').change(change_post_type);
			    change_post_type();
		    })
	    </script>

	    <?php
    }
}