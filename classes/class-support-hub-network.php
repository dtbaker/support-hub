<?php

class SupportHub_network{


	public $id;
	public $friendly_name;
	public $desc;

	public function __construct( ) {
		$this->reset();
	}
	private function reset(){}


	public function init(){}
	public function get_unread_count(){}
	public function init_menu(){}
	public function page_assets(){}
	public function settings_page(){}
	public function is_enabled(){
		return get_option('shub_manager_enabled_'.$this->id,0);
	}
	public function get_message_details($message_id){return array();}
	public function get_install_sql(){return '';}

}