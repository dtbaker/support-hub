ucm.social.envato = {
    init: function(){

        jQuery('body').delegate('#envato_edit_form .envato_reply_button','click',function(){
            var f = jQuery(this).parents('.envato_message').first().next('.envato_message_replies').find('.envato_message_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.envato_check_all','change',function(){
                jQuery('.check_envato_item').prop('checked', !!jQuery(this).prop('checked'));

            });

    }
};