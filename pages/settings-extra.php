<?php

$current_extra = isset($_REQUEST['shub_extra_id']) ? (int)$_REQUEST['shub_extra_id'] : false;
$shub_extra = new SupportHubExtra();
if($current_extra !== false){
	$shub_extra->load($current_extra);
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Extra Details', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_extra_details">
				<input type="hidden" name="shub_extra_id"
				       value="<?php echo (int) $shub_extra->get( 'shub_extra_id' ); ?>">
				<?php wp_nonce_field( 'save-extra' . (int) $shub_extra->get( 'shub_extra_id' ) ); ?>

				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'Extra Name', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="extra_name" value="<?php echo esc_attr( $shub_extra->get( 'extra_name' ) ); ?>">
							(e.g. Website Address)
						</td>
					</tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'Extra Description', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <textarea name="extra_description"><?php echo esc_attr($shub_extra->get( 'extra_description' )); ?></textarea>
	                        (e.g. Enter your Website Address so we can see the problem)
                        </td>
                    </tr>
					</tbody>
				</table>

				<p class="submit">
					<?php if ( $shub_extra->get( 'shub_extra_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this extra and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
					<?php } ?>
				</p>


			</form>
		</div>
	<?php
}else{
	// show account overview:
	$myListTable = new SupportHubExtraList();
	$myListTable->set_columns( array(
			'extra_name' => __( 'Extra Name', 'support_hub' ),
			'extra_description'    => __( 'Extra Description', 'support_hub' ),
			'edit_link'    => __( 'Action', 'support_hub' ),
		) );
	$extra_details = $shub_extra->get_all_extras();
	foreach($extra_details as $extra_detail_id => $extra_detail){
		$a = new SupportHubExtra($extra_detail['shub_extra_id']);
		$extra_details[$extra_detail_id]['edit_link'] = '<a href="'.$a->link_edit().'">'.__('Edit','support_hub').'</a>';
	}
	$myListTable->set_data($extra_details);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('Extra Details','support_hub');?>
			<a href="?page=<?php echo esc_attr($_GET['page']);?>&tab=<?php echo esc_attr($_GET['tab']);?>&shub_extra_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
		</h2>
	    <?php
	    //$myListTable->search_box( 'search', 'search_id' );
	     $myListTable->display();
		?>
	</div>
	<?php
}

