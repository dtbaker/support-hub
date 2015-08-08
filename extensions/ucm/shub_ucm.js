ucm.social.ucm = {
    init: function(){

        jQuery('body').delegate('.ucm_reply_button','click',function(){
            var f = jQuery(this).parents('.ucm_message').first().next('.ucm_message_replies').find('.ucm_message_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.ucm_check_all','change',function(){
                jQuery('.check_ucm_product').prop('checked', !!jQuery(this).prop('checked'));

            });
    }
};