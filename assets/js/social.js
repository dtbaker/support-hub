ucm = typeof ucm == 'undefined' ? {} : ucm;
ucm.social = {

    current_shub_message_id: 0,
    modal_url: '',
    init: function(){
        var t = this;
        var menu_count = jQuery('#shub_menu_outbox_count');
        if(menu_count.get(0) && !menu_count.data('count')){
            //menu_count.parents('li').first().hide();
        }
        jQuery('.support_hub_date_field').datepicker({ dateFormat: 'yy-mm-dd' });
        jQuery('.support_hub_time_field').timepicker();
        jQuery('body').delegate('.shub_modal','click',function() {
            var width = jQuery(window).width();
            if(width > 782) {
                // only show modal on desktop
                ucm.social.open_message_modal(jQuery(this).data('modaltitle'), jQuery(this).data());
                return false;
            }
            return true;
        }).delegate('.shub_close_modal','click',function(e) {
            e.preventDefault();
            ucm.social.close_modal();
            return false;
        }).delegate('.shub_request_extra', 'click', function(){
            var $f = jQuery(this).parents('.message_edit_form').first();
            // find out how far away this button is from the parent form
            // move our 'request extra' popover this far down as well so it kinda lines up nicely for long messages.
            if($f.data('requesting_extra')){
                $f.data('requesting_extra',false);
                $f.find('.message_content').css('opacity',1);
                $f.find('.message_request_extra').hide();
            }else{
                $f.data('requesting_extra',true);
                $f.find('.message_content').css('opacity',0.4);
                var pos = jQuery(this).position();
                var $e = $f.find('.message_request_extra');
                $e.show().css('top',Math.max(20, pos.top - jQuery($e).height() - (jQuery($e).height()/2)));

            }
            return false;
        }).delegate('.shub_request_extra_generate', 'click', function(){
            if(jQuery(this).hasClass('shub_button_loading')){
                // we show some progress indicator
                var loading_button = dtbaker_loading_button(this);
                if(!loading_button){
                    return false;
                }
            }
            var $f = jQuery(this).parents('.message_edit_form').first();
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
                        $f.find('.shub_message_reply textarea.shub_message_reply_text').val(r.message);
                        setTimeout(function(){$f.find('.shub_message_reply textarea.shub_message_reply_text').keyup();},100);
                        $f.find('.shub_request_extra').first().click(); // swap back to message screen.
                    }else{
                        $f.find('.extra_details_message').text("Unknown error, please try again: "+r);
                    }
                },
                complete: function(){
                    if(typeof loading_button != 'undefined'){
                        loading_button.done();
                    }
                }
            });
            return false;
        }).delegate('.shub_message_reply textarea','keyup',function(){
            var a = jQuery(this);
            if (!a.prop('scrollTop')) {
                do {
                    var b = a.prop('scrollHeight');
                    var h = a.height();
                    a.height(h - 5);
                }
                while (b && (b != a.prop('scrollHeight')));
            }
            if(a.hasClass('shub_message_reply_text')) {
                // show the reply buttons and actions if we have content.
                var txt = a.val();
                var $replybox = a.parents('.shub_message_reply').first();
                if (txt.length > 0) {
                    $replybox.addClass('shub_has_message_text');
                } else {
                    $replybox.removeClass('shub_has_message_text');
                }
            }

            a.height(a.prop('scrollHeight') + 10);
        }).delegate('.shub_message_action_button','click',function(){
            if(jQuery(this).hasClass('shub_button_loading')){
                // we show some progress indicator
                var loading_button = dtbaker_loading_button(this);
                if(!loading_button){
                    return false;
                }
            }
            var pt = jQuery(this).parents('.shub_message_actions').first();
            var post_data = {
                action: 'support_hub_message-actions',
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
                dataType: 'json',
                success: function(r){
                    if(r && typeof r.message != 'undefined' && r.message.length > 0){
                        pt.append("<div>" + r.message + "</div>");
                    }else{
                        pt.append("<div>Unknown error, please check logs. " + r+ "</div>");
                    }
                }
            });
            return false;
        }).delegate('.shub_send_message_reply_button','click',function(){
            // send a message!

            if(jQuery(this).hasClass('shub_button_loading')){
                // we show some progress indicator
                var loading_button = dtbaker_loading_button(this);
                if(!loading_button){
                    return false;
                }
            }

            var pt = jQuery(this).parents('.shub_message_reply_box').first();
            var txt = pt.find('textarea.shub_message_reply_text');
            var message = txt.val();
            if(message.length > 0){
                //txt[0].disabled = true;
                // show a loading message in place of the box..
                var post_data = t.build_post_data('send-message-reply');
                post_data.message = message;
                var button_post = jQuery(this).data('post');
                for(var i in button_post){
                    if(button_post.hasOwnProperty(i)){
                        post_data[i] = button_post[i];
                    }
                }
                // add any additioal reply options to this.
                pt.find('[data-reply="yes"]').each(function(){
                    if(jQuery(this).attr('type') == 'checkbox'){
                        if(this.checked){
                            post_data[jQuery(this).attr('name')] = jQuery(this).val();
                        }else{
                            // don't pass a 'false' into ajax, just send nothing.
                        }
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
                        if(r && typeof r.redirect != 'undefined') {
                            window.location = r.redirect;
                        }else if(r && typeof r.error != 'undefined' && r.error){
                            pt.html("Error: "+ r.message);
                        }else if(r && typeof r.shub_outbox_id != 'undefined' && r.shub_outbox_id){
                            // successfully queued the message reply for sending.
                            // slide up this window and show a "queued" message, similar to archiving a message.

                            // we fire off the 'change message' status change.
                            t.message_status_changed(post_data.network, post_data['message-id'], 'queued');
                            t.queue_watch.add(r.shub_outbox_id, false, function(){
                                // successfully sent.
                                // fire off the 'change_status' callback so we get a nice 'View' callback.
                                t.message_status_changed(post_data.network, post_data['message-id'], 'sent');
                                //element_action.find('.action_content').html('Message Sent!');
                            }, function(){
                                // failed to send
                                t.message_status_changed(post_data.network, post_data['message-id'], 'error');
                                //element_action.find('.action_content').html('Failed to send message. Please check logs.');
                            });

                        }else if(r && typeof r.message != 'undefined' && r.message.length > 0){
                            pt.html("Info: "+ r.message);
                        }else{
                            pt.html("Unknown error, please check logs or try reconnecting in settings. "+r);
                        }
                    },
                    complete: function(){
                        if(typeof loading_button != 'undefined'){
                            loading_button.done();
                        }
                    }
                });
                pt.html('Sending...');
                pt.find('.shub_message_actions').hide();
            }else{
                if(typeof loading_button != 'undefined'){
                    loading_button.done();
                }
            }
            return false;
        }).delegate('.shub_message_action','click',function(){
            if(jQuery(this).hasClass('shub_button_loading')){
                // we show some progress indicator
                var loading_button = dtbaker_loading_button(this);
                if(!loading_button){
                    return false;
                }
            }
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
                },
                complete: function(){
                    if(typeof loading_button != 'undefined'){
                        setTimeout(loading_button.done,2000);
                    }
                }
            });
            return false;
        }).delegate('.shub_message_load_content','click',function(){
            // action a message (archive / unarchive)
            if(jQuery(this).hasClass('shub_button_loading')){
                // we show some progress indicator
                var loading_button = dtbaker_loading_button(this);
                if(!loading_button){
                    return false;
                }
            }
            ucm.social.load_content(jQuery(this).data('action'), jQuery(this).data('target'), jQuery(this).data('post'), function(){
                if(typeof loading_button != 'undefined'){
                    loading_button.done();
                }
                ucm.social.set_inline_view();
            });

            return false;
        }).delegate('.swap_layout_type','click',function(){
            jQuery('#layout_type').val(jQuery(this).data('layout-type')).parents('form').get(0).submit();
            return false;
        }).delegate('.shub_view_full_message_sidebar','click',function(){
            var $s = jQuery(this).parents('section').first();
            $s.find('nav').hide();
            $s.find('header,aside').show();
            return false;
        }).delegate('.shub_message_reply_action_has_options [data-reply="yes"]','change',function(){
            var $s = jQuery(this).parents('.shub_message_reply_action').first();
            if(jQuery(this).is(':checked')){
                $s.addClass('shub_message_reply_activated');
                $s.find('textarea').keyup();
            }else{
                $s.removeClass('shub_message_reply_activated');
            }
            return false;
        });

        ucm.social.set_inline_view();

    },
    set_inline_view: function(){

        // on page scroll we align the inline-sidebar with the viewport.
        var inline_views = [];
        jQuery('#shub_table_inline .shub_extension_message').each(function(){
            inline_views.push({
                pos: jQuery(this).position(),
                height: jQuery(this).height(),
                sidebar: jQuery(this).find('section.message_sidebar')
            });
        });
        jQuery(window).scroll(function(){
            var width = jQuery(window).width();
            if(width > 782) {
                var currenttop = jQuery(window).scrollTop();
                // find out which elements we have to move into view.
                for (var i = 0; i < inline_views.length; i++) {
                    if (inline_views[i].pos.top < currenttop && inline_views[i].pos.top + inline_views[i].height > currenttop) {
                        // calc here incase of ajax loading sidebar data
                        var sidebar_height = inline_views[i].sidebar.height();
                        var from_top = Math.min(inline_views[i].height - sidebar_height - 20, currenttop - inline_views[i].pos.top + 20);
                        if (from_top > 10) {
                            inline_views[i].sidebar.css('padding-top', from_top + 'px');
                        } else {
                            inline_views[i].sidebar.attr('style', '');
                        }
                    } else {
                        inline_views[i].sidebar.attr('style', '');
                    }
                }
            }
        });

    },
    close_modal: function(){
        ucm.social.current_shub_message_id = 0;
        tb_remove();
    },
    open_message_modal: function(title, data){
        var url = ajaxurl + '?action=support_hub_modal&wp_nonce=' + support_hub.wp_nonce;
        ucm.social.open_modal(url, title, data);
    },
    open_modal: function(url, title, data){
        for(var i in data){
            if(data.hasOwnProperty(i) && i != 'modaltitle'){
                url += '&' + i + '=' + data[i];
                if(i == 'message_id' || i == 'shub_message_id'){
                    ucm.social.current_shub_message_id = data[i];
                }
            }
        }
        url += '&width=' + Math.min(800,(jQuery(window).width()-400));
        url += '&height=' + (jQuery(window).height()-200);
        tb_show(title, url );
    },

    queue_watch: {
        queue: [],
        last_queue_length:0,
        last_queue_length_same_count:0,
        add: function(shub_outbox_id, element, success_callback, fail_callback){
            this.queue.push(
                {
                    shub_outbox_id: parseInt(shub_outbox_id),
                    element: element,
                    success_callback: success_callback,
                    fail_callback: fail_callback
                }
            );
            this.last_queue_length = 0;
            this.last_queue_length_same_count = 1;
            var menu_count = jQuery('#shub_menu_outbox_count');
            if(menu_count.get(0)){
                var new_count = parseInt(menu_count.data('count')) + 1;
                jQuery('#shub_menu_outbox_count').data('count',new_count).text(new_count);
            }
            this.watch();
        },
        watching: false,
        watch: function(){
            var t = this;
            if(t.watching)return;
            t.watching = true;
            var post_data = {
                action: 'support_hub_queue-watch',
                wp_nonce: support_hub.wp_nonce,
                form_auth_key: ucm.form_auth_key
            };
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: post_data,
                dataType: 'json',
                success: function(r){
                    for(var x = 0; x < t.queue.length; x++){
                        if(typeof t.queue[x] != 'undefined') {
                            // find this shub_outbox_id in the queue response from server.
                            var found = false;
                            if (r && typeof r.outbox_ids != 'undefined') {
                                for (var i in r.outbox_ids) {
                                    if (r.outbox_ids.hasOwnProperty(i) && typeof r.outbox_ids[i] != 'undefined') {
                                        if (r.outbox_ids[i].shub_outbox_id && t.queue[x].shub_outbox_id == parseInt(r.outbox_ids[i].shub_outbox_id)) {
                                            found = true;
                                            // has it errored?
                                            if (parseInt(r.outbox_ids[i].shub_status) == 2) {
                                                if (typeof t.queue[x].fail_callback == 'function') {
                                                    t.queue[x].fail_callback();
                                                    delete(t.queue[x]);
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            if (!found) {
                                // it's no longer in the queue! yay!
                                // fire off complete callback
                                if (typeof t.queue[x].success_callback == 'function') {
                                    t.queue[x].success_callback();
                                    delete(t.queue[x]);
                                }
                            }
                        }
                    }
                    var has_pending = false;
                    // update the menu UI
                    var queue_length = 0;
                    if (r && typeof r.outbox_ids != 'undefined') {
                        for (var i in r.outbox_ids) {
                            if (r.outbox_ids.hasOwnProperty(i) && typeof r.outbox_ids[i] != 'undefined') {
                                if (typeof r.outbox_ids[i].shub_status != 'undefined' && (parseInt(r.outbox_ids[i].shub_status) == 0 || parseInt(r.outbox_ids[i].shub_status) == 1 || parseInt(r.outbox_ids[i].shub_status) == 2)) {
                                    // new/pending/errored
                                    queue_length++;
                                }
                                if (typeof r.outbox_ids[i].shub_status != 'undefined' && (parseInt(r.outbox_ids[i].shub_status) == 0 || parseInt(r.outbox_ids[i].shub_status) == 1)) {
                                    // we have a pending queue to send!
                                    has_pending = true;
                                }
                            }
                        }
                    }
                    jQuery('#shub_menu_outbox_count').text(queue_length); // (t.queue.length); //.parents('li').first().show();
                    if(queue_length == t.last_queue_length){
                        t.last_queue_length_same_count++;
                    }else{
                        t.last_queue_length = queue_length;
                        t.last_queue_length_same_count=1;
                    }

                    t.watching = false;
                    if(has_pending) {
                        setTimeout(function () {
                            t.watch();
                        }, t.last_queue_length_same_count * 1000);
                    }
                },
                error: function(){
                    t.watching = false;
                }
            });

        }
    },
    build_post_data: function(action){
        return {
            action: 'support_hub_' + action,
            wp_nonce: support_hub.wp_nonce,
            form_auth_key: ucm.form_auth_key
        };
    },
    load_content: function(action, target, button_post, callback){
        var post_data = ucm.social.build_post_data(action);
        if(button_post) {
            for (var i in button_post) {
                if (button_post.hasOwnProperty(i)) {
                    post_data[i] = button_post[i];
                }
            }
        }
        $target = jQuery(target);
        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: post_data,
            dataType: 'html',
            success: function(r){
                $target.append(r);
            },
            complete: function(){
                if(typeof callback == 'function')callback();

            }
        });
    },
    message_status_changed: function(network, message_id, message_status){

        if(ucm.social.current_shub_message_id == message_id){
            ucm.social.close_modal();
        }

        var element = jQuery('.shub_extension_message[data-message-id=' + message_id + ']');
        if(!element.length)return;
        var element_action = element.prev('.shub_extension_message_action').first();
        //var json = {'network': network, 'shub_message_id': message_id};
        //'<a href="#" class="shub_message_action" data-action="set-unanswered">Undo</a>');
        var message_view = 'View Message';
        var data_action = '';
        var message_text = '';
        var allow_undo = true, allow_view = true, allow_related = true, allow_scroll = false;
        switch(message_status){
            case 'queued':
                message_text = 'Sending message... Please wait...';
                allow_undo = allow_view = false;
                allow_scroll = true;
                //support_hub.layout_type...
                break;
            case 'sent':
                message_text = 'Message Sent.';
                break;
            case 'error':
                message_text = 'Failed to send message. Please check logs.';
                break;
            case 0: // message status chagned to unanswered (inbox)
                data_action = 'set-answered';
                message_text = 'Message Moved to Inbox.';
                break;
            case 1: // message status changed to answered (archived)
                data_action = 'set-unanswered';
                message_text = 'Message Archived.';
                break;
        }
        var $action_content_wrapper = element_action.find('.action_content'), $action_content = $action_content_wrapper.find('.action_content_message');
        if(!$action_content.length){
            $action_content = jQuery('<div/>',{class:'action_content_message'}).appendTo($action_content_wrapper);
        }


        // todo: find all instances of linked messages on the screen and update their status.

        $action_content.text(message_text);
        if(allow_undo) {
            $action_content.append(
                data_action
                    ?
                    jQuery('<a/>', {
                        'class': 'shub_message_action',
                        'href': '#',
                        'data-action': data_action
                    })
                        .data('post', {
                            network: network,
                            shub_message_id: message_id
                        })
                        .text('Undo')
                    :
                    jQuery('<span/>')
            );
        }
        if(allow_view) {
            $action_content.append(
                jQuery('<a/>', {
                    'class': 'shub_modal shub_message_view',
                    'href': '#',
                    text: message_view,
                    'data-network': network,
                    'data-message_id': message_id,
                    'data-message_comment_id': 0,
                    'data-modaltitle': message_view
                })
            );
            /*$action_content.append(
                jQuery('<a/>', {
                    'class': 'shub_message_view',
                    'href': '#'
                })
                    .text(message_view)
                    .click(function () {
                        // load the message again from the server into the empty message container.
                        var post_data = ucm.social.build_post_data('load-message');
                        post_data['layout_type'] = support_hub.layout_type; //element.is('div') ? 'continuous' : 'table';
                        post_data['network'] = network;
                        post_data['message_id'] = message_id;
                        jQuery.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: post_data,
                            dataType: 'html',
                            success: function (r) {
                                element.replaceWith(r);
                            },
                            complete: function () {
                                if (element.is('div')) {
                                    element.slideDown();
                                    element_action.slideUp();
                                } else {
                                    element.show();
                                    element_action.hide();
                                }
                            }
                        });
                        return false;
                    })
            );*/
        }
        if(allow_related && !$action_content_wrapper.find('.action_content_related').length) {
            var $related_wrapper= jQuery('<div/>',{class:'action_content_related'})
                .appendTo($action_content_wrapper);

            // build up a list of other related messages to display
            // the idea is "You just replied to this message! Great! Here's some others from this same user."
            var post_data = ucm.social.build_post_data('load-related-messages');
            post_data['network'] = network;
            post_data['shub_message_id'] = message_id;
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: post_data,
                dataType: 'json',
                success: function (r) {
                    // find out how many are inbox/archived.
                    var messages_inbox = [], messages_archived = [];
                    for(var i in r){
                        if(r.hasOwnProperty(i) && typeof r[i].message_status != 'undefined' && typeof r[i].full_link != 'undefined'){
                            switch(parseInt(r[i].message_status)){
                                case 1: // _shub_MESSAGE_STATUS_ANSWERED
                                    messages_archived.push(r[i]);
                                    break;
                                case 0: // _shub_MESSAGE_STATUS_UNANSWERED
                                    messages_inbox.push(r[i]);
                                    break;
                            }
                        }
                    }
                    if(messages_inbox.length > 0 || messages_archived.length > 0){
                        var $related_buttons = jQuery('<span/>',{class:'other_related_messages_buttons',text:'Other Messages:'})
                            .prependTo($related_wrapper);
                    }
                    if(messages_inbox.length > 0){
                        //var $related = jQuery('<div class="shub_status_related_messages">' + messages_inbox.length + ' Other Inbox Messages: </div>').appendTo($related_wrapper);
                        var $related_inbox_messages = jQuery('<ul/>',{class:'related_messages'}).appendTo($related_wrapper)
                            .append(jQuery.map(messages_inbox, function(tt){
                            if(tt && typeof tt.full_link != 'undefined') {
                                return jQuery('<li/>',{'data-message-id': tt.message_id,class:'shub_related_message_small'})
                                    .append( jQuery('<span/>',{class:'other_message_time', text: tt.date_time}) )
                                    .append( jQuery('<span/>',{class:'other_message_status'}).append(tt.message_status_html) )
                                    .append( jQuery('<span/>',{class:'other_message_network'}).append(tt.icon) )
                                    .append(tt.full_link);
                            }
                        }));
                        jQuery('<a/>',{href:'#',text:messages_inbox.length + ' inbox'}).click(function(e){
                            e.preventDefault();
                            $related_inbox_messages.toggleClass('related_shown');
                            return false;
                        }).appendTo($related_buttons);
                    }
                    if(messages_archived.length > 0){
                        //var $related = jQuery('<div class="shub_status_related_messages">' + messages_archived.length + ' Archived Messages: </div>').appendTo($related_wrapper);
                        var $related_archived_messages = jQuery('<ul/>',{class:'related_messages'}).appendTo($related_wrapper)
                            .append(jQuery.map(messages_archived, function(tt){
                            if(tt && typeof tt.full_link != 'undefined') {
                                return jQuery('<li/>',{'data-message-id': tt.message_id,class:'shub_related_message_small'})
                                    .append( jQuery('<span/>',{class:'other_message_time', text: tt.date_time}) )
                                    .append( jQuery('<span/>',{class:'other_message_status'}).append(tt.message_status_html) )
                                    .append( jQuery('<span/>',{class:'other_message_network'}).append(tt.icon) )
                                    .append(tt.full_link);
                            }
                        }));
                        jQuery('<a/>',{href:'#',text:messages_archived.length + ' archived'}).click(function(e){
                            e.preventDefault();
                            $related_archived_messages.toggleClass('related_shown');
                            return false;
                        }).appendTo($related_buttons);
                    }
                }
            });
        }
        if(element.is('div')){
            element.slideUp(function(){
                element.html('');
                if(allow_scroll){
                    var pos = element_action.position();
                    if(pos) {
                        if (jQuery(window).scrollTop() > pos.top - 10)jQuery(window).scrollTop(pos.top - 10);
                    }
                }
            });
            element_action.slideDown();
        }else{
            element.html('');
            element_action.show();
            if(allow_scroll){
                var pos = element_action.position();
                if(pos) {
                    if (jQuery(window).scrollTop() > pos.top - 10)jQuery(window).scrollTop(pos.top - 10);
                }
            }
        }
    }

};
function dtbaker_loading_button(btn){

    var $button = jQuery(btn);
    if($button.attr('disabled'))return false;
    var existing_text = $button.text();
    var existing_width = $button.outerWidth();
    var loading_text = '⡀⡀⡀⡀⡀⡀⡀⡀⡀⡀⠄⠂⠁⠁⠂⠄';
    var completed = false;

    $button.css('width',existing_width);
    $button.addClass('dtbaker_loading_button_current');
    $button.text(loading_text);
    $button.attr('disabled',true);

    var anim_index = [0,1,2];

    // animate the text indent
    function moo() {
        if (completed)return;
        var current_text = '';
        // increase each index up to the loading length
        for(var i = 0; i < anim_index.length; i++){
            anim_index[i] = anim_index[i]+1;
            if(anim_index[i] >= loading_text.length)anim_index[i] = 0;
            current_text += loading_text.charAt(anim_index[i]);
        }
        $button.text(current_text);
        setTimeout(function(){ moo();},60);
    }

    moo();

    return {
        done: function(){
            completed = true;
            $button.text(existing_text);
            $button.removeClass('dtbaker_loading_button_current');
            $button.attr('disabled',false);
        }
    }

}