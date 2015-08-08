<div class="wrap">
	<h2>
		<?php _e('Support Hub Message','support_hub');?>
	</h2>

    <?php

    // output same content that should be displayed in our modal popup.
    $network = isset($_GET['network']) ? $_GET['network'] : false;
    $message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : false;
    if($network && isset($this->message_managers[$network]) && $message_id > 0){
        $shub_extension_message = $this->message_managers[$network]->get_message( false, false, $message_id);
        if($shub_extension_message->get('shub_'.$network.'_message_id') == $message_id){
            extract(array(
                "shub_{$network}_id" => $shub_extension_message->get($network.'_account')->get('shub_'.$network.'_id'),
                "shub_{$network}_message_id" => $message_id
            ));
            include( trailingslashit( SupportHub::getInstance()->dir ) . 'extensions/'.$network.'/'.$network.'_message.php');
        }
    }


    ?>


</div>