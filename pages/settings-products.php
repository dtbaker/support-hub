<?php

$current_product = isset($_REQUEST['shub_product_id']) ? (int)$_REQUEST['shub_product_id'] : false;
$shub_product = new SupportHubproduct();
if($current_product !== false){
	$shub_product->load($current_product);
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Product Details', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_product_details">
				<input type="hidden" name="shub_product_id"
				       value="<?php echo (int) $shub_product->get( 'shub_product_id' ); ?>">
				<?php wp_nonce_field( 'save-product' . (int) $shub_product->get( 'shub_product_id' ) ); ?>

				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'Product Name', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="product_name" value="<?php echo esc_attr( $shub_product->get( 'product_name' ) ); ?>">
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php if ( $shub_product->get( 'shub_product_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this product and all associated data?', 'support_hub' ); ?>');"/>
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
			'product_name' => __( 'Product Name', 'support_hub' ),
			'edit_link'    => __( 'Action', 'support_hub' ),
		) );
	$product_details = SupportHub::getInstance()->get_products();
	foreach($product_details as $product_detail_id => $product_detail){
		$product_details[$product_detail_id]['edit_link'] = '<a href="?page='. esc_attr($_GET['page']) .'&tab='. esc_attr($_GET['tab']).'&shub_product_id='.$product_detail['shub_product_id'].'">'.__('Edit','support_hub').'</a>';
	}
	$myListTable->set_data($product_details);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('Product Details','support_hub');?>
			<a href="?page=<?php echo esc_attr($_GET['page']);?>&tab=<?php echo esc_attr($_GET['tab']);?>&shub_product_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
		</h2>
	    <?php
	    //$myListTable->search_box( 'search', 'search_id' );
	     $myListTable->display();
		?>
	</div>

	<?php
}

