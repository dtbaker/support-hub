ucm.social.bbpress = {
    init: function(){

        jQuery('body').delegate('.bbpress_check_all','change',function(){
                jQuery('.check_item').prop('checked', !!jQuery(this).prop('checked'));

            });
    }
};