ucm.social.bbpress = {
    init: function(){

        jQuery('body').delegate('.bbpress_reply_button','click',function(){
            var f = jQuery(this).parents('.bbpress_message').first().next('.bbpress_message_replies').find('.bbpress_message_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.bbpress_check_all','change',function(){
                jQuery('.check_bbpress_forum').prop('checked', !!jQuery(this).prop('checked'));

            });
    }
};