<div class="wrap">
	<h2>
		<?php _e('Support Hub Settings','support_hub');?>
	</h2>
	<?php
	// find out what tab we're on.
	// is it the main tab or one of the individual plugin settings pages.
	$SupportHub = SupportHub::getInstance();
	$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : false;
	?>
	<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
		<a href="?page=support_hub_settings" class="nav-tab <?php echo !$tab ? ' nav-tab-active' : '';?>"><?php _e('General Settings','support_hub');?></a>
		<?php foreach($SupportHub->message_managers as $message_manager_id => $message_manager){ ?>
			<a href="?page=support_hub_settings&amp;tab=<?php echo $message_manager_id;?>" class="nav-tab <?php echo $tab==$message_manager_id ? ' nav-tab-active' : '';?>"><?php echo $message_manager->friendly_name;?></a>
		<?php } ?>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">Envato Item Comments</a>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">bbPress Forum</a>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">POP3/IMAP</a>
	</h2>
	<br class="clear"/>

	<?php if($tab && isset($SupportHub->message_managers[$tab])){
		$SupportHub->message_managers[$tab]->settings_page();
	}
	?>

</div>