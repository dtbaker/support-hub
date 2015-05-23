<div class="wrap">
	<h2>
		<?php _e('Support Hub Inbox','support_hub');?>
		<a href="?page=support_hub_compose" class="add-new-h2"><?php _e('Compose','support_hub');?></a>
	</h2>
    <?php
    // copy layout from UCM
	// grab a mysql resource from all available social plugins (hardcoded for now - todo: hook)
	$search = isset($_REQUEST['search']) && is_array($_REQUEST['search']) ? $_REQUEST['search'] : array();
	if(!isset($search['status'])){
		$search['status'] = _shub_MESSAGE_STATUS_UNANSWERED;
	}
	$order = array();

	// retuin a combined copy of all available messages, based on search, as a MySQL resource
	// so we can loop through them on the global messages combined page.



    $myListTable = new SupportHubMessageList();
    $myListTable->set_columns( array(
		'cb' => '',
		'shub_column_account' => __( 'Account', 'support_hub' ),
		'shub_column_product' => __( 'Product', 'support_hub' ),
		'shub_column_time'    => __( 'Date/Time', 'support_hub' ),
		'shub_column_from'    => __( 'From', 'support_hub' ),
		'shub_column_summary'    => __( 'Summary', 'support_hub' ),
		'shub_column_action'    => __( 'Action', 'support_hub' ),
	) );
    $myListTable->process_bulk_action(); // before we do the search on messages.


	/* @var $message_manager shub_facebook */
    $limit_each = 40;
    $limit_pages = 2; // get about 10 pages of data to display in WordPress.
	foreach($this->message_managers as $message_manager_id => $message_manager){
		if(isset($search['type']) && !empty($search['type']) && $search['type'] != $message_manager_id)continue;
		$message_manager->load_all_messages($search, $order, $limit_each);
	}

	// filter through each mysql resource so we get the date views. output each row using their individual classes.
	$all_messages = array();
	$loop_messages = array();
	$last_timestamp = false;
    $has_more = false;
	while(true){
		// fill them up
		$has_messages = false;
		foreach($this->message_managers as $type => $message_manager){
			if(!isset($loop_messages[$type])){
				$loop_messages[$type] = $message_manager->get_next_message();
				if($loop_messages[$type]){
					//echo "Got $type with date of ".print_date($loop_messages[$type]['message_time'],true)."<br>\n";
					$loop_messages[$type]['message_manager'] = $message_manager;
					$has_messages = true;
				}else{
					unset($loop_messages[$type]);
				}
			}
		}
		if(!$has_messages && empty($loop_messages)){
			// we didn't get any more messages from any of the message_managers
			//
			break;
		}// todo - limit count here.
		// pick the lowest one and replenish its spot
		$next_type = false;
		foreach($loop_messages as $type => $message){
			if(!$next_type || $message['message_time'] > $last_timestamp){
				$next_type = $type;
				$last_timestamp = $message['message_time'];
			}
		}


		if(count($all_messages) >= ($myListTable->get_pagenum() * $myListTable->items_per_page) + ($limit_pages * $myListTable->items_per_page)){
			$has_more = true; // a flag so we can show "more" in the pagination listing.
			break;
		}

		//echo "Message $next_type : <br>\n";
		$all_messages[] = $loop_messages[$next_type];
		unset($loop_messages[$next_type]);
		// repeat.


	}

	// todo - hack in here some sort of cache so pagination works nicer ?
	//module_debug::log(array( 'title' => 'Finished social messages', 'data' => '', ));
	//print_r($all_messages);

	$myListTable->set_data($all_messages);
	$myListTable->prepare_items();
    $myListTable->pagination_has_more = $has_more;
    ?>
	<form method="post">
	    <input type="hidden" name="page" value="<?php echo htmlspecialchars($_REQUEST['page']); ?>" />
	    <?php //$myListTable->search_box(__('Search','support_hub'), 'search_id'); ?>
		<p class="search-box">
		<label for="simple_inbox-search-type"><?php _e('Network:','support_hub');?></label>
		<select id="simple_inbox-search-type" name="search[type]">
			<option value=""><?php _e('All','support_hub');?></option>
			<?php foreach($this->message_managers as $message_manager_id => $message_manager){ ?>
			<option value="<?php echo $message_manager_id;?>"<?php echo isset($search['type']) && $search['type'] == $message_manager_id ? ' selected' : '';?>><?php echo $message_manager->friendly_name;?></option>
			<?php } ?>
		</select>
		<label for="simple_inbox-search-product"><?php _e('Product:','support_hub');?></label>
		<select id="simple_inbox-search-product" name="search[shub_product_id]">
			<option value=""><?php _e('All','support_hub');?></option>
			<?php foreach(SupportHub::getInstance()->get_products() as $product){ ?>
			<option value="<?php echo $product['shub_product_id'];?>"<?php echo isset($search['shub_product_id']) && $search['shub_product_id'] == $product['shub_product_id'] ? ' selected' : '';?>><?php echo esc_attr( $product['product_name'] );?></option>
			<?php } ?>
		</select>
		<label for="simple_inbox-search-input"><?php _e('Message Content:','support_hub');?></label>
		<input type="search" id="simple_inbox-search-input" name="search[generic]" value="<?php echo isset($search['generic']) ? esc_attr($search['generic']) : '';?>">

		<label for="simple_inbox-search-status"><?php _e('Status:','support_hub');?></label>
		<select id="simple_inbox-search-status" name="search[status]">
			<option value="<?php echo _shub_MESSAGE_STATUS_UNANSWERED;?>"<?php echo isset($search['status']) && $search['status'] == _shub_MESSAGE_STATUS_UNANSWERED ? ' selected' : '';?>><?php _e('Inbox','support_hub');?></option>
			<option value="<?php echo _shub_MESSAGE_STATUS_ANSWERED;?>"<?php echo isset($search['status']) && $search['status'] == _shub_MESSAGE_STATUS_ANSWERED ? ' selected' : '';?>><?php _e('Archived','support_hub');?></option>
		</select>

		<input type="submit" name="" id="search-submit" class="button" value="<?php _e('Search','support_hub');?>"></p>
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