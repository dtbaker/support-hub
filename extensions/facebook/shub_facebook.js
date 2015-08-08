ucm.social.facebook = {
    api_url: '',
    init: function(){

        jQuery('body').delegate('.facebook_reply_button','click',function(){
            var f = jQuery(this).parents('.facebook_comment').first().next('.facebook_comment_replies').find('.facebook_comment_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.facebook_comment_reply textarea','keyup',function(){
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
        }).delegate('.facebook_comment_reply button','click',function(){
            // send a message!
            var p = jQuery(this).parent();
            var txt = jQuery(p).find('textarea');
            var message = txt.val();
            if(message.length > 0){
                //txt[0].disabled = true;
                // show a loading message in place of the box..
                jQuery.ajax({
                    url: ucm.social.facebook.api_url,
                    method: 'POST',
                    data: {
                        action: 'support_hub_send-message-reply',
                        wp_nonce: support_hub.wp_nonce,
                        id: jQuery(this).data('id'),
                        facebook_id: jQuery(this).data('facebook-id'),
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
                            p.html("Unknown error, please try reconnecting to Facebook in settings. "+r);
                        }
                    }
                });
                p.html('Sending...');
            }
            return false;
        }).delegate('.socialfacebook_message_action','click',ucm.social.facebook.message_action);
        jQuery('.facebook_message_summary a').click(function(){
            var p = jQuery(this).parents('tr').first().find('.socialfacebook_message_open').click();
            return false;
        });
        /*jQuery('.pagination_links a').click(function(){
            jQuery(this).parents('.ui-tabs-panel').first().load(jQuery(this).attr('href'));
            return false;
        });*/
    },
    message_action: function(link){
        jQuery.ajax({
            url: ucm.social.facebook.api_url,
            method: 'POST',
            data: {
                action: 'support_hub_' + jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                shub_facebook_message_id: jQuery(this).data('id'),
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