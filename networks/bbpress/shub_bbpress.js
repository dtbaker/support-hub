ucm.social.bbpress = {
    api_url: '',
    init: function(){

        jQuery('body').delegate('.bbpress_reply_button','click',function(){
            var f = jQuery(this).parents('.bbpress_message').first().next('.bbpress_message_replies').find('.bbpress_message_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.bbpress_message_reply textarea','keyup',function(){
            var a = this;
            if (!jQuery(a).prop('scrollTop')) {
                do {
                    var b = jQuery(a).prop('scrollHeight');
                    var h = jQuery(a).height();
                    jQuery(a).height(h - 5);
                }
                while (b && (b != jQuery(a).prop('scrollHeight')));
            }
            jQuery(a).height(jQuery(a).prop('scrollHeight') + 10);
        }).delegate('.bbpress_message_reply button','click',function(){
            // send a message!
            var p = jQuery(this).parent();
            var txt = jQuery(p).find('textarea');
            var message = txt.val();
            if(message.length > 0){
                //txt[0].disabled = true;
                // show a loading message in place of the box..
                jQuery.ajax({
                    url: ucm.social.bbpress.api_url,
                    method: 'POST',
                    data: {
                        action: 'support_hub_send-message-reply',
                        wp_nonce: support_hub.wp_nonce,
                        id: jQuery(this).data('id'),
                        bbpress_id: jQuery(this).data('bbpress-id'),
                        message: message,
                        debug: jQuery(p).find('.reply-debug')[0].checked ? 1 : 0,
                        form_auth_key: ucm.form_auth_key
                    },
                    dataType: 'json',
                    success: function(r){
                        if(r && typeof r.redirect != 'undefined'){
                            window.location = r.redirect;
                        }else if(r && typeof r.message != 'undefined'){
                            p.html("Info: "+ r.message);
                        }else{
                            p.html("Unknown error, please try reconnecting to bbpress in settings. "+r);
                        }
                    }
                });
                p.html('Sending...');
            }
            return false;
        }).delegate('.socialbbpress_message_action','click',ucm.social.bbpress.message_action)
            .delegate('.bbpress_check_all','change',function(){
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
    },
    message_action: function(link){
        jQuery.ajax({
            url: ucm.social.bbpress.api_url,
            method: 'POST',
            data: {
                action: 'support_hub_' + jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                shub_bbpress_message_id: jQuery(this).data('id'),
                form_auth_key: ucm.form_auth_key
            },
            dataType: 'script',
            success: function(r){
                ucm.social.close_modal();
            }
        });
        return false;
    }
};