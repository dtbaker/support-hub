<?php



if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SupportHub_Account_Data_List_Table extends WP_List_Table {

	public $action_key = 'ID';
	public $table_data = array();
	public $columns = array();
	public $found_data = array();

	public $items_per_page = 20;

	public $pagination_has_more = false;

	function __construct($args = array()) {
		global $status, $page;

		$args = wp_parse_args( $args, array(
			'plural' => __( 'accounts', 'support_hub' ),
			'singular' => __( 'account', 'support_hub' ),
			'ajax' => false,
		) );

		parent::__construct( $args );

		$this->set_columns( array(
			'account' => __( 'Account', 'support_hub' ),
			'last_checked'    => __( 'Last Checked', 'support_hub' ),
		) );

	}

	function no_items() {
		_e( 'Nothing found.' );
	}

	function column_default( $item, $column_name ) {
		if($this->row_callback !== false){
			$res = call_user_func($this->row_callback, $item, $column_name);
			if($res){
				return $res;
			}
		}
		return isset($item[ $column_name ]) ? $item[ $column_name ] : 'N/A';
	}


	function set_data($data){
		$this->items = $data;
	}
	private $row_callback = false;
	function set_callback($function){
		$this->row_callback = $function;
	}
	function set_columns($columns){
		$this->columns = $columns;
	}
	function get_columns() {
		return $this->columns;
	}

	function column_account( $item ) {
		if(isset($item['edit_link'])){
			$actions = array(
				'edit'   => '<a href="'.$item['edit_link'].'">'.__('Edit','support_hub').'</a>',
				//'delete' => sprintf( '<a href="?page=%s&action=%s&book=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['ID'] ),
			);
			return sprintf( '%1$s %2$s', $item['title'], $this->row_actions( $actions ) );
		}/*else {
			$actions = array(
				'edit' => sprintf( '<a href="?page=%s&' . $this->action_key . '=%s">'.__('Edit','support_hub').'</a>', htmlspecialchars( $_REQUEST['page'] ), $item[ $this->action_key ] ),
				//'delete' => sprintf( '<a href="?page=%s&action=%s&book=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['ID'] ),
			);
		}*/


	}

	function set_bulk_actions($actions) {
		$this->bulk_actions = $actions;
	}
	function get_bulk_actions() {
		return isset($this->bulk_actions) ? $this->bulk_actions : array();
	}


	function prepare_items() {

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array(); //
		$this->_column_headers = array( $columns, $hidden, $sortable );
		//usort( $this->example_data, array( $this, 'usort_reorder' ) );

		$current_page = $this->get_pagenum();

		$total_items  = count( $this->items );

		// only ncessary because we have sample data
		$this->found_data = array_slice( $this->items, ( ( $current_page - 1 ) * $this->items_per_page ), $this->items_per_page );
        if(!$this->found_data)$this->found_data = $this->items; // hack to stop the page overflow bug

		$this->set_pagination_args( array(
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $this->items_per_page //WE have to determine how many items to show on a page
		) );
		$this->items = $this->found_data;

	}


} //class






class SupportHubMessageList extends SupportHub_Account_Data_List_Table{
    private $row_output = array();

	public $available_networks = array();

	function __construct($args = array()) {
		$args = wp_parse_args( $args, array(
			'plural'   => __( 'messages', 'support_hub' ),
			'singular' => __( 'message', 'support_hub' ),
			'ajax'     => false,
		) );
		parent::__construct( $args );

		$this->available_networks = SupportHub::getInstance()->message_managers;
	}

