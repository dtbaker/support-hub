ucm.social.twitter = {
    api_url: '',
    init: function(){

        jQuery('body').delegate('.twitter_reply_button','click',function(){
            var f = jQuery(this).parents('.twitter_comment').first().next('.twitter_comment_replies').find('.twitter_comment_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.twitter_comment_reply textarea','keyup',function(){
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
        }).delegate('.twitter_comment_reply button','click',function(){
            // send a message!
            var p = jQuery(this).parent();
            var txt = jQuery(p).find('textarea');
            if(txt.length > 0){
                var message = txt.val();
                if(message.length > 0){
                    //txt[0].disabled = true;
                    // show a loading message in place of the box..
                    jQuery.ajax({
                        url: ucm.social.twitter.api_url,
                        method: 'POST',
                        data: {
                            action: 'support_hub_send-message-reply',
                            wp_nonce: support_hub.wp_nonce,
                            id: jQuery(this).data('id'),
                            social_twitter_id: jQuery(this).data('account-id'),
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
                                p.html("Unknown error, please try reconnecting to Twitter in settings. "+r);
                            }
                        }
                    });
                    p.html('Sending... Please wait...');
                }
            }
            return false;
        }).delegate('.socialtwitter_message_action','click',
            ucm.social.twitter.message_action
        ).delegate('.twitter_comment_clickable','click',function(){
            ucm.social.open_modal(jQuery(this).data('link'), jQuery(this).data('title'), jQuery(this).data());
        });
        jQuery('.twitter_message_summary a').click(function(){
            var p = jQuery(this).parents('tr').first().find('.socialtwitter_message_open').click();
            return false;
        });
        /*jQuery('.pagination_links a').click(function(){
            jQuery(this).parents('.ui-tabs-panel').first().load(jQuery(this).attr('href'));
            return false;
        });*/


        jQuery('.twitter_compose_message').change(this.twitter_txt_change).keyup(this.twitter_txt_change).change();
        this.twitter_change_post_type();
        jQuery('[name=twitter_post_type]').change(this.twitter_change_post_type);

    },
    message_action: function(link){
        jQuery.ajax({
            url: ucm.social.twitter.api_url,
            method: 'POST',
            data: {
                action: 'support_hub_'+jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                social_twitter_message_id: jQuery(this).data('id'),
                social_twitter_id: jQuery(this).data('social_twitter_id'),
                form_auth_key: ucm.form_auth_key
            },
            dataType: 'script',
            success: function(r){
                ucm.social.close_modal();
            }
        });
        return false;
    },
    twitter_limit: 140,
    twitter_set_limit: function(limit){
        ucm.social.twitter.twitter_limit = limit;
        jQuery('.twitter_compose_message').change();
    },
    charactersleft: function(tweet, limit) {
        var url, i, lenUrlArr;
        var virtualTweet = tweet;
        var filler = "0123456789012345678912";
        var extractedUrls = twttr.txt.extractUrlsWithIndices(tweet);
        var remaining = limit;
        lenUrlArr = extractedUrls.length;
        if ( lenUrlArr > 0 ) {
            for (i = 0; i < lenUrlArr; i++) {
                url = extractedUrls[i].url;
                virtualTweet = virtualTweet.replace(url,filler);
            }
        }
        remaining = remaining - virtualTweet.length;
        return remaining;
    },
    twitter_txt_change: function(){
        var remaining = ucm.social.twitter.charactersleft(jQuery(this).val(), ucm.social.twitter.twitter_limit);
        jQuery(this).parent().find('.twitter_characters_remain').first().find('span').text(remaining);
    },
    twitter_change_post_type: function(){
        var currenttype = jQuery('[name=twitter_post_type]:checked').val();
        jQuery('.twitter-type-option').each(function(){
            jQuery(this).parents('tr').first().hide();
        });
        jQuery('.twitter-type-'+currenttype).each(function(){
            jQuery(this).parents('tr').first().show();
        });
        if(currenttype == 'picture'){
            ucm.social.twitter.twitter_set_limit(118);
        }else{
            ucm.social.twitter.twitter_set_limit(140);
        }
    }
};