ucm.social.envato = {
    init: function(){

        jQuery('body').delegate('#envato_edit_form .envato_reply_button','click',function(){
            var f = jQuery(this).parents('.envato_message').first().next('.envato_message_replies').find('.envato_message_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.socialenvato_message_action','click',ucm.social.envato.message_action)
            .delegate('.envato_check_all','change',function(){
                jQuery('.check_envato_item').prop('checked', !!jQuery(this).prop('checked'));

            });
        jQuery('.envato_message_summary a').click(function(){
            var p = jQuery(this).parents('tr').first().find('.socialenvato_message_open').click();
            return false;
        });
        /*jQuery('.pagination_links a').click(function(){
            jQuery(this).parents('.ui-tabs-panel').first().load(jQuery(this).attr('href'));
            return false;
        });*/
    },
    message_action: function(link){
        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'support_hub_' + jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                shub_envato_message_id: jQuery(this).data('id'),
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