	function column_cb( $item ) {
		foreach($this->available_networks as $network => $mm){
			if(isset($item['shub_'.$network.'_message_id'])){
			    return sprintf(
				    '<input type="checkbox" name="shub_message['.$network.'][]" value="%s" />', $item['shub_'.$network.'_message_id']
			    );
			}
		}
	    return '';
	}
	public function get_bulk_actions(){
		return array(
	        'archive'    => __('Archive'),
	        'un-archive'  => __('Move to Inbox')
	    );
	}
	public function process_bulk_action() {
		$action = $this->current_action();
		$change_count = 0;
		if($action){
	        // security check!
	        if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['_wpnonce'] ) ) {
	            $nonce  = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
	            if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) )
	                wp_die( 'Nope! Security check failed!' );
	        }
	        switch ( $action ) {
	            case 'archive':
					$messages = isset($_POST['shub_message']) && is_array($_POST['shub_message']) ? $_POST['shub_message'] : array();
                    $shub = SupportHub::getInstance();
					foreach($messages as $network => $network_message_ids){
                        if(isset($shub->message_managers[$network])){
                            foreach($network_message_ids as $network_message_id){
                                $network_message = $shub->message_managers[$network]->get_message(false, false, $network_message_id);
                                if($network_message && $network_message->get('shub_'.$network.'_message_id') == $network_message_id){
                                    $network_message->update('status',_shub_MESSAGE_STATUS_ANSWERED);
                                    $change_count++;
                                }
                            }
                        }
                    }
	                break;
	            case 'un-archive':
					$messages = isset($_POST['shub_message']) && is_array($_POST['shub_message']) ? $_POST['shub_message'] : array();
                    $shub = SupportHub::getInstance();
                    foreach($messages as $network => $network_message_ids){
                        if(isset($shub->message_managers[$network])){
                            foreach($network_message_ids as $network_message_id){
                                $network_message = $shub->message_managers[$network]->get_message(false, false, $network_message_id);
                                if($network_message && $network_message->get('shub_'.$network.'_message_id') == $network_message_id){
                                    $network_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
                                    $change_count++;
                                }
                            }
                        }
                    }
	                break;
	            default:
	                return $change_count;
	                break;
	        }
		}
        return $change_count;
    }

	public $row_count = 0;
    function column_default($item, $column_name){

	    foreach($this->available_networks as $network => $mm){
			if(isset($item['shub_'.$network.'_message_id'])){
				// pass this row rendering off to the facebook plugin
			    // todo - don't hack the <td> outputfrom the existing plugin, move that back into this table class
			    if(!isset($this->row_output[$network][$item['shub_'.$network.'_message_id']])){
				    if(!isset($this->row_output[$network]))$this->row_output[$network] = array();
				    $this->row_output[$network][$item['shub_'.$network.'_message_id']] = $item['message_manager']->output_row($item);
				    $this->row_output[$network][$item['shub_'.$network.'_message_id']]['row_class'] = $this->row_count++%2 ? 'alternate' : '';
			    }
			    if(isset($this->row_output[$network][$item['shub_'.$network.'_message_id']][$column_name])){
				    return $this->row_output[$network][$item['shub_'.$network.'_message_id']][$column_name];
			    }
			}
		}
	    return false;
    }


	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args ) ) {
			return;
		}

		$total_items = $this->_pagination_args['total_items'];
		$total_pages = $this->_pagination_args['total_pages'];
		$infinite_scroll = false;
		if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
			$infinite_scroll = $this->_pagination_args['infinite_scroll'];
		}

		$output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s%s items', $total_items ), number_format_i18n( $total_items ), $this->pagination_has_more ? '+' : '' ) . '</span>';

		$current = $this->get_pagenum();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

		$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

		// add any search params to the url.
		if(isset($_REQUEST['search']) && is_array($_REQUEST['search'])){
			foreach($_REQUEST['search'] as $key=>$val){
				if($val){
					$current_url = remove_query_arg('search['.$key.']', $current_url);
					$current_url = add_query_arg('search['.$key.']', $val, $current_url);
				}
			}
		}
		$page_links = array();

		$disable_first = $disable_last = '';
		if ( $current == 1 ) {
			$disable_first = ' disabled';
		}
		if ( $current == $total_pages ) {
			$disable_last = ' disabled';
		}
		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s' data-paged='%s'>%s</a>",
			'shub_page_links first-page' . $disable_first,
			esc_attr__( 'Go to the first page' ),
			esc_url( remove_query_arg( 'paged', $current_url ) ),
			'',
			'&laquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s' data-paged='%s'>%s</a>",
			'shub_page_links prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page' ),
			esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
			max( 1, $current-1 ),
			'&lsaquo;'
		);

		if ( true || 'bottom' == $which ) {
			// no page input button, it messes with our shub page form post.
			$html_current_page = $current;
		} else {
			$html_current_page = sprintf( "%s<input class='current-page' id='current-page-selector' title='%s' type='text' name='paged' value='%s' size='%d' />",
				'<label for="current-page-selector" class="screen-reader-text">' . __( 'Select Page' ) . '</label>',
				esc_attr__( 'Current page' ),
				$current,
				strlen( $total_pages )
			);
		}
		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $this->pagination_has_more ? 'many' : $html_total_pages ) . '</span>';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s' data-paged='%s'>%s</a>",
			'shub_page_links next-page' . $disable_last,
			esc_attr__( 'Go to the next page' ),
			esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
			min( $total_pages, $current+1 ),
			'&rsaquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s' data-paged='%s'>%s</a>",
			'shub_page_links last-page' . $disable_last,
			esc_attr__( 'Go to the last page' ),
			esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
			$total_pages,
			'&raquo;'
		);

		$pagination_links_class = 'pagination-links';
		if ( ! empty( $infinite_scroll ) ) {
			$pagination_links_class = ' hide-if-js';
		}
		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}
		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;
		?>
		<script type="text/javascript">
			jQuery(function(){
				jQuery('.shub_page_links').click(function(){
					var $form = jQuery(this).parents('form').first();
					$form.find('[name="paged"]').val(jQuery(this).data('paged'));
					$form[0].submit();
					return false;
				});
			});
		</script>
		<?php
	}

    public function single_row( $item ) {
        echo '<tr';
        if(is_array($item) && isset($item['message_manager']) && $item['message_manager']->id){
            echo ' class="shub_network_message"';
            echo ' data-network="'.$item['message_manager']->id.'"';
            echo ' data-network-message-id="'.$item['shub_' . $item['message_manager']->id . '_message_id'].'"';
        }
        echo '>';
        $this->single_row_columns( $item );
        echo '</tr>';
    }
}


