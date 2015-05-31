ucm = typeof ucm == 'undefined' ? {} : ucm;
ucm.social = {

    modal_url: '',
    init: function(){
        //jQuery('.shub_modal').addClass('thickbox');

        /*var p = jQuery('#shub_modal_popup');
        p.dialog({
            autoOpen: false,
            width: 700,
            height: 600,
            modal: true,
            buttons: {
                Close: function() {
                    jQuery(this).dialog('close');
                }
            },
            open: function(){
                var t = this;
                jQuery.ajax({
                    type: "GET",
                    url: ucm.social.modal_url+(ucm.social.modal_url.match(/\?/) ? '&' : '?')+'display_mode=ajax',
                    dataType: "html",
                    success: function(d){
                        jQuery('.modal_inner',t).html(d);
                        jQuery('input[name=_redirect]',t).val(window.location.href);
                        init_interface();
                        jQuery('.modal_inner iframe.autosize',t).height(jQuery('.modal_inner',t).height()-41); // for firefox
                    }
                });
            },
            close: function() {
                jQuery('.modal_inner',this).html('');
            }
        });*/
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
        }).delegate('.shub_request_extra_send', 'click', function(){
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
                        $f.find('.extra_details_message').text(r.message);
                    }else{
                        $f.find('.extra_details_message').text("Unknown error, please try again: "+r);
                    }
                }
            });
            return false;
        });
    },
    close_modal: function(){
        tb_remove();
        return;
        var p = jQuery('#shub_modal_popup');
        p.dialog('close');
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
        return;
        var p = jQuery('#shub_modal_popup');
        p.dialog('close');
        ucm.social.modal_url = url;
        p.dialog('option', 'title', title);
        p.dialog('open');
    }

};