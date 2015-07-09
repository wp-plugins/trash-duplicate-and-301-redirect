jQuery(document).ready(function(){

    jQuery('#chk_remove_all').click(function () {
        if (this.checked)
        {
            jQuery('.chk_box').each(function () {
                this.checked = true;
            });
        }
        else
        {
            jQuery('.chk_box').each(function () {
                this.checked = false;
            });
        }
    });
    
    jQuery(".chk_box").change(function () {
        var a = jQuery(".chk_box");
        if (a.length != a.filter(":checked").length) 
        {
            jQuery('#chk_remove_all').attr('checked', false);
        }
        else
        {
            jQuery('#chk_remove_all').attr('checked', true);
            alert('Make sure you have selected all posts below?');
        }
    });
    jQuery('#delete-all-duplicate').click(function(){
        if (this.checked){
        alert('Make sure all posts will be moved to the trash?');
        }
    });
    
//    list expand collapse
    jQuery(".trash_post_expand").click(function(){
            var src = jQuery(this).children("img").attr('src');
            if (src.contains("down.png")) {
                var newsrc = src.replace("down.png","up.png");
                jQuery(this).children('img').attr('src',newsrc);
            }
            else{
                var newsrc = src.replace("up.png","down.png");
                jQuery(this).children('img').attr('src',newsrc);
            }
            var val = jQuery(this).attr('id');
            var get_id_arr = val.split('_');
            var get_id = get_id_arr[3];
            jQuery('tr[class^="trash_ind_group_'+get_id+'"]').stop().slideToggle(500);
        });
    
    jQuery('#chk_del_all').click(function () {
        if (this.checked)
        {
            jQuery('.chkbox').each(function () {
                this.checked = true;
            });
        }
        else
        {
            jQuery('.chkbox').each(function () {
                this.checked = false;
            });
        }
    });
    jQuery(".chkbox").change(function () {
        var a = jQuery(".chkbox");
        if (a.length != a.filter(":checked").length) 
        {
            jQuery('#chk_del_all').attr('checked', false);
        }
        else
        {
            jQuery('#chk_del_all').attr('checked', true);
        }
    });
});