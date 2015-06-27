<?php

	// show logs
	$myListTable = new SupportHubLogList();
	$myListTable->set_columns( array(
			'log_time' => __( 'Time', 'support_hub' ),
			'log_extension'    => __( 'Extension', 'support_hub' ),
			'log_error_level'    => __( 'Error Level', 'support_hub' ),
			'log_subject'    => __( 'Subject', 'support_hub' ),
			'log_data'    => __( 'Data', 'support_hub' ),
		) );
	$latest_logs = shub_get_multiple('shub_log',array(),'shub_log_id','shub_log_id DESC');
	$myListTable->set_data($latest_logs);
	$myListTable->prepare_items();
	?>

	<div class="wrap">
		<h2>
			<?php _e( 'Log Settings', 'support_hub' ); ?>
		</h2>


		<form action="" method="post">
			<input type="hidden" name="_process" value="save_log_settings">
			<?php wp_nonce_field( 'save-log-settings' ); ?>

			<table class="form-table">
				<tbody>
				<tr>
					<th class="width1">
						<?php _e( 'Enable Logging', 'support_hub' ); ?>
					</th>
					<td class="">
                        <?php if(get_option('shub_logging_enabled',0) > time()){ ?>
                            <strong>Logging has been enabled for the next <?php echo ceil((get_option('shub_logging_enabled',0) - time()) / 3600);?> hours</strong><br/>
                        <?php } ?>
						<input type="checkbox" id="enable" name="enable_logging" value="1">
						(enable logging for the next 24 hours)
					</td>
				</tr>
				<tr>
					<th>
						<?php _e( 'Clear Logs', 'support_hub' ); ?>
					</th>
					<td class="">
						<input type="checkbox" id="remove" name="remove_logs" value="<?php echo time();?>">
						(remove all existing logs)
					</td>
				</tr>
				</tbody>
			</table>

			<p class="submit">
				<input name="butt_save" type="submit" class="button-primary"
					   value="<?php echo esc_attr( __( 'Save Settings', 'support_hub' ) ); ?>"/>
			</p>


		</form>
	</div>
	<div class="wrap">
		<h2>
			<?php _e('Support Hub Logs','support_hub');?>
		</h2>
	    <?php
	     $myListTable->display();
		?>
	</div>
	<?php
