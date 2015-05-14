<?php
if(!isset($social_linkedin_id) || !isset($social_linkedin_message_id)){
	exit;
} ?>

	<?php

if($social_linkedin_id && $social_linkedin_message_id){
	$linkedin = new ucm_linkedin_account($social_linkedin_id);
    if($social_linkedin_id && $linkedin->get('social_linkedin_id') == $social_linkedin_id){
	    $linkedin_message = new ucm_linkedin_message( $linkedin, false, $social_linkedin_message_id );
	    if($social_linkedin_message_id && $linkedin_message->get('social_linkedin_message_id') == $social_linkedin_message_id && $linkedin_message->get('social_linkedin_id') == $social_linkedin_id){

		    $comments         = $linkedin_message->get_comments();
		    $linkedin_message->mark_as_read();

		    ?>

			<form action="" method="post" id="linkedin_edit_form">
				<div id="linkedin_message_header">
					<div style="float:right; text-align: right; margin-top:-4px;">
						<small><?php echo ucm_print_date( $linkedin_message->get('last_active'), true ); ?> </small><br/>
					    <?php if($linkedin_message->get('status') == _SOCIAL_MESSAGE_STATUS_ANSWERED){  ?>
						    <a href="#" class="sociallinkedin_message_action  btn btn-default btn-xs button"
						       data-action="set-unanswered" data-id="<?php echo (int)$linkedin_message->get('social_linkedin_message_id');?>"><?php _e( 'Inbox' ); ?></a>
					    <?php }else{ ?>
						    <a href="#" class="sociallinkedin_message_action  btn btn-default btn-xs button"
						       data-action="set-answered" data-id="<?php echo (int)$linkedin_message->get('social_linkedin_message_id');?>"><?php _e( 'Archive' ); ?></a>
					    <?php } ?>
					</div>
					<img src="<?php echo plugins_url('networks/linkedin/linkedin-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="linkedin_icon">
						    <strong><?php _e('Account:');?></strong> <a href="<?php echo $linkedin_message->get_link(); ?>"
					           target="_blank"><?php echo htmlspecialchars( $linkedin_message->get('linkedin_group') ? $linkedin_message->get('linkedin_group')->get( 'group_name' ) : 'Share' ); ?></a> <br/>
						    <strong><?php _e('Type:');?></strong> <?php echo htmlspecialchars( $linkedin_message->get_type_pretty() ); ?>
				</div>
				<div id="linkedin_message_holder">
		    <?php
		    $linkedin_message->full_message_output(true);
		    ?>
					</div>
		    </form>

	    <?php }
    }
}

if($social_linkedin_id && !(int)$social_linkedin_message_id){
	$linkedin = new ucm_linkedin_account($social_linkedin_id);
    if($social_linkedin_id && $linkedin->get('social_linkedin_id') == $social_linkedin_id){

	    /* @var $groups ucm_linkedin_group[] */
	    $groups = $linkedin->get('groups');
	    //print_r($groups);
	    ?>
	    <form action="" method="post" enctype="multipart/form-data">
		    <input type="hidden" name="_process" value="send_linkedin_message">
			<?php wp_nonce_field( 'send-linkedin' . (int) $linkedin->get( 'social_linkedin_id' ) ); ?>
		    <?php
		    $fieldset_data = array(
			    'heading' => array(
				    'type' => 'h3',
				    'title' => 'Compose Message',
				),
			    'class' => 'tableclass tableclass_form tableclass_full',
			    'elements' => array(
			       'linkedin_group' => array(
			            'title' => __('linkedin Group', 'support_hub'),
			            'fields' => array(),
			        ),
				    'message' => array(
					    'title' => __('Message', 'support_hub'),
					    'field' => array(
						    'type' => 'textarea',
						    'name' => 'message',
						    'id' => 'linkedin_compose_message',
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
						    '<div id="linkedin_link_loading_message"></div>',
						    '<span class="linkedin-type-link linkedin-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="linkedin-type-link linkedin-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="linkedin-type-link linkedin-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="linkedin-type-link linkedin-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="linkedin-type-link linkedin-type-option"></span>', // flag for our JS hide/show hack
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
						    '<span class="linkedin-type-picture linkedin-type-option"></span>', // flag for our JS hide/show hack
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
		    foreach($groups as $linkedin_group_id => $group){
			    $fieldset_data['elements']['linkedin_group']['fields'][] =
				    '<div id="linkedin_compose_group_select">' .
				    '<input type="checkbox" name="compose_group_id['.$linkedin_group_id.']" value="1" checked> ' .
				    '<img src="//graph.linkedin.com/'.$linkedin_group_id.'/picture"> ' .
				    htmlspecialchars($group->get('group_name')) .
				    '</div>'
			    ;
		    }
			echo module_form::generate_fieldset($fieldset_data);


		    ?>
	    </form>

	    <script type="text/javascript">
		    function change_post_type(){
			    var currenttype = jQuery('[name=post_type]:checked').val();
			    jQuery('.linkedin-type-option').each(function(){
				    jQuery(this).parents('tr').first().hide();
			    });
			    jQuery('.linkedin-type-'+currenttype).each(function(){
				    jQuery(this).parents('tr').first().show();
			    });

		    }
		    jQuery(function(){
			    jQuery('[name=post_type]').change(change_post_type);
			    jQuery('#message_link_url').change(function(){
				    jQuery('#linkedin_link_loading_message').html('<?php _e('Loading URL information...');?>');
				    jQuery.ajax({
					    url: '<?php echo '';?>',
					    data: {_process:'ajax_linkedin_url_info', url: jQuery(this).val()},
					    dataType: 'json',
					    success: function(res){
						    jQuery('.linkedin-type-link').each(function(){
							    var elm = jQuery(this).parent().find('input');
							    if(res && typeof res[elm.attr('name')] != 'undefined'){
								    elm.val(res[elm.attr('name')]);
							    }
						    });
					    },
					    complete: function(){
						    jQuery('#linkedin_link_loading_message').html('');
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
