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
        jQuery('body').delegate('.shub_modal','click',function(){
            ucm.social.open_modal(jQuery(this).attr('href'), jQuery(this).data('modaltitle'), jQuery(this).data());
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