ucm.social.envato = {
    init: function(){

        jQuery('body').delegate('.envato_check_all','change',function(){
                jQuery('.check_item').prop('checked', !!jQuery(this).prop('checked'));

            });

    }
};