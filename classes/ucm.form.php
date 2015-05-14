<?php

class module_form{
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
                        <option value=""><?php echo (!isset($setting['blank'])||$setting['blank'] === true) ? _l('- Select -') : htmlspecialchars($setting['blank']);?></option>
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
            echo '<label for="'.htmlspecialchars($setting['id']).'">' . _l($setting['label']) .'</label>';
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