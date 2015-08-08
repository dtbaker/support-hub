ucm.social.google = {
    api_url: '',
    init: function(){

        jQuery('body').delegate('.google_reply_button','click',function(){
            var f = jQuery(this).parents('.google_comment').first().next('.google_comment_replies').find('.google_comment_reply_box');
            f.show();
            f.find('textarea')[0].focus();
        }).delegate('.google_comment_reply textarea','keyup',function(){
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
        }).delegate('.google_comment_reply button','click',function(){
            // send a message!
            var p = jQuery(this).parent();
            var txt = jQuery(p).find('textarea');
            if(txt.length > 0){
                var message = txt.val();
                if(message.length > 0){
                    //txt[0].disabled = true;
                    // show a loading message in place of the box..
                    jQuery.ajax({
                        url: ucm.social.google.api_url,
                        method: 'POST',
                        data: {
                            action: 'support_hub_send-message-reply',
                            wp_nonce: support_hub.wp_nonce,
                            id: jQuery(this).data('id'),
                            shub_google_id: jQuery(this).data('account-id'),
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
                                p.html("Unknown error, please try reconnecting to Google in settings. "+r);
                            }
                        }
                    });
                    p.html('Sending... Please wait...');
                }
            }
            return false;
        }).delegate('.socialgoogle_message_action','click',
            ucm.social.google.message_action
        ).delegate('.google_comment_clickable','click',function(){
            ucm.social.open_modal(jQuery(this).data('link'), jQuery(this).data('title'), jQuery(this).data());
        });
        jQuery('.google_message_summary a').click(function(){
            var p = jQuery(this).parents('tr').first().find('.socialgoogle_message_open').click();
            return false;
        });
        /*jQuery('.pagination_links a').click(function(){
            jQuery(this).parents('.ui-tabs-panel').first().load(jQuery(this).attr('href'));
            return false;
        });*/


        jQuery('.google_compose_message').change(this.google_txt_change).keyup(this.google_txt_change).change();
        this.google_change_post_type();
        jQuery('[name=google_post_type]').change(this.google_change_post_type);

    },
    message_action: function(link){
        jQuery.ajax({
            url: ucm.social.google.api_url,
            method: 'POST',
            data: {
                action: 'support_hub_'+jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                shub_google_message_id: jQuery(this).data('id'),
                shub_google_id: jQuery(this).data('shub_google_id'),
                form_auth_key: ucm.form_auth_key
            },
            dataType: 'script',
            success: function(r){
                ucm.social.close_modal();
            }
        });
        return false;
    },
    google_change_post_type: function(){
        var currenttype = jQuery('[name=google_post_type]:checked').val();
        jQuery('.google-type-option').each(function(){
            jQuery(this).parents('tr').first().hide();
        });
        jQuery('.google-type-'+currenttype).each(function(){
            jQuery(this).parents('tr').first().show();
        });
    }
};
/*
var g = [[["f.ri","117852760669160280054"]
,["on.nr",[[["CgxncGx1c19zdHJlYW0aJXoxMnhkYmtwcnlpNHVqaHhwMDRjZDF0cHVuM3p2cnN4em93MGs",1,[[4,[["106390220425923580448:STREAM_PLUSONE_POST:z12xdbkpryi4ujhxp04cd1tpun3zvrsxzow0k",20,["Jack Jill","https://plus.google.com/106390220425923580448","https://lh3.googleusercontent.com/-XdUIqdMkCWA/AAAAAAAAAAI/AAAAAAAAAAA/4252rscbv5M/photo.jpg","106390220425923580448","male",0,"Jack","Jill","everyone"]
,1.398850174854E15,0,[]
,"106390220425923580448"]
]
]
]
,1.398850174854E15,1,[]
,1,,,,"z12xdbkpryi4ujhxp04cd1tpun3zvrsxzow0k",,,,,1,[]
,"0z12xdbkpryi4ujhxp04cd1tpun3zvrsxzow0k",[[["","","Google+","Support Hub Inbox","Testing a G+ Page Post\ufeff",1398836406547,"http://www.google.com/favicon.ico",[]
,"z12xdbkpryi4ujhxp04cd1tpun3zvrsxzow0k","","s:updates:esshare",[]
,[]
,"","Testing a G+ Page Post",[]
,"117852760669160280054",[]
,"",,"Testing a G+ Page Post","117852760669160280054/posts/XM7sDRSgJCq",0,0.0,"./117852760669160280054",[["Jack Jill","106390220425923580448",0,0,,"./106390220425923580448"]
]
,,,"",0,0,0,1,,0,1,"0",0,1398836406547,,,0,,,,0,,,,[]
,,,0,0,,,1,,,,,0,,,,,[]
,,,,,,,["4/jcsn4y34gdpr0wnth4ubeuncj1s30h33gksrcw3phstroxnmihwbovvra1pk/",4,,,,,1.398999633221134E12,,,"z12xdbkpryi4ujhxp04cd1tpun3zvrsxzow0k",,,[[2.0,,[]
]
]
,1,,,2,[]
,0,0,,"AEIZW7TSnllwmBXeA8qtpXHwoKpvSb1jxnjtHneYUD13PkkFqOg/hQJa5IObSpo8aj7d0XcZIbWs"]
,,,1,,0,0,,1,,,"social.google.com",0,0,0,,1,0,,"2014-04-29",0,0,,1,,,,,0,,,,,,,,,,,,[["Jack Jill","106390220425923580448",1,1,"","https://plus.google.com/106390220425923580448","other"]
]
,,,0,9,,,[]
,0,[]
,0,0,,,[]
,,,,"https://plus.google.com/117852760669160280054/posts/XM7sDRSgJCq",,,1,,["Support Hub Inbox","117852760669160280054",0,0,"","./117852760669160280054"]
,[[[0,"Testing a G+ Page Post\ufeff"]
]
]
,,,,["en",0,"English"]
,,[]
,,[,0]
,,[]
,,0,,,,"z12xdbkpryi4ujhxp04cd1tpun3zvrsxzow0k"]
,,,,[]
,,,,,,,,,0]
,,,,,,["Jack Jill: +1'd: Testing a G+ Page Post","Jack Jill: +1'd: Testing a G+ Page Post","Jack Jill","+1'd: Testing a G+ Page Post"]
]
,[]
,,,0]
,["CgxncGx1c19zdHJlYW0aI3oxMmhmbGJqNXdiNWV6Mmx1MjJjaWhpb3N6eXh5Ymp4YjA0",1,[[13,[["117903403161141413513:STREAM_COMMENT_NEW:z12hflbj5wb5ez2lu22cihioszyxybjxb04#1398996547872468",2,["David Baker","https://plus.google.com/117903403161141413513","//lh5.googleusercontent.com/-K7UUwSCTZAE/AAAAAAAAAAI/AAAAAAAAAwI/gC7q3fmaZ58/photo.jpg","117903403161141413513","other",0,"David","Baker","I'm Dave. A bit of a geek :) CodeCanyon and ThemeForest author."]
,1.398996550803E15,1,[]
,"117903403161141413513"]
]
]
]
,1.398996550803E15,1,[]
,1,,,,"z12hflbj5wb5ez2lu22cihioszyxybjxb04",,,,,1,[]
,"0z12hflbj5wb5ez2lu22cihioszyxybjxb04",[[["","","Google+","Support Hub Inbox","test test\ufeff",1398920588454,"http://www.google.com/favicon.ico",[[,"David Baker","Testing a comment on a page post\ufeff",1398996547872,"z12hflbj5wb5ez2lu22cihioszyxybjxb04#1398996547872468",,"117903403161141413513","z12hflbj5wb5ez2lu22cihioszyxybjxb04",0,0,"./117903403161141413513",0,,,0,[,,,,,,,,,,,,[]
,,,,,[]
,0]
,"https://lh5.googleusercontent.com/-K7UUwSCTZAE/AAAAAAAAAAI/AAAAAAAAAwI/HL4L9nSmiWY/photo.jpg",,,,,,,0,,["David Baker","117903403161141413513",0,0,"https://lh5.googleusercontent.com/-K7UUwSCTZAE/AAAAAAAAAAI/AAAAAAAAAwI/HL4L9nSmiWY/photo.jpg","./117903403161141413513"]
,["en",0,"English"]
,[[[0,"Testing a comment on a page post\ufeff"]
]
]
]
,[,"Support Hub Inbox","test reply\ufeff",1398999831567,"z12hflbj5wb5ez2lu22cihioszyxybjxb04#1398999831567366","test reply","117852760669160280054","z12hflbj5wb5ez2lu22cihioszyxybjxb04",0,0,"./117852760669160280054",0,1,,0,[,,,,,,,,,,,,[]
,,,,,[]
,0]
,"",,,,,,,0,,["Support Hub Inbox","117852760669160280054",0,0,"","./117852760669160280054"]
,["en",0,"English"]
,[[[0,"test reply\ufeff"]
]
]
]
]
,"z12hflbj5wb5ez2lu22cihioszyxybjxb04","","s:updates:esshare",[]
,[]
,"","test test",[]
,"117852760669160280054",[]
,"",,"test test","117852760669160280054/posts/AiTcCab3JVF",0,0.0,"./117852760669160280054",[]
,,,"",0,0,0,1,,0,1,"0",0,1398920588454,,,0,,,,0,,,,[]
,,,0,0,,,1,,,,,0,,,,,[]
,,,,,,,["4/jcsn4u3ahllaohfrgcuqeylmhlun4gn3h5oamvvnjdwrkyf2hdwa4g1o/",4,,,,,0.0,,,,,,[[0.0,,[]
]
]
,0,,,0,[]
,0,0,,"AEIZW7S5pSoTFYVdozvydLx9+In3ahSEfxPj3bKiR/woJpe5/1c95Yvd4BrtXdGYw5n7h+MQRLBR"]
,,,1,,0,0,,1,,,"social.google.com",0,0,0,,1,0,,"2014-04-30",2,0,,0,,,,,0,,,,,,,,,,,,[["David Baker","117903403161141413513",1,1,"https://lh5.googleusercontent.com/-K7UUwSCTZAE/AAAAAAAAAAI/AAAAAAAAAwI/HL4L9nSmiWY/photo.jpg","https://plus.google.com/+dtbaker","other"]
,["Support Hub Inbox","117852760669160280054",1,1,"","https://plus.google.com/117852760669160280054","other"]
]
,,,0,9,,,[]
,0,[]
,0,0,,,[]
,,,,"https://plus.google.com/117852760669160280054/posts/AiTcCab3JVF",,,1,,["Support Hub Inbox","117852760669160280054",0,0,"","./117852760669160280054"]
,[[[0,"test test\ufeff"]
]
]
,,,,["en",0,"English"]
,,[]
,,[,0]
,,[]
,,0,,,,"z12hflbj5wb5ez2lu22cihioszyxybjxb04"]
,,,,[]
,,,,,,,,,0]
,,,,,,["David Baker: Commented on: test test","David Baker: Commented on: test test","David Baker","Commented on: test test"]
]
,[]
,,,0]
]
,1.398997296662001E15,,1,,,1,,[["Support Hub Inbox","https://plus.google.com/117852760669160280054","https://lh3.googleusercontent.com/-XdUIqdMkCWA/AAAAAAAAAAI/AAAAAAAAAAA/4252rscbv5M/photo.jpg","117852760669160280054","other",0,"Support Hub Inbox",".",""]
,["David Baker","https://plus.google.com/117903403161141413513","//lh5.googleusercontent.com/-K7UUwSCTZAE/AAAAAAAAAAI/AAAAAAAAAwI/gC7q3fmaZ58/photo.jpg","117903403161141413513","other",0,"David","Baker","I'm Dave. A bit of a geek :) CodeCanyon and ThemeForest author."]
,["Jack Jill","https://plus.google.com/106390220425923580448","https://lh3.googleusercontent.com/-XdUIqdMkCWA/AAAAAAAAAAI/AAAAAAAAAAA/4252rscbv5M/photo.jpg","106390220425923580448","male",0,"Jack","Jill","everyone"]
]
,,[]
]
,"117852760669160280054"]
,["di",550,,,,,[]
,[]
,,,[]
,[]
,[]
]
,["e",4,,,5951]
]];*/