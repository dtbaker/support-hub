<?php



if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SupportHub_Account_Data_List_Table extends WP_List_Table {

	public $action_key = 'ID';
	public $table_data = array();
	public $columns = array();
	public $_sortable_columns = array();
	public $found_data = array();

	public $items_per_page = 0;

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
		_e( 'No accounts found.' );
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
	function set_sortable_columns($columns){
		$this->_sortable_columns = $columns;
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
		$sortable              = $this->_sortable_columns; //
		$this->_column_headers = array( $columns, $hidden, $sortable );
		//usort( $this->example_data, array( $this, 'usort_reorder' ) );

		$current_page = $this->get_pagenum();

		$total_items  = count( $this->items );

		// only ncessary because we have sample data
        if($this->items_per_page) {
            $this->found_data = array_slice($this->items, (($current_page - 1) * $this->items_per_page), $this->items_per_page);
            if (!$this->found_data) $this->found_data = $this->items; // hack to stop the page overflow bug
            $this->set_pagination_args( array(
                'total_items' => $total_items, //WE have to calculate the total number of items
                'per_page'    => $this->items_per_page //WE have to determine how many items to show on a page
            ) );
        }else{
            $this->found_data = $this->items;
        }

		$this->items = $this->found_data;
        unset($this->found_data);

	}


} //class






class SupportHubMessageList extends SupportHub_Account_Data_List_Table{
    private $row_output = array();

	public $available_networks = array();

    public $layout_type = 'table';

	function __construct($args = array()) {
		$args = wp_parse_args( $args, array(
			'plural'   => __( 'messages', 'support_hub' ),
			'singular' => __( 'message', 'support_hub' ),
			'ajax'     => false,
		) );
		parent::__construct( $args );

		$this->available_networks = SupportHub::getInstance()->message_managers;
	}


    function no_items() {
        _e( 'No messages found.' );
    }

