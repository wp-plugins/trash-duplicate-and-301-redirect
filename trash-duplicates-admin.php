<?php
//  Add menu 
function TDRD_menu() {
    add_menu_page('Trash Duplicates', 'Trash Duplicates', 'update_plugins', 'trash_duplicates', 'TDRD_admin_func', plugins_url('/images/trash-copy-1.png', __FILE__));
    add_submenu_page( 'trash_duplicates', '301 Redirects', '301 Redirects', 'update_plugins', '301_redirects', 'TDRD_301_redirect' );
}
add_action('admin_menu', 'TDRD_menu', 5);

// For Plugin Settings link 
function TDRD_settings_link($actions, $plugin_file) {
    
    static $plugin;
    if (empty($plugin))
        $plugin = dirname(plugin_basename(__FILE__)) . '/trash-duplicates.php';
    if ($plugin_file == $plugin) {
        $settings_link = '<a href="' . admin_url('admin.php?page=trash_duplicates') . '">Settings</a>';
        array_unshift($actions, $settings_link);
    }
    return $actions;
}

add_filter('plugin_action_links', 'TDRD_settings_link', 10, 2);

// ---------- load scripts and styles ----------
function TDRD_enqueue() {
    
    // register and enqueue stylesheet and scripts
    $trash_duplicates_options = get_option('trash_duplicates_options');
    wp_register_style('admin_css', plugins_url('/css/trash-duplicates.css', __FILE__), false, $trash_duplicates_options['version']);
    wp_enqueue_style('admin_css');
    wp_enqueue_script('admin_js', plugins_url('/js/trash-duplicates.js', __FILE__), array('jquery'), $trash_duplicates_options['version']);
}

add_action('admin_enqueue_scripts', 'TDRD_enqueue');
TDRD_table();
// Admin Main Function Start

