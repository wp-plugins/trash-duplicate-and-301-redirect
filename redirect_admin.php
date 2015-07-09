<?php
/* Included for Admin views only */

function TDRD_enqueue_301($hook) {

    // register and enqueue stylesheet and scripts
    wp_register_style('admin_css', plugins_url('/css/trash-duplicates.css', __FILE__));
    wp_enqueue_style('admin_css');
    wp_enqueue_script('admin_js', plugins_url('/js/trash-duplicates.js', __FILE__));
}

add_action('admin_enqueue_scripts', 'TDRD_enqueue_301');

//301 Redirects start

function TDRD_301_redirect() {
    global $wpdb;
    
    $tabel_name = $wpdb->prefix . 'tdrd_redirection';
    if ( (isset ($_REQUEST['301_redirect_from_new']) || isset($_REQUEST['301_redirect_to_new'])) && isset($_REQUEST['btnAdd'])) {
    $old_url = sanitize_text_field($_REQUEST['301_redirect_from_new']);
    $new_url = sanitize_text_field($_REQUEST['301_redirect_to_new']);
    if($old_url != ''){
        if($wpdb->insert( 
                  $tabel_name, 
                    array( 
                        'old_url' => $old_url, 
                        'new_url' => $new_url,
                        'date_time' => current_time('mysql', 1) 
                    ) 
            ) === FALSE)
        {
            ?><div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> Fail to insert new record.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
        }
        else{
            ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> Record inserted successfully.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
        }
    }
    }
    if (isset($_REQUEST['delete-id']) && esc_html($_REQUEST['action']) == 'delete') {
        $delete_id = absint($_REQUEST['delete-id']);
        $counter = $wpdb->delete($tabel_name, array('ID' => $delete_id));
        if ($counter) {
            ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> Removed successfully.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
        } else {
            ?><div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> Failed to remove.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
        }
    }
    if ( isset($_REQUEST['chk_delete_sel']) && (isset($_REQUEST['btnDelete']) ||  isset($_REQUEST['btnDelete1'])))
        TDRD_delete_selected($_REQUEST['chk_delete_sel']);
    
    if (isset($_REQUEST['btnSubmit'])) {
        $update_from = $_REQUEST['301_redirect_from'];
        $update_to = $_REQUEST['301_redirect_to'];
        $update_id = $_REQUEST['editid'];
        $count = count($update_id);
        for ($i = 0; $i < $count; $i++) :
            $wpdb->update(
                    $tabel_name, array(
                'old_url' => sanitize_text_field($update_from[$i]),
                'new_url' => sanitize_text_field($update_to[$i])
                    ), array('ID' => absint($update_id[$i]))
            );
        endfor;
        ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> Saved successfully.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
                }
                $select_stored_data = "SELECT * FROM $tabel_name";
                $stored_resultset = $wpdb->get_results($select_stored_data, ARRAY_A);
                ?>
    <h2 style="color: #42396d;font-weight: 600;text-decoration: underline;">301-Redirection-List</h2>
    <form id="frm_301_redirect" method="post" name="frm_301_redirect">

        <table class="widefat">
            <thead>
                <tr>
                    <?php if($stored_resultset) : ?><th style="padding-left: 8px;text-align: center;"><input type="checkbox" name="chk_del_all" id="chk_del_all"></th><?php endif; ?>
                    <th colspan="2" style="font-weight: 600;">Request</th>
                    <th colspan="2" style="font-weight: 600;">Destination</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php if($stored_resultset) : ?><td><input type="submit" value="Delete" class="button-secondary" name="btnDelete1" onclick="return confirm('Are you sure to remove it?');" ></td><?php endif; ?>
                    <td colspan="2"><small>example: /contact</small></td>
                    <td colspan="2"><small>example: http://loremipsum.com/xyz/contact/</small></td>
                </tr>
    <?php foreach ($stored_resultset as $row) : ?>
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="chkbox" name="chk_delete_sel[]" value="<?php echo $row['ID']; ?>"></td>
                        <td style="width:35%;"><input type="text" style="width:99%;" value="<?php echo $row['old_url']; ?>" name="301_redirect_from[]"><input type="hidden" name="editid[]" value="<?php echo $row['ID']; ?>" /></td>
                        <td style="width:2%;">»</td>
                        <td style="width:60%;"><input type="text" style="width:99%;" value="<?php echo $row['new_url']; ?>" name="301_redirect_to[]"></td>
                        <td style="padding-left: 0;"><a href="<?php echo esc_url(add_query_arg(array('action' => 'delete', 'delete-id' => absint($row['ID'])))); ?>" style="color: #ff0000;" onclick="return confirm('Are you sure to remove it?');" >Delete</a></td>
                    </tr>
    <?php endforeach; ?>
                    <tr>
                        <td></td>
                        <td style="width:35%;"><input type="text" style="width:99%;" name="301_redirect_from_new"></td>
                        <td style="width:2%;">»</td>
                        <td style="width:60%;"><input type="text" style="width:99%;" name="301_redirect_to_new"></td>
                        <td style="padding-left: 0;"><input type="submit" class="button button-primary" name="btnAdd" value="Add"></td>
                    </tr>
                    <?php if($stored_resultset) : ?><tr><td><input type="submit" value="Delete" class="button button-secondary" name="btnDelete" onclick="return confirm('Are you sure to remove it?');" ></tr><?php endif; ?>
                    
            </tbody>
        </table>
        <input type="submit" value="Save Changes" class="button-primary" name="btnSubmit" style="margin-top: 25px;">
    </form>
    <?php
}

function TDRD_delete_selected($delete_sel_id){
    global $wpdb;
    $table = $wpdb->prefix . 'tdrd_redirection';
    if($delete_sel_id){
        $count = count($delete_sel_id);
        $removed_items = array();
        for ($i = 0; $i < $count; $i++) :
            $wpdb->delete( $table, array( 'ID' => absint($delete_sel_id[$i]) ) );
            $removed_items[] = $delete_sel_id[$i];
        endfor;
        $count_del = count($removed_items);
        if ($count_del) {
            ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> <?php echo $count_del; ?> Records removed successfully.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
        } else {
            ?><div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> Failed to remove.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
        }
    }
}
?>