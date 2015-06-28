ucm = typeof ucm == 'undefined' ? {} : ucm;
ucm.social = {

    modal_url: '',
    init: function(){
        jQuery('.support_hub_date_field').datepicker({ dateFormat: 'yy-mm-dd' });
        jQuery('.support_hub_time_field').timepicker();
        jQuery('body').delegate('.shub_modal','click',function() {
            ucm.social.open_modal(jQuery(this).attr('href'), jQuery(this).data('modaltitle'), jQuery(this).data());
            return false;
        }).delegate('.shub_request_extra', 'click', function(){
            var $f = jQuery(this).parents('form').first();
            if($f.data('requesting_extra')){
                $f.data('requesting_extra',false);
                $f.find('.message_content').show();
                $f.find('.message_request_extra').hide();
            }else{
                $f.data('requesting_extra',true);
                $f.find('.message_content').hide();
                $f.find('.message_request_extra').show();
            }
            return false;
        }).delegate('.shub_request_extra_generate', 'click', function(){
            var $f = jQuery(this).parents('form').first();
            $f.find('.extra_details_message').text('');
            // send a message with these extra details.
            var postdata = jQuery(this).data();
            var ids = [];
            $f.find('.request_extra:checked').each(function(){
                ids.push(jQuery(this).data('extra-id'));
            });
            postdata.extra_ids = ids;
            postdata.action = 'support_hub_request_extra_details';
            postdata.wp_nonce = support_hub.wp_nonce;
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: postdata,
                dataType: 'json',
                success: function(r){
                    if(r && typeof r.redirect != 'undefined'){
                        window.location = r.redirect;
                    }else if(r && typeof r.message != 'undefined'){
                        // got a successful message response, paste that into the next available 'reply' box on the window.
                        jQuery('.shub_message_reply textarea').val(r.message);
                        setTimeout(function(){jQuery('.shub_message_reply textarea').keyup();},100);
                        jQuery('.shub_request_extra').first().click(); // swap back to message screen.
                    }else{
                        $f.find('.extra_details_message').text("Unknown error, please try again: "+r);
                    }
                }
            });
            return false;
        }).delegate('.shub_message_reply textarea','keyup',function(){
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
        }).delegate('.shub_message_reply button','click',function(){
            // send a message!
            var pt = jQuery(this).parent();
            var p = pt.parent();
            var txt = pt.find('textarea');
            var message = txt.val();
            if(message.length > 0){
                //txt[0].disabled = true;
                // show a loading message in place of the box..
                var post_data = {
                    action: 'support_hub_send-message-reply',
                    wp_nonce: support_hub.wp_nonce,
                    message: message,
                    form_auth_key: ucm.form_auth_key
                };
                var button_post = jQuery(this).data('post');
                for(var i in button_post){
                    if(button_post.hasOwnProperty(i)){
                        post_data[i] = button_post[i];
                    }
                }
                // add any additioal reply options to this.
                p.find('[data-reply="yes"]').each(function(){
                    if(jQuery(this).attr('type') == 'checkbox'){
                        post_data[jQuery(this).attr('name')] = this.checked ? jQuery(this).val() : false;
                    }else{
                        post_data[jQuery(this).attr('name')] = jQuery(this).val();
                    }
                });
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: post_data,
                    dataType: 'json',
                    success: function(r){
                        if(r && typeof r.redirect != 'undefined'){
                            window.location = r.redirect;
                        }else if(r && typeof r.message != 'undefined'){
                            pt.html("Info: "+ r.message);
                        }else{
                            pt.html("Unknown error, please check logs or try reconnecting in settings. "+r);
                        }
                    }
                });
                pt.html('Sending...');
                p.find('.shub_message_actions').hide();
            }
            return false;
        }).delegate('.shub_message_action','click',function(){
            // action a message (archive / unarchive)
            var post_data = {
                action: 'support_hub_' + jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                form_auth_key: ucm.form_auth_key
            };
            var button_post = jQuery(this).data('post');
            for(var i in button_post){
                if(button_post.hasOwnProperty(i)){
                    post_data[i] = button_post[i];
                }
            }
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: post_data,
                dataType: 'script',
                success: function(r){
                    ucm.social.close_modal();
                }
            });
            return false;
        });
    },
    close_modal: function(){
        tb_remove();
    },
    open_modal: function(url, title, data){
        url = ajaxurl + '?action=support_hub_modal&wp_nonce=' + support_hub.wp_nonce;
        for(var i in data){
            if(data.hasOwnProperty(i) && i != 'modaltitle'){
                url += '&' + i + '=' + data[i];
            }
        }
        url += '&width=' + Math.min(800,(jQuery(window).width()-400));
        url += '&height=' + (jQuery(window).height()-200);
        tb_show(title, url );
    }

};