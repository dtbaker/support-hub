<?php

if(!isset($extra_data))die('Failed to load');

$extra = new SupportHubExtra($extra_data->get('shub_extra_id'));

if(!$extra->get('shub_extra_id'))die('Failed to load extra');

?>

<div class="view_extra_data">

<strong><?php echo htmlspecialchars( $extra->get( 'extra_name' ) );?></strong>
<?php
switch($extra->get('field_type')){
	case 'encrypted':

		$encrypted_value = $extra_data->get('extra_value');
		$data = $extra_data->get('extra_data');
		$private_key = !empty($data['private_key']) ? $data['private_key'] : get_option('shub_encrypt_private_key','');
		if(!$private_key){
			echo 'Warning: unable to find private key. Unable to decrypt any deatils. Please set a new encryption key from Support Hub Settings area';
		}
		?>

		<script type="text/javascript">
			var rsa2 = {
				e: '010001', // 010001 (65537 was the old value, this was bad!)
				bits: 1024,
				public_key: '<?php echo esc_js(get_option('shub_encrypt_public_key','')); ?>',
				private_key: {},
				private_encrypted: '<?php echo $private_key; ?>',
				decrypt_private_key: function(passphrase){
					try{
						var p = sjcl.decrypt(passphrase,this.private_encrypted);
						if(p){
							var j = JSON.parse(p);
							if(j){
								this.private_key = j;
								return true;
							}
						}
					}catch(e){
					}
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
			function do_decrypt(passphrase){
				if(do_crypt_success || !passphrase.length)return;
				decrypt_raw_data('<?php echo esc_js($encrypted_value);?>',passphrase);
			}
			function decrypt_raw_data(raw,passphrase){
				// decrypt our private key from the string.
				if(rsa2.decrypt_private_key(passphrase)){
					do_crypt_success = true;
					var decrypt = rsa2.decrypt(raw);
					jQuery('#encryption_unlock').hide();
					jQuery('#encryption_unlocked').show();
					jQuery('#decrypted_value').val(decrypt);
				}
			}

			jQuery(function(){
				jQuery('#encryption_key').change(function(){
					do_decrypt(jQuery(this).val());
				}).keyup(function(){
					do_decrypt(jQuery(this).val());
				});
			});

		</script>

		<div id="encryption_unlock">
			Enter Password to Decrypt: <input type="password" name="encryption_key" id="encryption_key">
		</div>
		<div id="encryption_unlocked" style="display: none;">
			<textarea id="decrypted_value" name="decrypted_value" rows="10" cols="50"></textarea>
		</div>
		<?php
		break;
	default:
		echo htmlspecialchars( $extra_data->get('extra_value'));
}
?>

	<input type="button" name="shub_close_modal" value="Close">

</div>