<div class="wrap">
	<h2>
		<?php _e('Support Hub Message','support_hub');?>
	</h2>

    <div id="shub_table_inline">
        <div id="shub_table_contents">
        <?php
        // output same content that should be displayed in our modal popup.
        $network = isset($_GET['network']) ? $_GET['network'] : false;
        $shub_message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : false;
        if($network && isset($this->message_managers[$network]) && $shub_message_id > 0){
            $shub_extension_message = $this->message_managers[$network]->get_message( false, false, $shub_message_id);
            if($shub_extension_message->get('shub_message_id') == $shub_message_id){
                $shub_account_id = $shub_extension_message->get('account')->get('shub_account_id');
                include( trailingslashit( SupportHub::getInstance()->dir ) . 'extensions/'.$network.'/'.$network.'_message.php');
            }
        }
        ?>
        </div>
    </div>


    <script type="text/javascript">
        jQuery(function () {
            ucm.social.init();
            <?php foreach($this->message_managers as $message_id => $message_manager){
                $message_manager->init_js();
            } ?>
        });
    </script>


</div>