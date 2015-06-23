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
                        <th>
                            <?php _e( 'Extra Description', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <textarea name="extra_description"><?php echo esc_attr($shub_extra->get( 'extra_description' )); ?></textarea>
	                        (e.g. Enter your Website Address so we can see the problem)
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <?php _e( 'Field Type', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <?php shub_module_form::generate_form_element(array(
	                            'type' => 'select',
	                            'options' => array(
		                            'text' => 'Text Field',
		                            'encrypted' => 'Encrypted Field',
	                            ),
	                            'value' => $shub_extra->get( 'field_type' ),
	                            'name' => 'field_type',
                            )); ?>
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
		$extra_details[$extra_detail_id]->edit_link = '<a href="'.$extra_detail->link_edit().'">'.__('Edit','support_hub').'</a>';
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
	<div class="wrap">
			<h2>
				<?php _e( 'Encrypted Field Settings', 'support_hub' ); ?>
			</h2>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/json2.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/jsbn.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/jsbn2.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/prng4.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/rng.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/rsa.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/rsa2.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script language="JavaScript" type="text/javascript" src="<?php echo plugins_url('assets/js/sjcl.js', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>"></script>
		<script type="text/javascript">
		    var rsa2 = {
		        e: '010001', // 010001 (65537 was the old value, this was bad!)
		        bits: 1024,
		        public_key: '<?php echo get_option('shub_encrypt_public_key',''); ?>',
		        private_key: {},
		        private_encrypted: '<?php ?>', // this is what we get from our server.
		        generate: function(passphrase){
		            // we generate a brand new key when creating a new encryption.
		            var rsakey = new RSAKey();
		            var dr = document.rsatest;
		            rsakey.generate(parseInt(this.bits),this.e);
		            this.public_key = rsakey.n.toString(16);
		            //console.debug(this.public_key);
		            this.private_key.d = rsakey.d.toString(16);
		            this.private_key.p = rsakey.p.toString(16);
		            this.private_key.q = rsakey.q.toString(16);
		            this.private_key.dmp1 = rsakey.dmp1.toString(16);
		            this.private_key.dmq1 = rsakey.dmq1.toString(16);
		            this.private_key.coeff = rsakey.coeff.toString(16);
		            var private_string = JSON.stringify(this.private_key);
		            this.private_key = {};
		            // encrypt this private key with our password?
		            this.private_encrypted = sjcl.encrypt(passphrase,private_string);
		            //console.debug(this.private_encrypted);
		        },
		        decrypt_private_key: function(passphrase){
		            try{
		                var p = sjcl.decrypt(passphrase,this.private_encrypted);
		                if(p){
		                    var j = JSON.parse(p);
		                    if(j){
		                        this.private_key = j;
		                        //console.debug(this.private_key);
		                        return true;
		                    }
		                }
		            }catch(e){}
		            return false;
		        },
		        encrypt: function(value){
		            var rsakey = new RSAKey();
		            rsakey.setPublic(this.public_key, this.e);
		            return rsakey.encrypt(value);
		        },
		        decrypt: function(ciphertext){
		            var rsakey = new RSAKey();
		            rsakey.setPrivateEx(this.public_key, this.e, this.private_key.d, this.private_key.p, this.private_key.q, this.private_key.dmp1, this.private_key.dmq1, this.private_key.coeff);
		            return rsakey.decrypt(ciphertext);
		        }
		    };
		    var do_crypt_success = false;
		    function decrypt_raw_data(raw,passphrase){
		        if(do_crypt_success)return;
		        // decrypt our private key from the string.
		        if(rsa2.decrypt_private_key(passphrase)){
		            do_crypt_success = true;
		            var decrypt = rsa2.decrypt(raw);
		            //alert($('#decrypted_value').val().length);
		            $('#password_box').hide();
		            $('#unlocking_box').show();

		        }
		    }
		    function create_new(){
		        var password = jQuery('#encrypt_password').val();
		        if(password.length > 1){
			        jQuery('#encrypt_password').val('');
		            rsa2.generate(password);
		            // post this to our server so we can save it in the db.
		            if(rsa2.public_key.length > 2 && rsa2.private_encrypted.length > 5){
		                // it worked.
		                jQuery('#public_key').val(rsa2.public_key);
		                jQuery('#private_key').val(rsa2.private_encrypted);
			            jQuery('#encryption_form')[0].submit();

		            }else{
		                alert('Encryption generation error');
		            }
		        }else{
		            alert('Please enter a password');
		        }
		    }

		</script>

		<?php if(get_option('shub_encrypt_public_key','')) { ?>
			<table class="form-table">
				<tbody>
				<tr>
					<th class="width1">
						<?php _e( 'Public Cryptography Key', 'support_hub' ); ?>
					</th>
					<td class="">
						<pre><?php echo wordwrap(get_option('shub_encrypt_public_key',''),86,"\n",true); ?></pre>
						<a href="#" onclick="jQuery('#encryption_form').show(); return false;">(generate new key)</a>
					</td>
				</tr>
				</tbody>
			</table>
		<?php } ?>

			<form action="" method="post"<?php if(get_option('shub_encrypt_public_key','')) echo ' style="display:none;"'; ?> id="encryption_form">
				<input type="hidden" name="_process" value="save_encrypted_vault">
				<?php wp_nonce_field( 'save-encrypted-vault' ); ?>

				<input type="hidden" name="public_key" id="public_key" value="">
				<input type="hidden" name="private_key" id="private_key" value="">

				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'Choose a Password', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="password" id="encrypt_password">
							(this password will be used to decrypt any encrypted values, do not forget this password)
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
						<input name="butt_save" type="button" class="button-primary"
						       onclick="create_new(); return false;"
						       value="<?php echo esc_attr( __( 'Generate New Encryption Key', 'support_hub' ) ); ?>"/>
				</p>


			</form>
		</div>
	<?php
}