class SupportHubSentList extends SupportHub_Account_Data_List_Table{
    private $row_output = array();

	function __construct($args = array()) {
		$args = wp_parse_args( $args, array(
			'plural'   => __( 'sent_messages', 'support_hub' ),
			'singular' => __( 'sent_message', 'support_hub' ),
			'ajax'     => false,
		) );
		parent::__construct( $args );
	}



	private $message_managers = array();
	function set_message_managers($message_managers){
		$this->message_managers = $message_managers;
	}

	private $column_details = array();
    function column_default($item, $column_name){

	    if(!$item['shub_message_id'])return 'DBERR';
	    if(!isset($this->column_details[$item['shub_message_id']])){
		    $this->column_details[$item['shub_message_id']] = array();
	    }
	    // pass this off to our media managers and work out which social accounts sent this message.
		foreach($this->message_managers as $type => $message_manager){
			if(!isset($this->column_details[$item['shub_message_id']][$type])) {
				$this->column_details[ $item['shub_message_id'] ][ $type ] = $message_manager->get_message_details( $item['shub_message_id'] );
			}
		}

	    switch($column_name){
		    case 'shub_column_time':
				$column_data = '';
				foreach($this->column_details[ $item['shub_message_id'] ] as $message_type => $data){
					if(isset($data['message']) && $data['message']->get('status') == _shub_MESSAGE_STATUS_PENDINGSEND){
						$time = $data['message']->get('last_active');
						if(!$time)$time = $data['message']->get('message_time');
						$now = current_time('timestamp');
						if($time <= $now){
							return __('Pending Now');
						}else{
							$init = $time - $now;
							$hours = floor($init / 3600);
							$minutes = floor(($init / 60) % 60);
							$seconds = $init % 60;
							return sprintf(__('Pending %s hours, %s minutes, %s seconds','support_hub'),$hours, $minutes, $seconds);
						}

					}
				}
				$column_data = shub_print_date($item['sent_time'],true);
				return $column_data;
			    break;
		    case 'shub_column_action':
			    return '<a href="#" class="button">'. __( 'Open','support_hub' ).'</a>';
			    break;
		    case 'shub_column_post':
			    if($item['post_id']){
				    $post = get_post( $item['post_id'] );
				    if(!$post){
					    return 'N/A';
				    }else{
					    return '<a href="'.get_permalink($post->ID).'">' . htmlspecialchars($post->post_title).'</a>';
				    }
			    }else{
				    return __('No Post','support_hub');
			    }
			    break;
		    case 'shub_column_account':
		    default:
				$column_data = '';
				foreach($this->column_details[ $item['shub_message_id'] ] as $message_type => $data){
					if(isset($data[$column_name]))$column_data .= $data[$column_name];
				}
				return $column_data;
			    break;

	    }
    }
}

class SupportHubLogList extends SupportHub_Account_Data_List_Table{
	private $row_output = array();

	function __construct($args = array()) {
		$args = wp_parse_args( $args, array(
			'plural'   => __( 'extra_details', 'support_hub' ),
			'singular' => __( 'extra_detail', 'support_hub' ),
			'ajax'     => false,
		) );
		parent::__construct( $args );
	}


	private $message_managers = array();
	function set_message_managers($message_managers){
		$this->message_managers = $message_managers;
	}

	public function single_row( $item ) {
		echo '<tr class="' . (isset($item['log_error_level']) && $item['log_error_level'] > 0 ? 'log_error' : 'log_normal').'">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	private $column_details = array();
	function column_default($item, $column_name){

		if(is_object($item)){
			return $item->get($column_name);
		}else if( is_array($item) && isset($item[$column_name])){
			switch($column_name){
				case 'log_data':
					$data = maybe_unserialize($item[$column_name]);
					if(is_array($data)){
                        echo '<pre>';
                        echo htmlspecialchars(var_export($data,true));
                        echo '</pre>';
                        return false;
                    }else{
                        return $data;
                    }
					break;
				case 'log_time':
					return shub_print_date($item[$column_name],true);
					break;
			}
			return $item[$column_name];
		}else{
			return 'No';
		}
	}
}