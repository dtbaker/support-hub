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

    public $json_fields = array('shub_data','comments');

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
            'shub_type' => '',
            'shub_link' => '',
            'shub_data' => '',
            'shub_status' => '',
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
	        if(!$this->network){
		        // manually look up which network we are in
		        $account_temp = shub_get_single('shub_account','shub_account_id',$this->get('shub_account_id'));
		        if($account_temp && !empty($account_temp['shub_extension'])){
			        $this->network = $account_temp['shub_extension'];
		        }
	        }
	        if($this->network){
		        $this->account = SupportHub::getInstance()->message_managers[$this->network]->get_account($this->get('shub_account_id'));
	        }
        }
        if(!$this->item && $this->get('shub_item_id') && $this->account) {
            $this->item = $this->account->get_item($this->get('shub_item_id'));
        }
        return $this->shub_message_id;
    }

    /**
     * @param $field string the field name to return
     * @return bool|string|int|array|shub_ucm_item
     */
    public function get($field){
        if(isset($this->{$field})){
            return $this->{$field};
        }else if(isset($this->data) && is_array($this->data) && isset($this->data[$field])){
            return $this->data[$field];
        }else if(isset($this->shub_data) && is_array($this->shub_data) && isset($this->shub_data[$field])){
            return $this->shub_data[$field];
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
            $last_message_user_id = false;
            foreach($data as $message){
                if($message['id']){
                    // does this id exist in the db already?
                    $exists = shub_get_single('shub_message_comment',array('network_key','shub_message_id'),array($message['id'],$this->shub_message_id));

                    // create/update a user entry for this comments.
                    $shub_user_id = 0;
                    if(!empty($message['shub_user_id'])) {
                        $shub_user_id = $message['shub_user_id'];
                    }else if(!empty($message['username'])) {
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
                        'time' => isset($message['created_at']) ? strtotime($message['created_at']) : (isset($message['timestamp']) ? $message['timestamp'] : 0),
                        'data' => json_encode($message),
                        'message_from' => isset($message['username']) ? json_encode(array("username"=>$message['username'],"profile_image_url"=>$message['profile_image_url'])) : '',
                        'message_to' => '',
                        'message_text' => isset($message['content']) ? $message['content'] : '',
                        'shub_user_id' => $shub_user_id,
                    ));
                    $last_message_user_id = $shub_user_id;
                    if(isset($existing_messages[$shub_message_comment_id])){
                        unset($existing_messages[$shub_message_comment_id]);
                    }
                    /*if(isset($message['comments']) && is_array($message['comments'])){
                        $existing_messages = $this->_update_messages($message['comments'], $existing_messages);
                    }*/
                }
            }
            if($last_message_user_id){
                if($last_message_user_id == $this->account->get('shub_user_id')){
                    // the last comment on this item was from the account owner.
                    // mark this item as resolves so it doesn;t show up in the inbox.
                    $this->update('shub_status',_shub_MESSAGE_STATUS_ANSWERED);

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


            if($debug)echo "Type: ".$this->get('shub_type')." <br>\n";
            if(!$network_key)$network_key = $this->get('network_key');

            if($debug)echo "Sending a reply to Network Message ID: $network_key <br>\n";


            $reply_user = $this->get_reply_user();
            // add a placeholder in the comments table, next time the cron runs it should pick this up and fill in all the details correctly from the API
            $shub_message_comment_id = shub_update_insert('shub_message_comment_id',false,'shub_message_comment',array(
                'shub_message_id' => $this->shub_message_id,
                'shub_user_id' => $reply_user ? $reply_user->get('shub_user_id') : 0, // we get the main shub user id for sending messages from this account.
                'shub_outbox_id' => $shub_outbox_id,
                'network_key' => '',
                'time' => time(),
                'message_text' => $message,
                'user_id' => get_current_user_id(),
            ));
            if($debug){
                echo "Successfully added comment with id $shub_message_comment_id <br>\n";
            }
            return $shub_message_comment_id;


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
            $messages = shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id','time'); //@json_decode($this->get('comments'),true);
        }
        return $messages;
    }

    public function link_open(){
        return 'admin.php?page=support_hub_main&network='.$this->network.'&message_id='.$this->shub_message_id;
    }



    public function get_link(){ return '';} // link to external support system
    public function get_message_sidebar_data($product_data, $item_data){

        // find out the user details, purchases and if they have any other open messages.
        $user_hints = array(
            'shub_user_id' => array()
        );
        // this seeds the linked shub_user_ids before passing it to the bigger method that hunts down other possible linked user ids.
        $user_hints = $this->get_user_hints($user_hints);
        $data = SupportHub::getInstance()->get_message_user_summary($user_hints, $this->network, $this);

        if(!isset($data['message_details']))$data['message_details']=array();
        $data['message_details']['subject'] = array(
            'Subject',
            '<a href="'.$this->get_link().'" target="_blank">'.htmlspecialchars( $this->get('title') ).'</a>'
        );
        $data['message_details']['account'] = array(
            'Account',
            htmlspecialchars( $this->get('account') ? $this->get('account')->get( 'account_name' ) : 'N/A' )
        );
        $data['message_details']['time'] = array(
            'Time',
            shub_print_date( $this->get('last_active'), true )
        );
        return $data;
    }
    public function get_user_hints($user_hints){
        $user_hints['shub_user_id'][] = $this->get('shub_user_id');
        /*
        $comments         = $this->get_comments();
        $first_comment = current($comments);
        if(isset($first_comment['shub_user_id']) && $first_comment['shub_user_id']){
            $user_hints['shub_user_id'][] = $first_comment['shub_user_id'];
        }
        $message_from = @json_decode($first_comment['message_from'],true);
        if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $bbpress_message->get('account')->get( 'bbpress_name' )){
            // this wont work if user changes their username, oh well.
            $other_users = new SupportHubUser_bbPress();
            $other_users->load_by_meta('bbpress_username',$message_from['username']);
            if($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'),$user_hints['shub_user_id'])){
                // pass these back to the calling method so we can get the correct values.
                $user_hints['shub_user_id'][] = $other_users->get('shub_user_id');
            }
        }*/
        return $user_hints;
    }
    public function reply_actions(){ echo '';}

    public function get_product_id(){
        // if local product is id -1 (default) then we use the parent forum product id
        // this allows individual products to be overrideen with new one
        if($this->get('shub_product_id') > 0){
            return $this->get('shub_product_id');
        }else if($this->item){
            return $this->item->get('shub_product_id');
        }
        return false;
    }

    public function save_product_id($new_product_id){
        if($this->item && $new_product_id && $new_product_id == $this->item->get('shub_product_id')){
            // setting it back to default.
            $this->update('shub_product_id', -1);
        }else{
            $this->update('shub_product_id', $new_product_id);
        }
    }



    public function output_message_page($type='inline'){
        $message_id = $this->get('shub_message_id');
        if($message_id && $this->get('shub_account_id')){

            if('popup' == $type){
                $this->mark_as_read();
            }


            // icon for this account.
            $icons = SupportHub::getInstance()->message_managers[$this->network]->get_friendly_icon('shub_message_account_icon');
            // icon for the linked product
            $shub_product_id = $this->get_product_id();
            $product_data = array();
            $item_data = array();
            $bbpress_item = $this->get('item');
            if(!$shub_product_id && $bbpress_item){
                $shub_product_id = $bbpress_item->get('shub_product_id');
                $item_data = $bbpress_item->get('item_data');
                if(!is_array($item_data))$item_data = array();
            }
            if($shub_product_id) {
                $shub_product = new SupportHubProduct();
                $shub_product->load( $shub_product_id );
                $product_data = $shub_product->get( 'product_data' );
            }
            if($shub_product_id && !empty($product_data['image'])) {
                if(!empty($product_data['url'])){
                    $icons .=  '<a href="'.esc_attr($product_data['url']).'" target="_blank">';
                }
                $icons .= '<img src="'. esc_attr($product_data['image']).'" class="shub_message_account_icon">';
                if(!empty($product_data['url'])){
                    $icons .=  '</a>';
                }
            }

            ?>

            <div class="message_edit_form" data-network="<?php echo $this->network;?>">
                <section class="message_sidebar">
                    <nav>
                        <?php if($this->get('shub_status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button shub_button_loading"
                               data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_message_id' => $message_id,
                                'last_activity' => $this->get('last_active'),
                            )));?>"><?php _e( 'Inbox' ); ?></a>
                        <?php }else{ ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button shub_button_loading"
                               data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_message_id' => $message_id,
                                'last_activity' => $this->get('last_active'),
                            )));?>"><?php _e( 'Archive' ); ?></a>
                        <?php } ?>
                        <span class="responsive_sidebar_summary">
                        <?php echo $icons;
                        // todo - what other details need to show in mobile view?
                        ?>
                        </span>

                        <a href="#" class="shub_view_full_message_sidebar btn btn-default btn-xs button alignright"><?php _e( 'More Details' ); ?></a>
                    </nav>
                    <header>
                        <a href="<?php echo $this->get_link(); ?>" class="social_view_external btn btn-default btn-xs button" target="_blank"><?php _e( 'View Comment' ); ?></a>
                        <?php if($this->get('shub_status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button shub_button_loading"
                               data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_message_id' => $message_id,
                                'last_activity' => $this->get('last_active'),
                            )));?>"><?php _e( 'Inbox' ); ?></a>
                        <?php }else{ ?>
                            <a href="#" class="shub_message_action btn btn-default btn-xs button shub_button_loading"
                               data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                                'network' => $this->network,
                                'shub_message_id' => $message_id,
                                'last_activity' => $this->get('last_active'),
                            )));?>"><?php _e( 'Archive' ); ?></a>
                        <?php } ?>
                    </header>
                    <aside class="message_sidebar">
                        <?php
                        echo $icons;
                        echo '<br/>';

                        // this method does all the magic of getting the linked user ids and other messages
                        $data = $this->get_message_sidebar_data($product_data, $item_data);

                        if(!empty($data['message_details'])) {
                            ?>
                            <div class="message_sidebar_details">
                            <?php
                            foreach ($data['message_details'] as $message_details) {
                                ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($message_details[0]); ?>:</strong>
                                    <?php echo $message_details[1]; ?>
                                </div>
                                <?php
                            }
                            ?>
                            </div>
                            <?php
                        }
                        if(!empty($data['extra_datas'])) {
	                        $extras = SupportHubExtra::get_all_extras();
                            ?>
                            <div class="message_sidebar_extra_data">
                            <?php
                            foreach ($data['extra_datas'] as $extra_data) {
                                if (isset($extras[$extra_data->get('shub_extra_id')])) {
                                    ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($extras[$extra_data->get('shub_extra_id')]->get('extra_name')); ?>:</strong>
                                        <?php
                                        switch ($extras[$extra_data->get('shub_extra_id')]->get('field_type')) {
                                            case 'encrypted':
                                                echo '(encrypted)';
                                                break;
                                            default:
                                                echo shub_forum_text($extra_data->get('extra_value'), false);
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                            </div>
                            <?php
                        }
                        if(!empty($data['user_bits'])) {
                            ?>
                            <ul class="linked_user_details"> <?php
                                foreach ($data['user_bits'] as $user_bit) {
                                    ?>
                                    <li><strong><?php echo $user_bit[0]; ?>:</strong> <?php echo $user_bit[1]; ?></li>
                                    <?php
                                }
                                ?>
                            </ul>
                            <?php
                        }
                        if(!empty($data['other_messages'])) {
                            ?>
                            <div class="shub_other_messages">
                                <strong><?php echo sprintf(_n('%d Other Message:', '%d Other Messages:', count($data['other_messages']), 'support_hub'), count($data['other_messages'])); ?></strong><br/>
                                <ul>
                                    <?php
                                    foreach ($data['other_messages'] as $other_message) {
                                        ?>
                                        <li>
                                            <span class="other_message_time"><?php echo shub_pretty_date($other_message['time']); ?></span>
                                            <span class="other_message_status"><?php
                                                if (isset($other_message['message_status'])) {
                                                    switch ($other_message['message_status']) {
                                                        case _shub_MESSAGE_STATUS_ANSWERED:
                                                            echo '<span class="message_status_archived">Archived</span>';
                                                            break;
                                                        case _shub_MESSAGE_STATUS_UNANSWERED:
                                                            echo '<span class="message_status_inbox">Inbox</span>';
                                                            break;
                                                        case _shub_MESSAGE_STATUS_HIDDEN:
                                                            echo '<span class="message_status_hidden">Hidden</span>';
                                                            break;
                                                        default:
                                                            echo 'UNKNOWN?';
                                                    }
                                                }
                                                ?>
                                            </span>
                                            <span class="other_message_network">
                                                <?php echo $other_message['icon']; ?>
                                            </span>
                                            <br/>
                                            <a href="<?php echo esc_attr($other_message['link']); ?>" target="_blank" class="shub_modal"
                                               data-network="<?php echo esc_attr($other_message['network']); ?>"
                                               data-message_id="<?php echo (int)$other_message['message_id']; ?>"
                                               data-message_comment_id="<?php echo isset($other_message['message_comment_id']) ? (int)$other_message['message_comment_id'] : ''; ?>"
                                               data-modaltitle="<?php echo esc_attr($other_message['summary']); ?>"><?php echo esc_html($other_message['summary']); ?></a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                                </div>
                            <?php
                        }

                        do_action('supporthub_message_header', $this->network, $this);
                        ?>

                    </aside>
                </section>
                <section class="message_content">
                    <?php
                    // we display the first "primary" message (from the ucm_message table) followed by comments from the ucm_message_comment table.
                    //$this->full_message_output(true);
                    $this->output_message_list();
                    ?>
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


    public function output_message_list( $allow_reply = true ){
        $message_id = $this->get('shub_message_id');
        $comments         = $this->get_comments();
        $x=0;
        foreach($comments as $comment){
            $x++;
            $from_user = $this->get_user($comment['shub_user_id']);
            $time = isset($comment['time']) ? $comment['time'] : false;
            // is this a queued-to-send message?
            $extra_class = '';
            $comment_status = '';
            $message_error = false;
            if(!empty($comment['shub_outbox_id'])){
                $shub_outbox = new SupportHubOutbox($comment['shub_outbox_id']);
                if($shub_outbox->get('shub_outbox_id') != $comment['shub_outbox_id']){
                    // the outbox entry has been removed but this comment still references it
                    // todo: update this comment entry to not contain an shub_outbox_id
                }else {
                    switch ($shub_outbox->get('shub_status')) {
                        case _SHUB_OUTBOX_STATUS_QUEUED:
                        case _SHUB_OUTBOX_STATUS_SENDING:
                            $extra_class .= ' outbox_queued';
                            $comment_status = 'Currently Sending....' . $shub_outbox->get('shub_status');
                            break;
                        case _SHUB_OUTBOX_STATUS_FAILED:
                            $extra_class .= ' outbox_failed';
                            $comment_status = 'Failed to send message! Please check logs.';
                            $message_error = true;
                            break;
                    }
                }
            }
            if(!empty($comment['private'])){
                $extra_class .= ' shub_message_private';
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
                        <span class="buyer_status_badges">
                            <?php
                            // work out of this buyer has bought something via the envato module.
                            // first we have to find out what item this is:
                            //echo "user:".$comment['shub_user_id']."<br>product:".$this->get_product_id()."<br>";
                            // todo: store these as a flag in the message database so we can run stats on them and display a graph on the dashboard.
                            $buyer_status = $this->get_buyer_status($comment['shub_user_id']);
                            if(!empty($buyer_status['purchased'])){
                                echo '<span class="buyer_badge purchased">Purchased</span> ';
                            }
                            if(!empty($buyer_status['supported'])){
                                echo '<span class="buyer_badge supported">Supported</span> ';
                            }
                            if(!empty($buyer_status['unsupported'])){
                                echo '<span class="buyer_badge unsupported">Unsupported</span> ';
                            }
                            if(!empty($buyer_status['presale'])){
                                //echo '<span class="buyer_badge presale">Pre-sale</span> ';
                            }
                            // todo - add a badge for staff reply.
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
                    <?php if($message_error && !empty($comment['shub_outbox_id'])){ ?>
                        <button data-post="<?php echo esc_attr(json_encode(array(
                            'action' => "support_hub_resend_outbox_message",
                            'shub_outbox_id' => $comment['shub_outbox_id'],
                        ))); ?>" class="btn button shub_message_action_button"><?php _e('Re-Send'); ?></button>
                        <button data-post="<?php echo esc_attr(json_encode(array(
                            'action' => "support_hub_delete_outbox_message",
                            'shub_outbox_id' => $comment['shub_outbox_id'],
                        ))); ?>" class="btn button shub_message_action_button"><?php _e('Delete Message'); ?></button>
                    <?php } ?>
                </div>
            </div>
        <?php }
        if($allow_reply) { ?>
            <div class="shub_message shub_message_reply shub_message_reply_box">
                <?php
                $reply_shub_user = $this->get_reply_user();
                ?>
                <div class="shub_message_picture">
                      <img src="<?php echo $reply_shub_user->get_image(); ?>" />
                </div>
                <div class="shub_message_header">
                     <?php echo $reply_shub_user->get_full_link(); ?>
                </div>
                <div class="shub_message_body">
                    <textarea placeholder="Write a reply..."></textarea>

                    <div class="shub_message_buttons">
                        <a href="#" class="shub_request_extra btn btn-default btn-xs button"
                           data-modaltitle="<?php _e('Request Extra Details'); ?>" data-action="request_extra_details"
                           data-network="<?php echo $this->network; ?>"
                           data-<?php echo $this->network; ?>-message-id="<?php echo $message_id; ?>"><?php _e('Request Details'); ?></a>
                        <!-- <a href="#" class="shub_template_button btn btn-default btn-xs button"
                           data-modaltitle="<?php _e('Send Template Message'); ?>" data-action="send_template_message"
                           data-network="<?php echo $this->network; ?>"
                           data-<?php echo $this->network; ?>-message-id="<?php echo $message_id; ?>"><?php _e('Template'); ?></a> -->

                        <button data-post="<?php echo esc_attr(json_encode(array(
                            'account-id' => $this->get('shub_account_id'),
                            'message-id' => $message_id,
                            'network' => $this->network,
                            'last_activity' => $this->get('last_active'),
                        ))); ?>" class="btn button shub_send_message_reply_button shub_hide_when_no_message shub_button_loading"><?php _e('Send'); ?></button>
                    </div
                </div>
                <div class="shub_message_actions shub_hide_when_no_message">
                    <div>
                        <label
                            for="message_reply_archive_<?php echo $message_id; ?>"><?php _e('Archive After Reply', 'shub'); ?></label>
                        <input id="message_reply_archive_<?php echo $message_id; ?>" type="checkbox" name="archive"
                               data-reply="yes" value="1" checked>
                    </div>
                    <div>
                        <label
                            for="message_reply_private_<?php echo $message_id; ?>"><?php _e('Mark As Private', 'shub'); ?></label>
                        <input id="message_reply_private_<?php echo $message_id; ?>" type="checkbox" name="private"
                               data-reply="yes" value="1">
                    </div>
                    <div>
                        <label
                            for="message_reply_notify_email_<?php echo $message_id; ?>"><?php _e('Notify Via Email', 'shub'); ?></label>
                        <input id="message_reply_notify_email_<?php echo $message_id; ?>" type="checkbox" name="notify_email"
                               data-reply="yes" value="1">
                    </div>
                    <div>
                        <label
                            for="message_reply_debug_<?php echo $message_id; ?>"><?php _e('Enable Debug Mode', 'shub'); ?></label>
                        <input id="message_reply_debug_<?php echo $message_id; ?>" type="checkbox" name="debug"
                               data-reply="yes" value="1">
                    </div>
                    <?php $this->reply_actions(); ?>
                </div>
            </div>
            <?php
        }
    }

    public function get_buyer_status($shub_user_id){
        $return = array();
        if($shub_product_id = $this->get_product_id()){
            // thid is a duplicate of the code in class.shub_envato.php to determine if a user has purchased a product
            // todo: try to make them both use the same cached data.
            $purchases = shub_get_multiple('shub_envato_purchase',array('shub_user_id'=>$shub_user_id,'shub_product_id'=>$shub_product_id));
            foreach($purchases as $purchase){
                if($purchase['shub_product_id']){
                    $purchase_product = new SupportHubProduct($purchase['shub_product_id']);
                    $data = $purchase_product->get('product_data');
                    if(!empty($data['envato_item_data']['item'])){
                        $return['purchased'] = true;
                        $support = shub_get_single('shub_envato_support','shub_envato_purchase_id',$purchase['shub_envato_purchase_id']);
                        if($support && !empty($support['end_time']) && $support['end_time'] <= time()){
                            // WHOPPS. I got this wrong in the DB initially. Hack to double check purchase happened before new support terms
                            if(strtotime($purchase['purchase_time']) < strtotime("2015-09-01")){
                                $support['end_time'] = strtotime("+6 months", strtotime("2015-09-01"));
                            }
                        }
                        if($support && !empty($support['end_time']) && $support['end_time'] > time()){
                            $return['supported'] = true;
                        }
                    }
                }
            }
            if(empty($return['purchased'])){
                $return['presale'] = true;
            }
            if(empty($return['supported']) && empty($return['presale'])){
                $return['unsupported'] = true;
            }
        }else{
            $return['unknown'] = true;
        }
        return $return;
    }

	public function get_network(){
		return $this->network;
	}

}