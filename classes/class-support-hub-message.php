<?php

class SupportHub_message{

    protected $network = '';

    public function get($key){return '';}
    public function get_link(){ return '';} // link to external support system
    public function message_sidebar_data(){ echo '';}

    public function output_message_page(){
        $network_message_id = $this->get('shub_'.$this->network.'_message_id');
        if($network_message_id && $this->get('shub_'.$this->network.'_id')){

            $this->mark_as_read();

            ?>

            <form action="" method="post" id="message_edit_form" data-network="<?php echo $this->network;?>">
                <section class="message_sidebar">
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
                        ?>
                        <div class="shub_message shub_message_<?php echo $x==1 ? 'primary' : 'reply';?>">
                            <div class="shub_message_picture">
                                <img src="<?php echo $from_user->get_image();?>" />
                            </div>
                            <div class="shub_message_header">
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
                </section>
                <section class="message_reply">
                    <?php
                    $reply_shub_user = $this->get_reply_user();
                    ?>
                    <div class="shub_message_picture">
                        <img src="<?php echo isset($user_data['user'],$user_data['user']['image']) ? $user_data['user']['image'] : '#';?>">
                    </div>
                    <div class="shub_message_header">
                        <?php echo isset($user_data['user']) ? shub_envato::format_person( $user_data['user'], $this->envato_account ) : 'Error'; ?>
                    </div>
                    <div class="shub_message_reply envato_message_reply">
                        <textarea placeholder="Write a reply..."></textarea>
                        <button data-envato-id="<?php echo htmlspecialchars($envato_id);?>" data-post="<?php echo esc_attr(json_encode(array(
                            'id' => (int)$this->shub_envato_message_id,
                            'network' => 'envato',
                            'envato_id' => htmlspecialchars($envato_id),
                        )));?>"><?php _e('Send');?></button>
                    </div>
                    <div class="shub_message_actions">
                        (debug) <input type="checkbox" name="debug" data-reply="yes" value="1"> <br/>
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
            </form>

        <?php }
    }

}