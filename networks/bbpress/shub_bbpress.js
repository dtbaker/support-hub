ucm.social.bbpress = {
    init: function(){

        jQuery('body').delegate('.bbpress_reply_button','click',function(){
            var f = jQuery(this).parents('.bbpress_message').first().next('.bbpress_message_replies').find('.bbpress_message_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.bbpress_check_all','change',function(){
                jQuery('.check_bbpress_forum').prop('checked', !!jQuery(this).prop('checked'));

            });
        jQuery('.bbpress_message_summary a').click(function(){
            var p = jQuery(this).parents('tr').first().find('.socialbbpress_message_open').click();
            return false;
        });
        /*jQuery('.pagination_links a').click(function(){
            jQuery(this).parents('.ui-tabs-panel').first().load(jQuery(this).attr('href'));
            return false;
        });*/
    }
};