<?php

class SupportHub_extension{

	public $id;
	public $friendly_name;
	public $desc;

    public $accounts = array();

	public function __construct( $id = false ) {
		$this->reset();
        if()
	}
    private function reset() {
        $this->accounts = array();
    }

	public function init(){

        if(isset($_GET[_SUPPORT_HUB_LINK_REWRITE_PREFIX]) && strlen($_GET[_SUPPORT_HUB_LINK_REWRITE_PREFIX]) > 0){
            // check hash
            $bits = explode(':',$_GET[_SUPPORT_HUB_LINK_REWRITE_PREFIX]);
            if(defined('AUTH_KEY') && isset($bits[1])){
                $shub_message_link_id = (int)$bits[0];
                if($shub_message_link_id > 0){
                    $correct_hash = substr(md5(AUTH_KEY.' shub extension link '.$shub_message_link_id),1,5);
                    if($correct_hash == $bits[1]){
                        // link worked! log a visit and redirect.
                        $link = shub_get_single('shub_message_link','shub_message_link_id',$shub_message_link_id);
                        if($link){
                            if(!preg_match('#^http#',$link['link'])){
                                $link['link'] = 'http://'.trim($link['link']);
                            }
                            shub_update_insert('shub_message_link_click_id',false,'shub_message_link_click',array(
                                'shub_message_link_id' => $shub_message_link_id,
                                'click_time' => time(),
                                'ip_address' => $_SERVER['REMOTE_ADDR'],
                                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                                'url_referrer' => $_SERVER['HTTP_REFERER'],
                            ));
                            header("Location: ".$link['link']);
                            exit;
                        }
                    }
                }
            }
        }
    }

