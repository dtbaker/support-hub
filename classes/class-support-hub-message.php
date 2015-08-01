<?php

class SupportHub_message{

    protected $network = '';

    public function get($key){return '';}
    public function get_link(){ return '';} // link to external support system
    public function message_sidebar_data(){ echo '';}
    public function reply_actions(){ echo '';}
    public function queue_reply($envato_id, $message, $debug = false, $extra_data = array(), $shub_outbox_id = false){return false;}

    public function get_product_id(){
        return $this->get('shub_product_id');
    }

    public function output_message_page(){
        $network_message_id = $this->get('shub_'.$this->network.'_message_id');
        if($network_message_id && $this->get('shub_'.$this->network.'_id')){

            $this->mark_as_read();

            ?>

            <div class="message_edit_form" data-network="<?php echo $this->network;?>">
                <section class="message_sidebar">
                    <nav>
                        <a href="<?php echo $this->get_link(); ?>" class="social_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
                        <a href="#" class="shub_view_full_message_sudebar btn btn-default btn-xs button alignright"><?php _e( 'Show More Details' ); ?></a>
                    </nav>
                    <header>
                        <a href="<?php echo $this->get_link(); ?>" class="social_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
                        <?php if($this->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button"
                               data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_'.$this->network.'_message_id' => $network_message_id,
                            )));?>"><?php _e( 'Inbox' ); ?></a>
                        <?php }else{ ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button"
                               data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_'.$this->network.'_message_id' => $network_message_id,
                            )));?>"><?php _e( 'Archive' ); ?></a>
                        <?php } ?>
                    </header>
                    <aside>

                        <?php $this->message_sidebar_data(); ?>

                        <?php
                        // find out the user details, purchases and if they have any other open messages.
                        $user_hints = array(
                            'shub_user_id' => array()
                        );
                        $user_hints = $this->get_user_hints($user_hints);
                        SupportHub::getInstance()->message_user_summary($user_hints, $this->network, $this);
                        do_action('supporthub_message_header', $this->network, $this);
                        ?>

                        <a href="#" class="shub_request_extra btn btn-default btn-xs button" data-modaltitle="<?php _e( 'Request Extra Details' ); ?>" data-action="request_extra_details" data-network="<?php echo $this->network;?>" data-<?php echo $this->network;?>-message-id="<?php echo $network_message_id;?>"><?php _e( 'Request Extra Details' ); ?></a>
                    </aside>
                </section>
                <section class="message_content">
                    <?php
                    // we display the first "primary" message (from the ucm_message table) followed by comments from the ucm_message_comment table.
                    //$this->full_message_output(true);
                    $comments         = $this->get_comments();
                    $x=0;
                    foreach($comments as $comment){
                        $x++;
                        $from_user = $this->get_user($comment['shub_user_id']);
                        $time = isset($comment['time']) ? $comment['time'] : false;
                        // is this a queued-to-send message?
                        $extra_class = '';
                        $comment_status = '';
                        if(!empty($comment['shub_outbox_id'])){
                            $shub_outbox = new SupportHubOutbox($comment['shub_outbox_id']);
                            switch($shub_outbox->get('status')){
                                case _SHUB_OUTBOX_STATUS_QUEUED:
                                case _SHUB_OUTBOX_STATUS_SENDING:
                                    $extra_class .= ' outbox_queued';
                                    $comment_status = 'Currently Sending....';
                                    break;
                                case _SHUB_OUTBOX_STATUS_FAILED:
                                    $extra_class .= ' outbox_failed';
                                    $comment_status = 'Failed to send message! Please check logs.';
                                    break;
                            }
                        }
                        ?>
                        <div class="shub_message shub_message_<?php echo $x==1 ? 'primary' : 'reply'; echo $extra_class;?>">
                            <div class="shub_message_picture">
                                <img src="<?php echo $from_user->get_image();?>" />
                            </div>
                            <div class="shub_message_header">
                                <?php if($comment_status){ ?>
                                    <div class="shub_comment_status"><?php echo $comment_status;?></div>
                                <?php } ?>
                                <?php echo $from_user->get_full_link(); ?>
                                <span>
                                    <?php if($time){ ?>
                                    <span class="time" data-time="<?php echo esc_attr($time);?>" data-date="<?php echo esc_attr(shub_print_date($time,true));?>"><?php echo shub_pretty_date($time);?></span>
                                    <?php } ?>
                                    <span class="wp_user">
                                    <?php
                                    // todo - better this! don't call on every message, load list in main loop and pass through all results.
                                    if ( isset( $envato_data['user_id'] ) && $envato_data['user_id'] ) {
                                        $user_info = get_userdata($envato_data['user_id']);
                                        echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
                                    }
                                    ?>
                                    </span>
                                </span>
                            </div>
                            <div class="shub_message_body">
                                <div>
                                    <?php
                                    echo shub_forum_text($comment['message_text']);?>
                                </div>
                            </div>
                            <div class="shub_message_actions">
                            </div>
                        </div>
                    <?php } ?>
                    <div class="shub_message shub_message_reply shub_message_reply_box">
                        <?php
                        $reply_shub_user = $this->get_reply_user();
                        ?>
                        <div class="shub_message_picture">
                           <!--  <img src="<?php echo $reply_shub_user->get_image();?>" /> -->
                        </div>
                        <div class="shub_message_header">
                            <!-- <?php echo $reply_shub_user->get_full_link(); ?> -->
                        </div>
                        <div class="shub_message_body">
                            <textarea placeholder="Write a reply..."></textarea>
                            <button data-post="<?php echo esc_attr(json_encode(array(
                                'network-account-id' => $this->get('shub_'.$this->network.'_id'),
                                'network-message-id' => $network_message_id,
                                'network' => $this->network,
                            )));?>" class="btn button"><?php _e('Send');?></button>
                        </div>
                        <div class="shub_message_actions">
                            <input id="message_reply_debug_<?php echo $network_message_id;?>" type="checkbox" name="debug" data-reply="yes" value="1"> <label for="message_reply_debug_<?php echo $network_message_id;?>"><?php _e('enable debug mode','shub');?></label>
                            <?php $this->reply_actions();?>
                        </div>
                    </div>
                </section>
                <section class="message_request_extra">
                    <?php
                    SupportHubExtra::form_request_extra(array(
                        'network' => $this->network,
                        'network-account-id' => $this->get('shub_'.$this->network.'_id'),
                        'network-message-id' => $network_message_id,
                    ));
                    ?>
                </section>
            </div>

        <?php }
    }

}