function TDRD_admin_func() {
    global $trash_final_result;
    $group = 1;
    global $wpdb;
    // To trash multiple posts from DB 
    if ( ( isset($_REQUEST['take_action']) || isset($_REQUEST['take_action2']) ) && isset($_REQUEST['chk_remove_sel']) && ( esc_html($_REQUEST['duplicates-action-top2']) != 'none') || (isset($_REQUEST['duplicates-action-top']) && esc_html($_REQUEST['duplicates-action-top']) != 'none') )
        TDRD_selected(esc_html($_REQUEST['chk_remove_sel']));
    
    // Trashing individual items.
    if (isset($_REQUEST['trash-id']) && esc_html($_REQUEST['action']) == 'trashing')
        TDRD_individual(absint($_REQUEST['trash-id']));
    
    // Trashing all items.
    if (count($_REQUEST))
        TDRD_trash_all();
    // get the options

    $trash_duplicates_options = get_option('trash_duplicates_options');

    // get the list of custom post types, attachments etc.

    $trash_duplicates_post_types = get_post_types(array('show_ui' => true));

    // set up the database details
    $tbl_nm = $wpdb->prefix . 'posts';


    // set query variable based on post types 
    if (isset($_REQUEST['trash-post-types']) && esc_html($_REQUEST['trash-post-types']) != '0') {
        $post_type = esc_html($_REQUEST['trash-post-types']);
        $trash_post_type = $wpdb->prepare("$tbl_nm.post_type = %s", $post_type);
    } else {
        $post_type = 0;
        $post_type_array = array();
        foreach ($trash_duplicates_post_types as $key => $value) {
            $post_type_array[] = $wpdb->prepare("$tbl_nm.post_type = '%s'", $key);
        }
        $trash_post_type = implode($post_type_array, ' OR ');
        $trash_post_type = '(' .$trash_post_type. ')';
    }

    // For filter post as per search parameter
    if (isset($_REQUEST['tra-search-submit']) && isset($_REQUEST['tra-search-input'])) {
        $trash_duplicates_search_query = esc_html($_REQUEST['tra-search-input']);
        $main_search_query = $wpdb->prepare(" AND $tbl_nm.post_title LIKE %s", '%' . $wpdb->esc_like($trash_duplicates_search_query) . '%');
    } 
    else 
    {
        $trash_duplicates_search_query = '';
        $main_search_query = '';
    }

    // setup variable and SQL string based for show draft
    if (isset($_REQUEST['show-draft']) && esc_html($_REQUEST['show-draft']) == 1) {
        $show_drafts = 1;
        $show_drafts_query = "($tbl_nm.post_status ='publish' OR $tbl_nm.post_status ='draft')";
    } else {
        $show_drafts = 0;
        $show_drafts_query = "$tbl_nm.post_status ='publish'";
    }
    
    // Number of duplicate titles
    $trash_count_record = "SELECT COUNT(post_title) FROM ( SELECT post_title FROM $tbl_nm WHERE $trash_post_type $main_search_query AND $show_drafts_query GROUP BY post_title HAVING COUNT(*)>1 ) AS cn";
    $trash_count_result = $wpdb->get_var($trash_count_record);

    // pagination-code
	if ( isset( $_GET[ 'page' ] ) && absint( $_GET[ 'page' ] ) ) :
            $show_num = absint($_GET[ 'page' ]);
	else :
            $show_num = 3;
	endif;
        
	if ( isset( $_REQUEST[ 'number' ] ) && intval( $_REQUEST[ 'number' ] ) && $_REQUEST[ 'number' ] > 1 ) :                          
            
            $page = intval( $_REQUEST[ 'number' ]);
            if ( $page < 1 ){
                $page = 1;
            }    
            elseif ( ( $page - 1 ) * $show_num >= $trash_count_result )
            {
                $page = ceil( $trash_count_result / $show_num );
            }         
            $offset = ( $page - 1 ) * $show_num;
	else :
            $offset = 0;
            $page = 1;
	endif;        
	$pages_all = ceil( $trash_count_result / $show_num );
    // get the duplicates from the Database
    $trash_duplicates_query = "SELECT * FROM $tbl_nm AS first INNER JOIN ( SELECT post_title FROM $tbl_nm WHERE $trash_post_type $main_search_query AND $show_drafts_query GROUP BY post_title HAVING COUNT(*)>1 LIMIT $offset, $show_num ) AS second ON first.post_title = second.post_title 
		WHERE " . str_replace($tbl_nm, 'first', $trash_post_type) . " AND " . str_replace($tbl_nm, 'first', $show_drafts_query) . "
		ORDER BY first.post_title, first.post_date DESC";
   
    $trash_final_result = $wpdb->get_results($trash_duplicates_query, ARRAY_A);
    
    $count_individual_query = "SELECT COUNT(*) FROM $tbl_nm AS first INNER JOIN ( SELECT post_title FROM $tbl_nm WHERE $trash_post_type $main_search_query AND $show_drafts_query GROUP BY post_title HAVING COUNT(*)>1 ) AS second ON first.post_title = second.post_title WHERE " . str_replace($tbl_nm, 'first', $trash_post_type) . " AND " . str_replace($tbl_nm, 'first', $show_drafts_query);
    $trash_individual_result_count = $wpdb->get_var($count_individual_query);

    // if no duplicates.
    if ($trash_count_result == 0 && empty($trash_duplicates_search_query)) : ?>
       <div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> Congratulations you have no duplicate posts!<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
    <?php 
    endif;    
    ?>

    <div class="trash-duplicates-wrap">
        <div class="trash-board-header">
            <h2><img src="<?php echo plugins_url('/images/trash-copy-2.png', __FILE__); ?>" alt="Trash Duplicates icon" />Trash Duplicates</h2>
        </div>
        <div class="trash-duplicates-inner">
            <form id="sol-trash-duplicates-form" class="sol-trash-duplicates-form" method="POST" action="#">
                <div class="trash-inner-top">
                    <div class="trash-duplicates-result">
                        <h2>Total Duplicate Posts: <span>(<?php echo $trash_count_result; ?>)</span> | Total Individual Posts: <span>(<?php echo $trash_individual_result_count; ?>)</span></h2>
                    </div>
                    <div class="trash-duplicates-search">
                        <p class="post-search-box">
                            <input id="tra-search-input" type="search" name="tra-search-input" placeholder="Post title" value="<?php echo $trash_duplicates_search_query; ?>" />
                            <input id="tra-search-submit" class="button" name="tra-search-submit" type="submit" value="Search Duplicates" />
                        </p>
                    </div>
                </div>
                <div class="sol-action-options">

                    <select name="duplicates-action-top">
                        <option selected="selected" value="none">Bulk Actions</option>
                        <option value="trash">Move to Trash</option>
                        <option value="delete_pr">Delete Permanently</option>
                    </select>
                    <input id="take_action" class="button action" name="take_action" type="submit" value="Apply" >
                    <select name="trash-post-types">
                        <option <?php if ($post_type == 0) echo 'selected="selected" '; ?>value="0">All post types</option>
                        <?php
                        // add custom post types to the options.
                        foreach ($trash_duplicates_post_types as $key => $value) :
                            echo '<option value="' . $key . '"';
                            if ($post_type === $key)
                                echo ' selected="selected"';echo '>' . $value . '</option>' . "\n";
                        endforeach;
                        ?>
                    </select>
                    <label>
                        <input id="show-draft" type="checkbox" value="1" <?php if ($show_drafts) echo 'checked="checked" '; ?> name="show-draft">Show drafts
                    </label>
                    <input id="submit-post-type" class="button action" name="filter-post" type="submit" value="Filter Posts">
                    <?php if($pages_all > 0) { ?>
                    <div class='nav-paging<?php if ( $pages_all <= 1 ) echo 'one'; ?> nav-paging-display'>
                        <div class="page_link">
                            <a class="first<?php if ( $pages_all > 1 &&  $page == 1 ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => '1' ) ) ); ?>" title="First">&laquo;</a>
                            <a class="previous<?php if ( $pages_all > 1 &&  $page == 1 ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => $page - 1, 'trash-post-types' => $post_type, 'show-draft' => $show_drafts) ) ); ?>" title="Previous">&lsaquo;</a>
                            <span class="paging-input"><input class="current-page" type="text" name="number" value="<?php echo $page; ?>" title="Current" size="1"/> Of <span class="total-pages"><?php echo $pages_all; ?></span></span>
                            <input type="submit" name="go_to_page" class="button" value="Go to" />
                            <a class="next<?php if ( $pages_all > 1 &&  $page == $pages_all ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => $page + 1, 'trash-post-types' => $post_type, 'show-draft' => $show_drafts) ) ); ?>" title="Next">&rsaquo;</a>
                            <a class="last<?php if ( $pages_all > 1 &&  $page == $pages_all ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => $pages_all, 'trash-post-types' => $post_type, 'show-draft' => $show_drafts) ) ); ?>" title="Last">&raquo;</a>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="trash-duplicates-top-field nav-top">
                    <fieldset class="field-one">
                        <strong>Trash:</strong> 
                        <label class="trash-duplicate1"><input type="checkbox" value="0" name="delete-all-duplicates" id="delete-all-duplicate"> All </label>
                    </fieldset>
                    <fieldset class="field-two">
                        <strong>And Keep:</strong> 
                        <label class="trash-duplicate1"><input type="radio" checked="checked" value="0" name="keep-all-duplicates"> Oldest </label>
                        <label class="trash-duplicate2"><input type="radio" value="1" name="keep-all-duplicates"> Newest</label>
                    </fieldset>
                    <label style="margin-left:80px;"><input type="submit" value="Apply" class="button action" name="duplicate-items-apply" id="duplicate-items-apply"></label>
                </div>
                <div class="trash-inner-table">
                    <table cellspacing="0" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-col" id="chk-col"><input type="checkbox" name="chk_remove_all" id="chk_remove_all"></th>
                                <th class="col-title" id="title">Title</th>
                                <th class="col-post-id" id="post-id">ID</th>
                                <th class="col-author" id="author">Author</th>
                                <th class="col-categories" id="categories">Categories</th>
                                <th class="col-comment num" id="comments"><span class="vers comment-grey-bubble"></span></th>
                                <th class="col-date" id="date">Last Modified</th>
                            </tr>
                        </thead>
                            <?php
                            $key_z=$key_y=$cntkey=$var_key='';
                            foreach ($trash_final_result as $key => $value) :
                                if($key != 0){
                                    $key_z = trim(strtolower($trash_final_result[ $key - 1 ][ 'post_title' ]));
                                }
                                if ( $key_z != trim(strtolower($value[ 'post_title' ])) ) {
                                         
                            ?>
                        <tbody class="trash_group">
                            <tr class="trash-duplicates-row" >
                                <th id="chk-ind" class="check-column"></th>
                                <th>
                                    <strong><?php echo $value[ 'post_title' ]; ?></strong>
                                <?php
                                    // Looping through the results.
                                    $count_ind = 0;
                                    $trash_duplicates_ary = array();
                                    foreach ( $trash_final_result as $key_ind => $value_ind ) {
                                        $str1 = trim( strtolower( $trash_final_result[ $key_ind ][ 'post_title' ] ) );
                                        $str2 = trim( strtolower( $value[ 'post_title' ] ) );
                                        if ( $str1 == $str2 ) {
                                            $trash_duplicates_ary[] = $trash_final_result[ $key_ind ][ 'ID' ]; 
                                                $count_ind++;
                                        }
                                    }
                                    $trash_duplicates_str = implode(",", $trash_duplicates_ary);
                                    echo '<span class="trash_post_total">[ '.$count_ind.' posts]</span>';
                                ?><input type="hidden" name="get_group_id_<?php echo $group; ?>" value="<?php echo $trash_duplicates_str; ?>" />
                                <a id="trash_group_ind_<?php echo $group + 1; ?>" class="trash_post_expand"><img src="<?php echo plugins_url('/images/down.png', __FILE__) ?>" alt="Down" /></a>
                                </th>
                                <th>
                                    <strong>Keep:</strong> 
                                    <label class="trash-duplicate1"><input type="radio" id="duplicate_old_keep_<?php echo $group; ?>" name="duplicate_keep[<?php echo $group; ?>]" value="0" checked="checked" /> Oldest </label></th>
                                        
                                <th>
                                    <label class="trash-duplicate2"><input type="radio" id="duplicate_new_keep_<?php echo $group; ?>" name="duplicate_keep[<?php echo $group; ?>]" value="1" /> Newest</label>
                                        
                                </th>
                                <th>
                                    <label><input type="submit" id="duplicate_apply_<?php echo $group; ?>" name="duplicate_apply_<?php echo $group; ?>" class="button-secondary action" value="Apply"/></label>
                                </th>
                                <th></th>
                                <th></th>
                            </tr>
                            
                            <?php
                            $group++;
                            }; ?>
                            
                                <tr class="trash_ind_group_<?php echo $group; ?>" >
                                    <th class="check-column" id="chk-ind">
                                        <input type="checkbox" class="chk_box" name="chk_remove_sel[]" value="<?php echo $value['ID']; ?>">
                                    </th>
                                    <td>
                                        <strong class="post-title"><a href="post.php?post=<?php echo $value['ID']; ?>&action=edit" ><?php echo $value['post_title']; ?></a></strong>
                                        <div class="row-actions">
                                            <span class="view"><a class="view" href="<?php echo get_permalink( absint( $value[ 'ID' ] ) ); ?>" rel="permalinks">View | </a></span>
                                            <span class="trash"><a class="trash_red" href="<?php echo esc_url( add_query_arg( array( 'action' => 'trashing', 'trash-id' => absint( $value[ 'ID' ] ) ) ) ); ?>">Trash</a></span>
                                        </div>
                                    </td>
                                    <td><?php echo $value['ID']; ?></td>
                                    <td><?php the_author_meta( 'display_name', $value[ 'post_author' ] ); ?></td>
                                    <td>
                                        <?php
                                        $trash_duplicates_category = wp_get_post_categories($value['ID']);
                                        if ($trash_duplicates_category) :
                                            $cats = array();
                                            foreach ($trash_duplicates_category as $c) :
                                                $cat = get_category($c);
                                                $cats[] = array('name' => $cat->name, 'slug' => $cat->slug);
                                            endforeach;
                                            foreach ($cats as $c1) :
                                                echo '<a href="' . esc_url('edit.php?category_name=' . $c1['slug']) . '">' . esc_html($c1['name']) . '</a>';
                                            endforeach;

                                        else :
                                            echo '<a href="edit.php?category_name=uncategorized">Uncategorized</a>';
                                        endif;
                                        ?>
                                    </td>
                                    <td style="text-align: center;"><?php echo $value['comment_count']; ?></td>
                                    <td><?php echo $value['post_modified']; ?></td>
                                </tr>
                        <?php
                        $cntkey = $key + 1;
                        if($cntkey < count($trash_final_result)){
                           $var_key = trim(strtolower($trash_final_result[ $key + 1 ][ 'post_title' ])); 
                        }
                        if ( $var_key != trim(strtolower($value[ 'post_title' ])) ) : ?>            
                        </tbody>
                        <?php 
                        endif;
                        endforeach; ?>
                    </table>
                </div>
                <?php if($pages_all > 0) { ?>
                <div class="trash-inner-bottom">
                    <select name="duplicates-action-top2">
                        <option selected="selected" value="none">Bulk Actions</option>
                        <option value="trash">Move To Trash</option>
                        <option value="delete_pr">Delete Permanently</option>
                    </select>
                    <input id="take_action2" class="button action" name="take_action2" type="submit" value="Apply" >
                    <div class='nav-paging<?php if ( $pages_all <= 1 ) echo 'one'; ?> nav-paging-display'>
                        <div class="page_link">
                            <a class="first<?php if ( $pages_all > 1 &&  $page == 1 ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => '1') ) ); ?>" title="First">&laquo;</a>
                            <a class="previous<?php if ( $pages_all > 1 &&  $page == 1 ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => $page - 1, 'trash-post-types' => $post_type, 'show-draft' => $show_drafts) ) ); ?>" title="Previous">&lsaquo;</a>
                            <span class="paging-input"><?php echo $page; ?> Of <?php echo $pages_all; ?></span>
                            <a class="next<?php if ( $pages_all > 1 &&  $page == $pages_all ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => $page + 1, 'trash-post-types' => $post_type, 'show-draft' => $show_drafts) ) ); ?>" title="Next">&rsaquo;</a>
                            <a class="last<?php if ( $pages_all > 1 &&  $page == $pages_all ) echo 'disabled'; ?>" href="<?php echo esc_url( add_query_arg( array( 'number' => $pages_all, 'trash-post-types' => $post_type, 'show-draft' => $show_drafts) ) ); ?>" title="Last">&raquo;</a>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <?php wp_nonce_field('trash_duplicates_main_form_nonce', '_wpnonce', false); ?>
            </form>
        </div>
        
    </div>

    <?php
}

function TDRD_individual($trash_ind_id){
    if ( wp_trash_post( $trash_ind_id ) === FALSE ) : ?>        
        <div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> while moving following post to the trash.(<?php echo $trash_ind_id; ?>)<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
    <?php else : ?>
        <div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> Following post moved to the trash.(<?php echo $trash_ind_id; ?>)<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
    <?php
    endif;
		
}

function TDRD_selected($trash_sel_id){
    $trash_del_action='';
    global $wpdb;
    $tbl_nm = $wpdb->prefix . 'posts';
    if(isset($_REQUEST['duplicates-action-top']) && esc_html($_REQUEST['duplicates-action-top']) != 'none'){
        $trash_del_action = esc_html($_REQUEST['duplicates-action-top']);
    }
    else{
        if(isset($_REQUEST['duplicates-action-top2'])){
        $trash_del_action = esc_html($_REQUEST['duplicates-action-top2']);
        }
    }
    if ($trash_sel_id) :
        $remove_id = esc_html($_REQUEST['chk_remove_sel']);
        $count = count($remove_id);
        $removed_items = array();
        if($trash_del_action == 'trash'){
            for ($i = 0; $i < $count; $i++) :
                wp_trash_post($remove_id[$i]);
                $removed_items[] = $remove_id[$i];
            endfor;
        }
        else {
            for ($i = 0; $i < $count; $i++) :
                $del_permanent = $remove_id[$i];
                $wpdb->delete( $tbl_nm, array( 'ID' => $del_permanent ) );
                $removed_items[] = $remove_id[$i];
            endfor;
        }
        if($removed_items) :
           $trashed_items = implode(",",$removed_items);
            ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> Following post moved to the trash.(<?php echo $trashed_items; ?>)<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
        <?php endif;
    endif;
		
}
function TDRD_trash_all(){
    global $wpdb;
    $success=$error=0;
    $in_tbl = $wpdb->prefix . 'tdrd_redirection';
    $old_url=$new_url=$keep_uri="";
    if( isset($_REQUEST['duplicate-items-apply']) && isset($_REQUEST['delete-all-duplicates']) )
    {
        $var = absint($_REQUEST['keep-all-duplicates']);
        $min_max='';
        if($var == 1){
            $min_max = 'MAX';
        }
        else{
            $min_max = 'MIN';
        }
        global $wpdb;
            $tbl_nm = $wpdb->prefix . 'posts';
            $trash_duplicates_post_types = get_post_types(array('show_ui' => true));
            if (isset($_REQUEST['trash-post-types']) && esc_html($_REQUEST['trash-post-types']) != '0') {
                $post_type = esc_html($_REQUEST['trash-post-types']);
                $post_type_string = $wpdb->prepare("post_tbl.post_type = %s", $post_type);
                $post_type_str = $wpdb->prepare("$tbl_nm.post_type = %s", $post_type);
                $where = "post_type = '".$post_type."'";
            } 
            else 
            {
                $post_type = 0;
                $where = $post_type_string;
                $post_type_array = array();
                $post_type_ar = array();
                foreach ($trash_duplicates_post_types as $key => $value) {
                    $post_type_array[] = $wpdb->prepare("post_tbl.post_type = '%s'", $key);
                    $post_type_ar[] = $wpdb->prepare("$tbl_nm.post_type = '%s'", $key);
                }

                $post_type_string = '(' . implode($post_type_array, ' OR ') . ')';
                $post_type_str = '(' . implode($post_type_ar, ' OR ') . ')';
            }
            $select_query = "SELECT * FROM $tbl_nm WHERE $post_type_str AND $tbl_nm.post_status ='publish' AND ID NOT IN (SELECT * FROM (SELECT $min_max(post_tbl.ID) FROM $tbl_nm post_tbl WHERE $post_type_string GROUP BY post_tbl.post_title) del)";
            $select_qry = "SELECT ID FROM $tbl_nm WHERE $post_type_str AND $tbl_nm.post_status ='publish' AND ID IN (SELECT * FROM (SELECT $min_max(post_tbl.ID) FROM $tbl_nm post_tbl WHERE $post_type_string GROUP BY post_tbl.post_title HAVING COUNT(*)>1) del)";
            $myrows = $wpdb->get_results( $select_query, ARRAY_A );
            $myrow1 = $wpdb->get_results( $select_qry, ARRAY_A );
            
            foreach ($myrow1 as $ind_id){
                $keep_uri = get_permalink($ind_id['ID']);
                $post_cate = wp_get_post_categories($ind_id['ID']);
                $title = get_the_title($ind_id['ID']);
                foreach ($myrows as $in_id){
                    $title_to = get_the_title($in_id['ID']);
                    $post_slug = get_permalink($in_id['ID']);
                    $home = home_url();
                    $trimmed_str = str_replace($home, '', $post_slug);
                    if($title == $title_to)
                    {
                        $old_url = $trimmed_str;
                        if($wpdb->insert( 
                                  $in_tbl, 
                                    array( 
                                        'old_url' => $old_url, 
                                        'new_url' => $keep_uri,
                                        'date_time' => current_time('mysql', 1) 
                                    ) 
                                ) === FALSE){
                            $error = 1;    
                                }
                        else {
                            $success = 1;
                        }
                        
                    }
                }
            }
            if($success == 1){
                $success = 0;
                ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> All post moved to the trash.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php              
            }
            else
            {
                $error = 0;
                ?><div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> Can't move to the trash.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
            }
            // delete all duplicates from the db
            $delete_query = "DELETE FROM $tbl_nm WHERE $post_type_str AND ID NOT IN (SELECT * FROM (SELECT $min_max(post_tbl.ID) FROM $tbl_nm post_tbl WHERE $post_type_string GROUP BY post_tbl.post_title) del)";
            $count = $wpdb->query($delete_query);
    }
    else
    {
    global $wpdb;
    $in_tbl = $wpdb->prefix . 'tdrd_redirection';
    foreach( $_POST as $key => $value ) {
        if ( strspn( $key, 'duplicate_apply_' ) == 16 ) 
        {
            $group_key = $key;
            $int_num = filter_var($group_key, FILTER_SANITIZE_NUMBER_INT);
            $keep_type = esc_html($_REQUEST['duplicate_keep']);
            $keep_type_val = $keep_type[$int_num];
            $group_str = "get_group_id_".$int_num."";
            $array_from_group = array();
            $array_from_group = explode(",", $_REQUEST[$group_str]);
            if($keep_type_val == 0){
                $keep_elem_id = min($array_from_group);
                $keep_uri = get_permalink($keep_elem_id);
                $elem_to_remove = array_diff($array_from_group, array($keep_elem_id));
                foreach ($elem_to_remove as $row_remove){
                    $post_cate = wp_get_post_categories($row_remove);
                    $post_slug = get_permalink($row_remove);
                    $home = home_url();
                    $trimmed = str_replace($home, '', $post_slug);
                    $old_url = $trimmed;
                    if($wpdb->insert( 
                                  $in_tbl, 
                                    array( 
                                        'old_url' => $old_url, 
                                        'new_url' => $keep_uri,
                                        'date_time' => current_time('mysql', 1) 
                                    ) 
                            ) === FALSE)
                        {
                            $error = 1;
                        }
                        else{
                            $success = 1;
                        }
                    wp_trash_post($row_remove);
                }
                if($success == 1){
                    $success = 0;
                    ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> All post moved to the trash.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php              
                }
                else{
                    $error = 0;
                    ?><div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> Can't move to the trash.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
                }
            }
            else{
                $keep_elem_id = max($array_from_group);
                $keep_uri = get_permalink($keep_elem_id);
                $elem_to_remove = array_diff($array_from_group, array($keep_elem_id));
                foreach ($elem_to_remove as $row_remove){
                    $post_cate = wp_get_post_categories($row_remove);
                    $post_slug = get_permalink($row_remove);
                    $home = home_url();
                    $trimmed = str_replace($home, '', $post_slug);
                    $old_url = $trimmed;
                    if($wpdb->insert( 
                                  $in_tbl, 
                                    array( 
                                        'old_url' => $old_url, 
                                        'new_url' => $keep_uri,
                                        'date_time' => current_time('mysql', 1) 
                                    ) 
                            ) === FALSE)
                        {
                            $error = 1;
                        }
                        else{
                            $success = 1;
                        }
                    wp_trash_post($row_remove);
                }
                if($success == 1){
                    $success = 0;
                    ?><div class="updated notice is-dismissible below-h2" id="message"><p><strong>Success:</strong> All post moved to the trash.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php              
                }
                else{
                    $error = 0;
                    ?><div class="error notice is-dismissible below-h2" id="message"><p><strong>Error:</strong> Can't move to the trash.<button class="notice-dismiss" type="button"><span class="screen-reader-text">Dismiss this notice.</span></button></div><?php
                }
            }
        }
    }
    }
}

function TDRD_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'tdrd_redirection';

    $sql_create = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
      ID int(15) NOT NULL AUTO_INCREMENT,
      old_url VARCHAR(50),
      new_url VARCHAR(100),
      date_time datetime,
      PRIMARY KEY  (ID)
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_create);
}

register_activation_hook(__FILE__, 'TDRD_table');
?>