    public function get_unread_count($search=array()){
        if(!get_current_user_id())return 0;
        $sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_message` m ";
        $sql .= " WHERE 1 ";
        $sql .= " AND m.shub_message_id NOT IN (SELECT mr.shub_message_id FROM `"._support_hub_DB_PREFIX."shub_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_message_id = m.shub_message_id)";
        $sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
        $res = shub_qa1($sql);
        return $res ? $res['unread'] : 0;
    }

    
    public function init_menu(){}
	public function page_assets(){}
	public function settings_page(){}
	public function is_enabled(){
		return get_option('shub_manager_enabled_'.$this->id,0);
	}
	public function get_message_details($message_id){return array();}
	public function get_install_sql(){return '';}


    public function get_friendly_icon(){
        return '<img src="'.plugins_url('extensions/'.$this->id.'/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="shub_friendly_icon">';
    }


    public function get_accounts() {
        $this->accounts = shub_get_multiple( 'shub_account', array(
            'shub_extension' => $this->id,
        ), 'shub_account_id' );
        return $this->accounts;
    }

	public $all_messages = array();
	public $limit_start = 0;
	public $search_params = array();
	public $search_order = array();
	public $search_limit = 0;

    public function load_all_messages($search=array(),$order=array(),$limit_batch=0){
        $this->search_params = $search;
        $this->search_order = $order;
        $this->search_limit = $limit_batch;

        $sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_message` m ";
        $sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_message_read` mr ON ( m.shub_message_id = mr.shub_message_id AND mr.user_id = ".get_current_user_id()." )";
        //$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_item` ei ON ( m.shub_item_id = ei.shub_item_id )";
        $sql .= " WHERE 1 ";
        if(isset($search['status']) && $search['status'] !== false){
            $sql .= " AND `status` = ".(int)$search['status'];
        }
        if(isset($search['shub_product_id']) && (int)$search['shub_product_id']){
            $sql .= " AND `shub_product_id` = ".(int)$search['shub_product_id'];
        }
        if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
            $sql .= " AND m.`shub_message_id` = ".(int)$search['shub_message_id'];
        }
        if(isset($search['shub_account_id']) && $search['shub_account_id'] !== false){
            $sql .= " AND m.`shub_account_id` = ".(int)$search['shub_account_id'];
        }
        if(isset($search['generic']) && !empty($search['generic'])){
            // todo: search item comments too.. not just title (first comment) and summary (last comment)
            $sql .= " AND (`title` LIKE '%".esc_sql($search['generic'])."%'";
            $sql .= " OR `summary` LIKE '%".esc_sql($search['generic'])."%' )";
        }
        if(empty($order)){
            $sql .= " ORDER BY `last_active` ASC ";
        }else{
            switch($order['orderby']){
                case 'shub_column_time':
                    $sql .= " ORDER BY `last_active` ";
                    $sql .= $order['order'] == 'asc' ? 'ASC' : 'DESC';
                    break;
            }
        }
        if($limit_batch){
            $sql .= " LIMIT ".$this->limit_start.', '.$limit_batch;
            $this->limit_start += $limit_batch;
        }
        global $wpdb;
        $this->all_messages = $wpdb->get_results($sql, ARRAY_A);
        return $this->all_messages;
    }

	public $get_next_message_failed = false;
	public function get_next_message(){
		if(empty($this->all_messages) && !$this->get_next_message_failed){
			// seed the next batch of messages.
			$this->load_all_messages($this->search_params, $this->search_order, $this->search_limit);
			if(empty($this->all_messages)){
				// seed failed, we're completely out of messages from this one.
				// mark is as failed so we don't keep hitting sql
				$this->get_next_message_failed = true;
			}
		}
		return !empty($this->all_messages) ? array_shift($this->all_messages) : false;
		/*if(mysql_num_rows($this->all_messages)){
			return mysql_fetch_assoc($this->all_messages);
		}
		return false;*/
	}

    /**
     * @param $message - an array holding a result from the shub_message row
     * @param array $existing_rows - optional array passed in from the individual extension already having data for this row.
     * @return array
     */
    public function output_row($message, $existing_rows=array()){
        $extension_message = $this->get_message(false, false, $message['shub_message_id']);
        $messages         = $extension_message->get_comments();
        $return = array();

        ob_start();
        echo $this->get_friendly_icon();
        ?>
        <a href="<?php echo $extension_message->get_link(); ?>"
           target="_blank"><?php
            echo htmlspecialchars( $extension_message->get('account') ? $extension_message->get('account')->get( 'account_name' ) : 'Item' ); ?></a> <br/>
        <?php echo htmlspecialchars( $extension_message->get_type_pretty() ); ?>
        <?php
        $return['shub_column_account'] = ob_get_clean();

        ob_start();
        $shub_product_id = $extension_message->get('shub_product_id');
        if($shub_product_id) {
            $shub_product = new SupportHubProduct();
            $shub_product->load($shub_product_id);
            $product_data = $shub_product->get('product_data');
            if(!empty($product_data['image'])){
                ?>
                <img src="<?php echo esc_attr($product_data['image']);?>" class="icon">
            <?php } ?>
            <?php if(!empty($product_data['url'])){ ?>
                <a href="<?php echo esc_url($product_data['url']); ?>" target="_blank"><?php echo htmlspecialchars( $shub_product->get('product_name') ); ?></a>
                <?php
            }else{
                ?> <?php echo htmlspecialchars( $shub_product->get('product_name') ); ?> <?php
            }
        }
        $return['shub_column_product'] = ob_get_clean();

        $return['shub_column_time'] = '<span class="shub_time" data-time="'.esc_attr($message['message_time']).'" data-date="'.esc_attr(shub_print_date( $message['message_time'], true )).'">'.shub_pretty_date( $message['message_time']).'</span>';

        ob_start();
        // work out who this is from.
        $from = $extension_message->get_from();
        ?>
        <div class="shub_from_holder">
            <div class="shub_from_full">
                <?php
                foreach($from as $id => $from_data){
                    ?>
                    <div>
                        <a href="<?php echo esc_url($from_data['link']);?>" target="_blank"><img src="<?php echo esc_attr($from_data['image']);?>" class="shub_from_picture"></a> <?php echo htmlspecialchars($from_data['name']); ?>
                    </div>
                    <?php
                } ?>
            </div>
            <?php
            reset($from);
            if(isset($from_data)) {
                echo '<a href="' . $from_data['link'] . '" target="_blank">' . '<img src="' . esc_attr($from_data['image']) . '" class="shub_from_picture"></a> ';
                echo '<span class="shub_from_count">';
                if ( count( $from ) > 1 ) {
                    echo '+' . ( count( $from ) - 1 );
                }
                echo '</span>';
            }
            ?>
        </div>
        <?php
        $return['shub_column_from'] = ob_get_clean();

        ob_start();
        ?>
        <span style="float:right;">
		    <?php echo count( $messages ) > 0 ? '('.count( $messages ).')' : ''; ?>
	    </span>
        <div class="shub_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
            // todo - pull in comments here, not just title/summary
            // todo - style customer and admin replies differently (eg <em> so we can easily see)
            $title = strip_tags($extension_message->get( 'title' ));
            $summary = strip_tags($extension_message->get( 'summary' ));
            echo htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title ) . ($summary!=$title ? '<br/>' .htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary ) : '');
            ?>
        </div>
        <?php
        $return['shub_column_summary'] = ob_get_clean();

        ob_start();
        ?>
        <a href="<?php echo $extension_message->link_open();?>" class="socialmessage_open shub_modal button" data-modaltitle="<?php echo esc_attr($title);?>" data-network="<?php echo esc_attr($this->id);?>" data-message_id="<?php echo (int)$extension_message->get('shub_message_id');?>"><?php _e( 'Open' );?></a>
        <?php if($extension_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
            <a href="#" class="socialmessage_action shub_message_action button"
               data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                'network' => $this->id,
                'shub_message_id' => $extension_message->get('shub_message_id'),
            )));;?>"><?php _e( 'Inbox' ); ?></a>
        <?php }else{ ?>
            <a href="#" class="socialmessage_action shub_message_action button"
               data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                'network' => $this->id,
                'shub_message_id' => $extension_message->get('shub_message_id'),
            )));?>"><?php _e( 'Archive' ); ?></a>
        <?php } ?>
        <?php
        $return['shub_column_action'] = ob_get_clean();

        return $return;
    }

	public function find_other_user_details($user_hints, $current_extension, $message_object){return array();}
	public function handle_process($process, $options = array()){
        switch($process){
            case 'save_account_details':
                $shub_account_id = isset($_REQUEST['shub_account_id']) ? (int)$_REQUEST['shub_account_id'] : 0;
                if(check_admin_referer( 'save-account'.$shub_account_id )) {
                    $account = new SupportHub_account( $shub_account_id );
                    if ( isset( $_POST['butt_delete'] ) ) {
                        $account->delete();
                        $redirect = 'admin.php?page=support_hub_settings&tab='.$account->extension_id;
                    } else {
                        $account->save_data( $_POST );
                        $shub_account_id = $account->get( 'shub_account_id' );
                        if ( isset( $_POST['butt_save_reconnect'] ) ) {
                            $redirect = $account->link_connect();
                        } else {
                            $redirect = $account->link_edit();
                        }
                    }
                    header( "Location: $redirect" );
                    exit;
                }

                break;
        }
    }

	public function extra_process_login($network, $account_id, $message_id, $extra_ids){ return false; }
	public function extra_save_data($extra, $value, $network, $account_id, $message_id){ return false; }
	public function extra_send_message($message, $network, $account_id, $message_id){ return false; }
    /**
     * @return SupportHub_message
     */
	public function get_message($account, $item, $message_id){ return false; }

    public function handle_ajax($action, $support_hub_wp){}
    public function init_js(){}




    public function get_url($url, $post_data = false){
        // get feed from fb:

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        if($post_data){
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
        }
        $data = curl_exec($ch);
        $feed = @json_decode($data,true);
        //print_r($feed);
        return $feed;

    }


}