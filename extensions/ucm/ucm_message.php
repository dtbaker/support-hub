<?php
if(!isset($shub_account_id) || !isset($shub_message_id)){
    exit;
}

if($shub_account_id && $shub_message_id){
    $ucm = new shub_ucm_account($shub_account_id);
    if($shub_account_id && $ucm->get('shub_account_id') == $shub_account_id){
        $ucm_message = new shub_ucm_message( $ucm, false, $shub_message_id );
        $ucm_message->output_message_page('popup');

    }
}
