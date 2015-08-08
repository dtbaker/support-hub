<?php

class SupportHub_message{

    protected $network = '';

    public function __construct($account = false, $item = false, $shub_message_id = false){
        $this->account = $account;
        $this->item = $item;
        $this->load($shub_message_id);
    }

    /* @var $item SupportHub_item */
    public $item = false;
    /* @var $account SupportHub_account */
    public $account = false;
    public $shub_message_id = false; // the current user id in our system.
    public $details = array();

    public $json_fields = array('data','comments');

    private function reset(){
        $this->shub_message_id = false;
        $this->details = array(
            'shub_message_id' => '',
            'shub_item_id' => '',
            'shub_account_id' => '',
            'network_key' => '',
            'title' => '',
            'summary' => '',
            'last_active' => '',
            'comments' => '',
            'type' => '',
            'link' => '',
            'data' => '',
            'status' => '',
            'user_id' => '',
            'shub_user_id' => 0,
        );
        foreach($this->details as $key=>$val){
            $this->{$key} = '';
        }
    }

    public function create_new(){
        $this->reset();
        $this->shub_message_id = shub_update_insert('shub_message_id',false,'shub_message',array(
            'title' => '',
        ));
        $this->load($this->shub_message_id);
    }

    public function load($shub_message_id = false){
        if(!$shub_message_id)$shub_message_id = $this->shub_message_id;
        $this->reset();
        $this->shub_message_id = $shub_message_id;
        if($this->shub_message_id){
            $data = shub_get_single('shub_message','shub_message_id',$this->shub_message_id);
            foreach($this->details as $key=>$val){
                $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
                if(in_array($key,$this->json_fields)){
                    $this->details[$key] = @json_decode($this->details[$key],true);
                    if(!is_array($this->details[$key]))$this->details[$key] = array();
                }
            }
            if(!is_array($this->details) || !isset($this->details['shub_message_id']) || $this->details['shub_message_id'] != $this->shub_message_id){
                $this->reset();
                return false;
            }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        if(!$this->account && $this->get('shub_account_id')){
            $this->account = SupportHub::getInstance()->message_managers[$this->network]->get_account($this->get('shub_account_id'));
        }
        if(!$this->item && $this->get('shub_item_id')) {
            $this->item = $this->account->get_item($this->get('shub_item_id'));
        }
        return $this->shub_message_id;
    }

    public function get($field){
        if(isset($this->{$field})){
            return $this->{$field};
        }else if(isset($this->data) && is_array($this->data) && isset($this->data[$field])){
            return $this->data[$field];
        }
        return false;
    }



    public function update($field,$value){
        // what fields to we allow? or not allow?
        if(in_array($field,array('shub_message_id')))return;
        if($this->shub_message_id){
            $this->{$field} = $value;
            if(in_array($field,$this->json_fields)){
                $value = json_encode($value);
            }
            shub_update_insert('shub_message_id',$this->shub_message_id,'shub_message',array(
                $field => $value,
            ));
            // special processing for certain fields.
            if($field == 'comments'){
                // we push all thsee messages into a shub_message_comment database table
                // this is so we can do quick lookups on message ids so we dont import duplicate items from graph (ie: a reply on a message comes in as a separate item sometimes)
                $data = is_array($value) ? $value : @json_decode($value,true);
                if(is_array($data)) {
                    // clear previous message history.
                    $existing_messages = $this->get_comments(); //shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id');
                    //shub_delete_from_db('shub_message_comment','shub_message_id',$this->shub_message_id);
                    $remaining_messages = $this->_update_comments( $data , $existing_messages);
                    // $remaining_messages contains any messages that no longer exist...
                    // todo: remove these? yer prolly. do a quick test on removing a message - i think the only thing is it will show the 'from' name still.
                }
            }
        }
    }

    public function parse_links($content = false){
        if(!$this->get('shub_message_id'))return;
        // strip out any links in the tweet and write them to the envato_message_link table.
        $url_clickable = '~
		            ([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
		            (                                                      # 2: URL
		                    [\\w]{1,20}+://                                # Scheme and hier-part prefix
		                    (?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
		                    [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
		                    (?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
		                            [\'.,;:!?)]                            # Punctuation URL character
		                            [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
		                    )*
		            )
		            (\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
		    ~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
        // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.
        if(!$content){
            $content = $this->get('summary');
            $doing_summary = true;
        }
        $summary = ' ' . $content . ' ';
        if(strlen($summary) && preg_match_all($url_clickable,$summary,$matches)){
            foreach($matches[2] as $id => $url){
                $url = trim($url);
                if(strlen($url)) {
                    // wack this url into the database and replace it with our rewritten url.
                    $shub_message_link_id = shub_update_insert( 'shub_message_link_id', false, 'shub_message_link', array(
                        'shub_message_id' => $this->get('shub_message_id'),
                        'link' => $url,
                    ) );
                    if($shub_message_link_id) {
                        $new_link = trailingslashit( get_site_url() );
                        $new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
                        $new_link .= _SUPPORT_HUB_LINK_REWRITE_PREFIX . '=' . $shub_message_link_id;
                        // basic hash to stop brute force.
                        if(defined('AUTH_KEY')){
                            $new_link .= ':'.substr(md5(AUTH_KEY.' envato link '.$shub_message_link_id),1,5);
                        }
                        $newsummary = trim(preg_replace('#'.preg_quote($url,'#').'#',$new_link,$summary, 1));
                        if(strlen($newsummary)){// just incase.
                            $summary = $newsummary;
                        }
                    }
                }
            }
        }
        if(isset($doing_summary) && $doing_summary){
            $this->update('summary',$summary);
        }
        return trim($summary);
    }

    private function _update_comments($data, $existing_messages){
        if(is_array($data)){
            $last_message_user_name = false;
            foreach($data as $message){
                if($message['id']){
                    // does this id exist in the db already?
                    $exists = shub_get_single('shub_message_comment',array('network_key','shub_message_id'),array($message['id'],$this->shub_message_id));

                    // create/update a user entry for this comments.
                    $shub_user_id = 0;
                    if(!empty($message['username'])) {
                        $comment_user = new SupportHubUser_Envato();
                        $res = $comment_user->load_by( 'user_username', $message['username']);
                        if(!$res){
                            $comment_user -> create_new();
                            if(!$comment_user->get('user_username'))$comment_user -> update('user_username', $message['username']);
                            $comment_user -> update_user_data(array(
                                'image' => $message['profile_image_url'],
                                'envato' => $message,
                            ));
                        }
                        $shub_user_id = $comment_user->get('shub_user_id');
                    }

                    $shub_message_comment_id = shub_update_insert('shub_message_comment_id',$exists ? $exists['shub_message_comment_id'] : false,'shub_message_comment',array(
                        'shub_message_id' => $this->shub_message_id,
                        'network_key' => $message['id'],
                        'time' => isset($message['created_at']) ? strtotime($message['created_at']) : 0,
                        'data' => json_encode($message),
                        'message_from' => isset($message['username']) ? json_encode(array("username"=>$message['username'],"profile_image_url"=>$message['profile_image_url'])) : '',
                        'message_to' => '',
                        'message_text' => isset($message['content']) ? $message['content'] : '',
                        'shub_user_id' => $shub_user_id,
                    ));
                    $last_message_user_name = isset($message['username']) ? $message['username'] : false;
                    if(isset($existing_messages[$shub_message_comment_id])){
                        unset($existing_messages[$shub_message_comment_id]);
                    }
                    /*if(isset($message['comments']) && is_array($message['comments'])){
                        $existing_messages = $this->_update_messages($message['comments'], $existing_messages);
                    }*/
                }
            }
            if($last_message_user_name){
                $account_user_data = $this->account->get('account_data');
                if($account_user_data && isset($account_user_data['user']) && $last_message_user_name == $account_user_data['user']['username']){
                    // the last comment on this item was from the account owner.
                    // mark this item as resolves so it doesn;t show up in the inbox.
                    $this->update('status',_shub_MESSAGE_STATUS_ANSWERED);

                }
            }
        }
        return $existing_messages;
    }

    public function delete(){
        if($this->shub_message_id) {
            shub_delete_from_db( 'shub_message', 'shub_message_id', $this->shub_message_id );
        }
    }


    public function mark_as_read(){
        if($this->shub_message_id && get_current_user_id()){
            $sql = "REPLACE INTO `"._support_hub_DB_PREFIX."shub_message_read` SET `shub_message_id` = ".(int)$this->shub_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
            shub_query($sql);
        }
    }

    public function get_summary() {
        // who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
        $title = $this->get( 'title' );
        return htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title );
//		$summary = $this->get( 'summary' );
//	    return htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title ) . ($summary!=$title ? '<br/>' .htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary ) : '');
    }


    public function queue_reply($network_key, $message, $debug = false, $extra_data = array(), $shub_outbox_id = false){
        if($this->account && $this->shub_message_id) {


            if($debug)echo "Type: ".$this->get('type')." <br>\n";
            switch($this->get('type')) {
                case 'item_comment':
                    if(!$network_key)$network_key = $this->get('network_key');

                    if($debug)echo "Sending a reply to Envato Comment ID: $network_key <br>\n";

                    $result = false;
                    // send via api
                    $item_data = $this->get('item')->get('item_data');
                    if($item_data && $item_data['url']){

                        $reply_user = $this->get_reply_user();
                        // add a placeholder in the comments table, next time the cron runs it should pick this up and fill in all the details correctly from the API
                        $shub_message_comment_id = shub_update_insert('shub_message_comment_id',false,'shub_message_comment',array(
                            'shub_message_id' => $this->shub_message_id,
                            'shub_user_id' => $reply_user->get('shub_user_id'), // we get the main shub user id for sending messages from this account.
                            'shub_outbox_id' => $shub_outbox_id,
                            'network_key' => '',
                            'time' => time(),
                            'message_text' => $message,
                            'user_id' => get_current_user_id(),
                        ));
                        $this->update('status',_shub_MESSAGE_STATUS_ANSWERED);
                        if($debug){
                            echo "Successfully added comment with id $shub_message_comment_id <br>\n";
                        }
                        return $shub_message_comment_id;


                    }

                    break;
            }
        }
        return false;
    }
    public function get_comments($message_data = false) {
        if($message_data){
            $messages = $message_data;
            if(!is_array($messages))$messages=array();
            usort($messages,function($a,$b){
                if(isset($a['id'])){
                    return $a['id'] > $b['id'];
                }
                return strtotime($a['created_at']) > strtotime($b['created_at']);
            });
        }else{
            $messages = shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id'); //@json_decode($this->get('comments'),true);
        }
        return $messages;
    }

    public function link_open(){
        return 'admin.php?page=support_hub_main&network='.$this->network.'&message_id='.$this->shub_message_id;
    }



    public function get_link(){ return '';} // link to external support system
    public function message_sidebar_data(){ echo '';}
    public function reply_actions(){ echo '';}

    public function get_product_id(){
        return $this->get('shub_product_id');
    }

    public function output_message_page($type='inline'){
        $message_id = $this->get('shub_message_id');
        if($message_id && $this->get('shub_account_id')){

            if('popup' == $type){
                $this->mark_as_read();
            }

            ?>

            <div class="message_edit_form" data-network="<?php echo $this->network;?>">
                <section class="message_sidebar">
                    <nav>
                        <a href="<?php echo $this->get_link(); ?>" class="social_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
                        <a href="#" class="shub_view_full_message_sidebar btn btn-default btn-xs button alignright"><?php _e( 'Show More Details' ); ?></a>
                    </nav>
                    <header>
                        <a href="<?php echo $this->get_link(); ?>" class="social_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
                        <?php if($this->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button"
                               data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_message_id' => $message_id,
                            )));?>"><?php _e( 'Inbox' ); ?></a>
                        <?php }else{ ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button"
                               data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_message_id' => $message_id,
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
                            <div class="shub_message_buttons">
                                <a href="#" class="shub_request_extra btn btn-default btn-xs button" data-modaltitle="<?php _e( 'Request Extra Details' ); ?>" data-action="request_extra_details" data-network="<?php echo $this->network;?>" data-<?php echo $this->network;?>-message-id="<?php echo $message_id;?>"><?php _e( 'Request Details' ); ?></a>
                                <a href="#" class="shub_template_button btn btn-default btn-xs button" data-modaltitle="<?php _e( 'Send Template Message' ); ?>" data-action="send_template_message" data-network="<?php echo $this->network;?>" data-<?php echo $this->network;?>-message-id="<?php echo $message_id;?>"><?php _e( 'Template' ); ?></a>

                                <button data-post="<?php echo esc_attr(json_encode(array(
                                    'account-id' => $this->get('shub_account_id'),
                                    'message-id' => $message_id,
                                    'network' => $this->network,
                                )));?>" class="btn button shub_send_message_reply_button"><?php _e('Send');?></button>
                            </div>
                        </div>
                        <div class="shub_message_actions">
                            <div>
                                <label for="message_reply_debug_<?php echo $message_id;?>"><?php _e('Enable Debug Mode','shub');?></label>
                                <input id="message_reply_debug_<?php echo $message_id;?>" type="checkbox" name="debug" data-reply="yes" value="1">
                            </div>
                            <?php $this->reply_actions();?>
                        </div>
                    </div>
                </section>
                <section class="message_request_extra">
                    <?php
                    SupportHubExtra::form_request_extra(array(
                        'network' => $this->network,
                        'account-id' => $this->get('shub_account_id'),
                        'message-id' => $message_id,
                    ));
                    ?>
                </section>
            </div>

        <?php }
    }

}