<div class="wrap">
	<h2>
		<?php _e('Support Hub Sent Items','support_hub');?>
		<a href="?page=support_hub_compose" class="add-new-h2"><?php _e('Compose','support_hub');?></a>
	</h2>
    <?php

    $myListTable = new SupportHubSentList();
    $myListTable->set_columns( array(
	    'shub_column_time'    => __( 'Date/Time', 'support_hub' ),
	    'shub_column_account' => __( 'Social Accounts', 'support_hub' ),
		'shub_column_summary'    => __( 'Summary', 'support_hub' ),
		'shub_column_links'    => __( 'Link Clicks', 'support_hub' ),
		//'shub_column_stats'    => __( 'Stats', 'support_hub' ),
		//'shub_column_action'    => __( 'Action', 'support_hub' ),
		'shub_column_post'    => __( 'WP Post', 'support_hub' ),
	) );

	/* @var $message_manager shub_facebook */
	/*foreach($this->message_managers as $message_id => $message_manager){
		$message_manager->load_all_messages($search, $order);
	}*/

    global $wpdb;
    $sql = "SELECT * FROM `"._support_hub_DB_PREFIX."shub_message` ORDER BY `shub_message_id` DESC ";
    $messages = $wpdb->get_results($sql, ARRAY_A);


	$myListTable->set_message_managers($this->message_managers);
	$myListTable->set_data($messages);
	$myListTable->prepare_items();
    ?>
	<form method="post">
	    <input type="hidden" name="page" value="<?php echo htmlspecialchars($_REQUEST['page']); ?>" />
		<?php
	    $myListTable->display();
		?>
	</form>

	<script type="text/javascript">
	    jQuery(function () {
		    ucm.social.init();
		    <?php foreach($this->message_managers as $message_id => $message_manager){
				$message_manager->init_js();
			} ?>
	    });
	</script>


</div>