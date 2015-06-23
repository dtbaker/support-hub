<?php

class shub_module_form{

	public static $done_encrypt_scripts = false;

	 public static function generate_form_element($setting){

        if(isset($setting['ignore'])&&$setting['ignore'])return;
        // type defaults
        if($setting['type']=='currency'){
            $setting['class'] = (isset($setting['class']) ? $setting['class'] . ' ': '') . 'currency';
        }
        if($setting['type']=='date'){
            $setting['class'] = (isset($setting['class']) ? $setting['class'] . ' ': '') . 'date_field';
            $setting['type'] = 'text';
        }
        if($setting['type']=='time'){
            $setting['class'] = (isset($setting['class']) ? $setting['class'] . ' ': '') . 'time_field';
            $setting['type'] = 'text';
        }
		 if($setting['type']=='encrypted' && (!isset($setting['id'])||!$setting['id'])){
			 $setting['id'] = md5($setting['name']);
		 }
        if($setting['type']=='select' || $setting['type']=='wysiwyg'){
            if(!isset($setting['id'])||!$setting['id']){
                $setting['id'] = $setting['name'];
            }
        }
        if($setting['type']=='save_button'){
            $setting['type'] = 'submit';
            $setting['class'] = (isset($setting['class']) ? $setting['class'] . ' ': '') . 'submit_button save_button';
        }
        if($setting['type']=='delete_button'){
            $setting['type'] = 'submit';
            $setting['class'] = (isset($setting['class']) ? $setting['class'] . ' ': '') . 'submit_button delete_button';
        }


        if(isset($setting['label']) && (!isset($setting['id'])||!$setting['id'])){
            // labels need ids
            $setting['id'] = md5($setting['name']);
        }

        $attributes = '';
        foreach(array('size','style','autocomplete','placeholder','class','id','onclick') as $attr){
            if(isset($setting[$attr])){
                $attributes .= ' '.$attr.'="'.$setting[$attr].'"';
            }
        }

        if(!isset($setting['value']))$setting['value']='';


        ob_start();

        // handle multiple options
        $loop_count = 1;
        if(isset($setting['multiple']) && $setting['multiple']){
            // has to have at least 1 value
            if($setting['multiple'] === true){
                // create our wrapper id.
                $multiple_id = md5(serialize($setting));
                echo '<div id="'.$multiple_id.'">';
            }else{
                $multiple_id = $setting['multiple'];
            }
            if(!isset($setting['values']))$setting['values'] = array($setting['value']);
            $loop_count = count($setting['values']);
        }
        for($x=0; $x<$loop_count; $x++){


            if(isset($setting['multiple']) && $setting['multiple']){
                $setting['value'] = isset($setting['values'][$x]) ? $setting['values'][$x] : false;
                echo '<div class="dynamic_block">';
            }

            switch($setting['type']){
                case 'currency':
                    echo currency('<input type="text" name="'.$setting['name'].'" value="'.htmlspecialchars($setting['value']).'"'.$attributes.'>',true,isset($setting['currency_id']) ? $setting['currency_id'] : false);
                    break;
                case 'number':
                    ?>
                    <input type="text" name="<?php echo $setting['name'];?>" value="<?php echo htmlspecialchars($setting['value']);?>"<?php echo $attributes;?>>
                    <?php
                    break;
                case 'encrypted':
					if(!self::$done_encrypt_scripts){
						self::$done_encrypt_scripts = true;
						?> 
					
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
						            //console.log(this.public_key);
						            //console.log(this.e);
						            //console.log(this.private_key.d);
						            //console.log(this.private_key.p);
						            //console.log(this.private_key.q);
						            //console.log(this.private_key.dmp1);
						            //console.log(this.private_key.dmq1);
						            //console.log(this.private_key.coeff);
						            rsakey.setPrivateEx(this.public_key, this.e, this.private_key.d, this.private_key.p, this.private_key.q, this.private_key.dmp1, this.private_key.dmq1, this.private_key.coeff);
						            return rsakey.decrypt(ciphertext);
						        }
						    };
						    var do_crypt_success = false;
						    function do_crypt(passphrase){
						        if(do_crypt_success)return;
						        // decrypt our private key from the string.
						        if(rsa2.decrypt_private_key(passphrase)){
						            do_crypt_success = true;
						            var raw = '<?php echo $encrypt['data'];?>';
						            var decrypt = rsa2.decrypt(raw);
						            //alert($('#decrypted_value').val().length);
						            $('#password_box').hide();
						            $('#unlocking_box').show();
						            // do an ajax post to tell our logging that we successfully unlocked this entry.
						            // this is not fool proof. turn off your internet after unlocked the password will get around this.
						            // but it's a start.
						            $.ajax({
						                type: 'GET',
						                url: '<?php echo $plugins['encrypt']->link('note_admin',array(
						                    '_process' => 'encrypt_successful',
						                    'encrypt_field_id' => $encrypt_field_id,
						                    'encrypt_id' => $encrypt_id,
						                ));?>',
						                success: function(){
						                    $('#unlocking_box').hide();
						                    if(decrypt && decrypt.length>0){
						                        $('#decrypted_value').val(decrypt);
						                    }
						                    $('#decrypt_box').show();
						                    $('#decrypted_value')[0].focus();
						                },
						                fail: function(){
						                    alert('Decryption failed. Refresh and try again.');
						                }
						            });
						        }
						    }
						    function do_save_decrypted(){
						        $('#<?php echo htmlspecialchars($callback_id);?>').val($('#decrypted_value').val());
						        $('#<?php echo htmlspecialchars($callback_id);?>')[0].form.submit();
						    }
						    function do_save(){
						        var enc = rsa2.encrypt($('#decrypted_value').val());
						        if(enc){
						            $.ajax({
						                type: 'POST',
						                url: '<?php echo $plugins['encrypt']->link('note_admin',array(
						                    '_process' => 'save_encrypt',
						                    'encrypt_field_id' => $encrypt_field_id,
						                    'encrypt_id' => $encrypt_id,
						                ));?>',
						                data: {
						                    encrypt_key_id: $('#encrypt_key_id').val(),
						                    data: enc
						                },
						                dataType: 'json',
						                success: function(h){
						                    // update our hidden field back in the other page.
						                    $('#<?php echo htmlspecialchars($callback_id);?>').val('encrypt:'+ h.encrypt_id);
						                    //alert('<?php _e('Encrypted successfully! Saving...');?>');
						                    $('#<?php echo htmlspecialchars($callback_id);?>')[0].form.submit();
						                },
						                fail: function(){
						                    alert('Something went wrong');
						                }
						            });
						        }
						    }
						    function create_new(){
						        var password = $('#new_passphrase').val();
						        var name = $('#encrypt_key_name').val();
						        if(name.length > 1 && password.length > 1){
						            rsa2.generate(password);
						            // post this to our server so we can save it in the db.
						            if(rsa2.public_key.length > 2 && rsa2.private_encrypted.length > 5){
						                // it worked.
						                $.ajax({
						                    type: 'POST',
						                    url: '<?php echo $plugins['encrypt']->link('note_admin',array(
						                        '_process' => 'save_encrypt_key',
						                        'encrypt_field_id' => $encrypt_field_id,
						                    ));?>',
						                    data: {
						                        encrypt_key_id: 0,
						                        encrypt_key_name: name,
						                        public_key: rsa2.public_key,
						                        secured_private_key: rsa2.private_encrypted,
						                        e: rsa2.e
						                    },
						                    success: function(h){
						                        //alert(h);
						                        //alert('<?php _e('Created successfully!');?>');
						                        $('#env_vault_name').html(name);
						                        $('#enc_create_new').hide();
						                        $('#enc_existing').show();
						                        do_crypt(password);
						                    },
						                    fail: function(){
						                        alert('Something went wrong');
						                    }
						                });
						
						            }else{
						                alert('generation error');
						            }
						        }else{
						            alert('error');
						        }
						    }
						
						</script>
					<?php
					}
                    ?>
                    <input type="text" value="<?php echo htmlspecialchars($setting['value']);?>"<?php echo $attributes;?>>
                    <input type="hidden" name="<?php echo $setting['name'];?>" value="" id="encrypted_<?php echo $setting['id'];?>">
					[encrypted] <a href="#" onclick="jQuery(this).parent().find('.encrypted_learn_more').show(); jQuery(this).hide(); return false;">(learn more)</a>
					<div class="encrypted_learn_more" style="display: none">
						This field is encrypted using industry standard PGP cryptography in JavaScript before getting sent to the server. This provides an extremely high level of security as the raw value is never transmitted with the request and can only be decrypted by a single person here in the office.
					</div>
                    <?php
                    break;
                case 'text':
                    ?>
                    <input type="text" name="<?php echo $setting['name'];?>" value="<?php echo htmlspecialchars($setting['value']);?>"<?php echo $attributes;?>>
                    <?php
                    break;
                case 'password':
                    ?>
                    <input type="password" name="<?php echo $setting['name'];?>" value="<?php echo htmlspecialchars($setting['value']);?>"<?php echo $attributes;?>>
                    <?php
                    break;
                case 'hidden':
                    ?>
                    <input type="hidden" name="<?php echo $setting['name'];?>" value="<?php echo htmlspecialchars($setting['value']);?>"<?php echo $attributes;?>>
                    <?php
                    break;
                case 'textarea':
                    ?>
                    <textarea name="<?php echo $setting['name'];?>" rows="6" cols="50"<?php echo $attributes;?>><?php echo htmlspecialchars($setting['value']);?></textarea>
                    <?php
                    break;
                case 'select':
                    // copied from print_select_box()
                    if(isset($setting['allow_new']) && $setting['allow_new']){
                        $attributes .= ' onchange="dynamic_select_box(this);"';

                    }
                    ?>
                    <select name="<?php echo $setting['name'];?>"<?php echo $attributes;?>>
                        <?php if(!isset($setting['blank'])||$setting['blank']){ ?>
                        <option value=""><?php echo (!isset($setting['blank'])||$setting['blank'] === true) ? __('- Select -') : htmlspecialchars($setting['blank']);?></option>
                        <?php }

                        $found_selected = false;
                        $current_val = 'Enter new value here';
                        $sel = '';
                        foreach($setting['options'] as $key => $val){
                            if(is_array($val)){
                                if(!$setting['options_array_id']){
                                    if(isset($val[$setting['id']]))$setting['options_array_id'] = $setting['id'];
                                    else $setting['options_array_id'] = key($val);
                                }
                                $printval = $val[$setting['options_array_id']];
                            }else{
                                $printval = $val;
                            }
                            if(strlen($printval)==0)continue;
                            $sel .= '<option value="'.htmlspecialchars($key).'"';
                            // to handle 0 elements:
                            if($setting['value'] !== false && ($setting['value'] != '') && $key == $setting['value']){
                                $current_val = $printval;
                                $sel .= ' selected';
                                $found_selected = true;
                            }
                            $sel .= '>'.htmlspecialchars($printval).'</option>';
                        }
                        if($setting['value'] && !$found_selected){
                            $sel .= '<option value="'.htmlspecialchars($setting['value']).'" selected>'.htmlspecialchars($setting['value']).'</option>';
                        }
                        /*if(isset($setting['allow_new']) && $setting['allow_new'] && get_display_mode() != 'mobile'){
                            $sel .= '<option value="create_new_item">'._l(' - Create New - ') .'</option>';
                        }
                        if(isset($setting['allow_new']) && $setting['allow_new']){
                            //$sel .= '<input type="text" name="new_'.$id.'" style="display:none;" value="'.$current_val.'">';
                        }*/
                        echo $sel;
                        ?>
                        <?php /*foreach($setting['options'] as $key=>$val){ ?>
                        <option value="<?php echo $key;?>"<?php echo $setting['value'] == $key ? ' selected':'' ?>><?php echo htmlspecialchars($val);?></option>
                        <?php }*/ ?>
                    </select>
                    <?php
                    break;
                case 'checkbox':
                    ?>
                    <input type="hidden" name="default_<?php echo $setting['name'];?>" value="1">
                    <input type="checkbox" name="<?php echo $setting['name'];?>" value="1" <?php if($setting['value']) echo ' checked'; ?><?php echo $attributes;?>>
                    <?php
                    break;
                case 'check':
                    ?>
                    <input type="checkbox" name="<?php echo $setting['name'];?>" value="<?php echo $setting['value'];?>" <?php if($setting['checked']) echo ' checked'; ?><?php echo $attributes;?>>
                    <?php
                    break;
                case 'submit':
                    ?>
                    <input type="submit" name="<?php echo htmlspecialchars($setting['name']);?>" value="<?php echo htmlspecialchars($setting['value']); ?>" <?php echo $attributes;?>/>
                    <?php
                    break;
                case 'button':
                    ?>
                    <input type="button" name="<?php echo htmlspecialchars($setting['name']);?>" value="<?php echo htmlspecialchars($setting['value']); ?>" <?php echo $attributes;?>/>
                    <?php
                    break;

            }

            if(isset($setting['multiple']) && $setting['multiple']){
                echo '<a href="#" class="add_addit" onclick="return seladd(this);">+</a> <a href="#" class="remove_addit" onclick="return selrem(this);">-</a>';
                echo '</div>';
            }
        }

        if(isset($setting['multiple']) && $setting['multiple']){
            if($setting['multiple'] === true){
                echo '</div>';
            }
            echo '<script type="text/javascript"> set_add_del("'.$multiple_id.'"); </script>';
        }

        $html = ob_get_clean();


        /*if(isset($setting['encrypt']) && $setting['encrypt'] && class_exists('module_encrypt',false)){
            $html = module_encrypt::parse_html_input($setting['page_name'],$html);
        }*/
        echo $html;
        if(isset($setting['label']) && strlen($setting['label'])){
            echo '<label for="'.htmlspecialchars($setting['id']).'">' . __($setting['label']) .'</label>';
        }
        /*if(isset($setting['help']) && (count($setting['help']) || strlen($setting['help']))){
            _h($setting['help']);
        }*/
    }


    public static function generate_fieldset($options){


        $defaults = array(
            'type' => 'table',
            'title' => false,
            'title_type' => 'h3',
            'heading' => false,
            'row_title_class' => 'width1',
            'row_data_class' => '',
            'elements' => array(),
            'class' => 'tableclass tableclass_form',
            'extra_settings' => array(),
            'elements_before' => '',
            'elements_after' => '',
        );
        $options = array_merge($defaults,$options);
        //todo - hook in here for themes.
        ob_start();
        /*if($options['heading']){
            print_heading($options['heading']);
        }else if($options['title']){ */?><!--
            <<?php /*echo $options['title_type'];*/?>><?php /*echo $options['title']; */?></<?php /*echo $options['title_type'];*/?>>
        --><?php /*}*/ ?>
        <?php echo $options['elements_before'];?>
        <?php if($options['elements']){ ?>
        <table class="<?php echo $options['class'];?>">
            <tbody>
            <?php
            foreach($options['elements'] as $element){
                if(isset($element['ignore']) && $element['ignore'])continue;
                if(isset($element['field']) && !isset($element['fields'])){
                    $element['fields'] = array($element['field']);
                    unset($element['field']);
                }
                ?>
                <tr>
                    <?php if((isset($element['message'])&&$element['message']) || (isset($element['warning'])&&isset($element['warning']))){ ?>
                        <td colspan="2" align="center">
                            <?php if(isset($element['message'])){ ?>
                                <?php echo $element['message'];?>
                            <?php }else if(isset($element['warning'])){ ?>
                                <span class="error_text"><?php echo $element['warning'];?></span>
                            <?php } ?>

                        </td>
                    <?php }else{ ?>
                        <?php if(isset($element['title'])){ ?>
                        <th class="<?php echo isset($element['row_title_class']) ? $element['row_title_class'] : $options['row_title_class'];?>">
                            <?php echo htmlspecialchars($element['title']);?>
                        </th>
                        <?php }
                        if(isset($element['fields'])){ ?>
                        <td class="<?php echo isset($element['row_data_class']) ? $element['row_data_class'] : $options['row_data_class'];?>">
                            <?php if(is_array($element['fields'])){
                                foreach($element['fields'] as $dataid => $field){
                                    if(is_array($field)){
                                        // treat this as a call to the form generate option
                                        self::generate_form_element($field);
                                        echo ' ';
                                    }else{
                                        echo $field.' ';
                                    }
                                }
                            }else{
                                echo $element['fields'];
                            }
                            ?>
                        </td>
                    <?php } ?>
                </tr>
                <?php
                }
            }
            /*if(class_exists('module_extra') && module_extra::is_plugin_enabled() && $options['extra_settings']){
                module_extra::display_extras($options['extra_settings']);
            }*/
            ?>
            </tbody>
        </table>
        <?php }
        echo $options['elements_after'];?>
        <?php

        return ob_get_clean();
    }
}