	function column_cb( $item ) {
        if(isset($item['shub_extension'])){
            return sprintf(
                '<input type="checkbox" name="shub_message['.esc_attr($item['shub_extension']).'][]" value="%s" />', $item['shub_message_id']
            );
        }
	    return '';
	}
	public function get_bulk_actions(){
        if($this->layout_type == 'table') {
            return array(
                'archive' => __('Archive'),
                'un-archive' => __('Move to Inbox'),
                'hide' => __('Hide')
            );
        }else{
            return array();
        }
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
					foreach($messages as $network => $message_ids){
                        if(isset($shub->message_managers[$network])){
                            foreach($message_ids as $message_id){
                                $network_message = $shub->message_managers[$network]->get_message(false, false, $message_id);
                                if($network_message && $network_message->get('shub_message_id') == $message_id){
                                    $network_message->update('shub_status',_shub_MESSAGE_STATUS_ANSWERED);
                                    $change_count++;
                                }
                            }
                        }
                    }
	                break;
	            case 'un-archive':
					$messages = isset($_POST['shub_message']) && is_array($_POST['shub_message']) ? $_POST['shub_message'] : array();
                    $shub = SupportHub::getInstance();
                    foreach($messages as $network => $message_ids){
                        if(isset($shub->message_managers[$network])){
                            foreach($message_ids as $message_id){
                                $network_message = $shub->message_managers[$network]->get_message(false, false, $message_id);
                                if($network_message && $network_message->get('shub_message_id') == $message_id){
                                    $network_message->update('shub_status',_shub_MESSAGE_STATUS_UNANSWERED);
                                    $change_count++;
                                }
                            }
                        }
                    }
	                break;
	            case 'hide':
					$messages = isset($_POST['shub_message']) && is_array($_POST['shub_message']) ? $_POST['shub_message'] : array();
                    $shub = SupportHub::getInstance();
                    foreach($messages as $network => $message_ids){
                        if(isset($shub->message_managers[$network])){
                            foreach($message_ids as $message_id){
                                $network_message = $shub->message_managers[$network]->get_message(false, false, $message_id);
                                if($network_message && $network_message->get('shub_message_id') == $message_id){
                                    $network_message->update('shub_status',_shub_MESSAGE_STATUS_HIDDEN);
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

        if(!empty($item['shub_extension']) && isset($this->available_networks[$item['shub_extension']])){
            $network = $item['shub_extension'];
            if(!isset($this->row_output[$network][$item['shub_message_id']])){
                if(!isset($this->row_output[$network]))$this->row_output[$network] = array();
                $this->row_output[$network][$item['shub_message_id']] = $this->available_networks[$item['shub_extension']]->output_row($item);
            }
            if(isset($this->row_output[$network][$item['shub_message_id']][$column_name])){
                return $this->row_output[$network][$item['shub_message_id']][$column_name];
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

    public function set_layout_type($layout_type){
        $this->layout_type = $layout_type;
    }
    public function extra_tablenav( $which ){
    }
    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav( 'top' );

        switch($this->layout_type){
            case 'continuous':
            case 'inline':
                ?>
                <div id="shub_table_inline">
                    <div id="shub_table_contents">
                    <?php if ( $this->has_items() ) {
                        $this->display_rows();
                    } else {
                        echo '<div class="no-items" style="text-align:center">';
                        $this->no_items();
                        echo '</div>';
                    }
                    ?>
                    </div>
                    <?php
                    if($this->layout_type == 'continuous'){
                        ?>
                        <div id="shub_continuous_more">
                            <a href="#" class="shub_message_load_content btn btn-default btn-xs button shub_button_loading"
                               data-action="next-continuous-message" data-target="#shub_table_contents" data-post="<?php echo esc_attr(json_encode(array(
                            )));?>"><?php _e('Load More','support_hub');?></a>

                        </div>
                        <?php
                    }
                    ?>
                </div>
                <?php
                break;
            default:
                ?>
                <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
                    <thead>
                    <tr>
                        <?php $this->print_column_headers(); ?>
                    </tr>
                    </thead>

                    <tbody id="the-list"<?php
                    if ( $singular ) {
                        echo " data-wp-lists='list:$singular'";
                    } ?>>
                    <?php $this->display_rows_or_placeholder(); ?>
                    </tbody>

                    <tfoot>
                    <tr>
                        <?php $this->print_column_headers( false ); ?>
                    </tr>
                    </tfoot>

                </table>
                <?php
                break;
        }
        $this->display_tablenav( 'bottom' );
    }

    public function single_row( $item ) {
        switch($this->layout_type) {
            case 'continuous':
            case 'inline':
                if (is_array($item) && !empty($item['shub_extension']) && $message_manager = SupportHub::getInstance()->message_managers[$item['shub_extension']]) {
                    echo '<div ';
	                echo ' data-network="' . $message_manager->id . '"';
	                echo ' data-message-id="' . $item['shub_message_id'] . '"';
	                echo ' class="shub_extension_message_action"><div class="action_content"></div></div>';
                    echo '<div';
                    echo ' class="shub_extension_message"';
                    echo ' data-network="' . $message_manager->id . '"';
                    echo ' data-message-id="' . $item['shub_message_id'] . '"';
                    echo '>';
                    // show the same content from output_message_page() page from the modal popup, but give it a minimal view so it doesn't look too cluttered on the page
                    $message = $message_manager->get_message(false, false, $item['shub_message_id']);
                    $message->output_message_page('inline');
                    echo '</div>';
                }else{
                    echo '<hr>';
                    echo 'Invalid item. Please report bug to dtbaker. <br>';
                    print_r($item);
                    echo '<hr>';
                }
                break;
            default:
                if (is_array($item) && !empty($item['shub_extension']) && $message_manager = SupportHub::getInstance()->message_managers[$item['shub_extension']]) {
	                echo '<tr ';
	                echo ' data-network="' . $message_manager->id . '"';
	                echo ' data-message-id="' . $item['shub_message_id'] . '"';
	                echo ' class="shub_extension_message_action"><td class="action_content" colspan="'.$this->get_column_count().'"></td></tr>';
	                echo '<tr';
                    echo ' class="shub_extension_message ';
                    echo  $this->row_count++%2 ? 'alternate' : '';
                    echo ' "';
                    echo ' data-network="' . $message_manager->id . '"';
                    echo ' data-message-id="' . $item['shub_message_id'] . '"';
	                echo '>';
                }else{
	                // shouldn't happen.
	                echo '<tr>';
                }
                $this->single_row_columns($item);
                echo '</tr>';
                break;
        }
    }

    protected function single_row_columns( $item ) {
        list( $columns, $hidden ) = $this->get_column_info();

        switch($this->layout_type) {
            case 'continuous':
            case 'inline':
                ?>
                <div class="shub_message"
                <?php
                foreach ($columns as $column_name => $column_display_name) {
                    $class = "class='$column_name column-$column_name'";

                    $style = '';
                    if (in_array($column_name, $hidden))
                        $style = ' style="display:none;"';

                    $attributes = "$class$style";

                    if ('cb' == $column_name) {
                        echo '<div scope="row" class="check-column">';
                        echo $this->column_cb($item);
                        echo '</div>';
                    } elseif (method_exists($this, 'column_' . $column_name)) {
                        echo "<div $attributes>";
                        echo call_user_func(array($this, 'column_' . $column_name), $item);
                        echo "</div>";
                    } else {
                        echo "<div $attributes>";
                        echo $this->column_default($item, $column_name);
                        echo "</div>";
                    }
                }
                break;
            default:
                foreach ($columns as $column_name => $column_display_name) {
                    $class = "class='$column_name column-$column_name'";

                    $style = '';
                    if (in_array($column_name, $hidden))
                        $style = ' style="display:none;"';

                    $attributes = "$class$style";


                    if ('cb' == $column_name) {
                        echo '<th scope="row" class="check-column">';
                        echo $this->column_cb($item);
                        echo '</th>';
                    } elseif (method_exists($this, 'column_' . $column_name)) {
                        echo "<td $attributes>";
                        echo call_user_func(array($this, 'column_' . $column_name), $item);
                        echo "</td>";
                    } else {
                        echo "<td $attributes>";
                        echo $this->column_default($item, $column_name);
                        echo "</td>";
                    }
                }
                break;
        }
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
					if(isset($data['message']) && $data['message']->get('shub_status') == _shub_MESSAGE_STATUS_PENDINGSEND){
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
                    if(!is_array($data)){
                        $data_test = @json_decode($data,true);
                        if(is_array($data_test)){
                            $data = $data_test;
                        }
                    }
					if(is_array($data)){
                        echo '<div style="max-height:100px; overflow-y:auto;"><pre>';
                        $lines = explode("\n",var_export($data,true));
                        echo htmlspecialchars(implode("\n",array_merge(array_slice($lines,0,12), (count($lines)>11 ? array("etc...") : array()))));
                        echo '</pre></div>';
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