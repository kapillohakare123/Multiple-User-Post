<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Plugin Name: Multiple User Post
 * Plugin URI: #
 * Description: This plugin will assign the multiple users to post.
 * Version: 1.0
 * Author: Kapil Lohakare
 * License: GPL2
 */
// Add files for admin and frontend
//include(plugin_dir_path(__FILE__) . '/widget-last-visited.php' );
// Add scripts
function multiple_user_post_enqueue_scripts() {
    wp_enqueue_script("jquery");
    wp_enqueue_script('jquery-ui', plugins_url('js/jquery-ui.js', __FILE__));
    // Register the script
    wp_register_script('script_handle', plugins_url('js/custom.js', __FILE__));
// Localize the script with new data
    $translation_array = array(
        'ajaxurl' => admin_url('admin-ajax.php')
    );
    wp_localize_script('script_handle', 'script_object', $translation_array);
    wp_enqueue_script('script_handle');
}

//This hook ensures our scripts and styles are only loaded in the admin.
add_action('admin_enqueue_scripts', 'multiple_user_post_enqueue_scripts');

add_action('wp_ajax_multiple_user_post_getpage', 'multiple_user_post_getpage');
add_action('wp_ajax_nopriv_multiple_user_post_getpage', 'multiple_user_post_getpage');

function multiple_user_post_getpage() {
    $term = strtolower($_GET['term']);
    $suggestions = array();
    $args = array(
        'order' => 'ASC',
        'offset' => '',
        'search' => $term . "*",
        'number' => '',
        'count_total' => false
    );
    $users = get_users($args);
    foreach ($users as $user) {
        $suggestion = array();
        $suggestion['label'] = $user->display_name;
        $suggestion['id'] = $user->id;
        $suggestions[] = $suggestion;
    }
    $response = json_encode($suggestions);
    echo $response;
    exit();
}

function multiple_user_post_markup_admin($object) {
    wp_nonce_field(basename(__FILE__), "meta-box-nonce");
    $users = get_post_meta($object->ID, "meta-box-user-store", true);
    ?>
    <div>
        <div class="ui-widget">
            <label for="birds">Assign Users To this post: </label>
            <input id="birds">
        </div>

        <div class="ui-widget" style="margin-top:2em; font-family:Arial">
            <div id="log"  class="ui-widget-content"></div>
        </div>  
        <?php if ($users) { ?>
            <div>
                <b>Already Assigned Users:</b>
                <?php
                foreach ($users as $user) {
                    $user_info = get_user_by('id', $user);
                    echo "<div>$user_info->display_name</div>";
                }
                ?>
            </div>
        <?php } ?>
    </div>
    <?php
}

function multiple_user_post_save_admin($post_id, $post, $update) {
    if (!isset($_POST["meta-box-nonce"]) || !wp_verify_nonce($_POST["meta-box-nonce"], basename(__FILE__)))
        return $post_id;

    if (!current_user_can("edit_post", $post_id))
        return $post_id;

    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)
        return $post_id;

    $slug = "post";
    if ($slug != $post->post_type)
        return $post_id;
    $meta_box_text_value = "";
    $post_author_info = array();
    if (isset($_POST["meta-box-user-store"])) {
        $meta_box_text_value = $_POST["meta-box-user-store"];
        update_post_meta($post_id, "meta-box-user-store", $meta_box_text_value);
        $users = $_POST["meta-box-user-store"];
        foreach ($users as $user_id_new) {
            $title = get_the_title($post_id) . "--Copy For User--" . $user_id_new;
            // Create post object
            $my_post = array(
                'post_title' => $title,
                'post_content' => get_post_field('post_content', $post_id),
                'post_status' => 'unread',
                'post_author' => $user_id_new,
                'post_type' => 'unread',
            );
// Insert the post into the database
            if (!get_page_by_title($title, 'OBJECT', 'post')) {
                //Nice function to get a workaround for creating number of posts in DB
                remove_action(current_filter(), __FUNCTION__);
                $post_author_info[$user_id_new] = wp_insert_post($my_post);
                //Notify User For this
                $user_info = get_user_by('id', $user_id_new);
                $multiple_recipients = array(
                    $user_info->user_email
                );
                $subj = 'The Following Post Needs Your Suggestions';
                $body = 'Please click on below link to suggest edits: ' . get_edit_post_link($post_author_info[$user_id_new]);
                wp_mail($multiple_recipients, $subj, $body);
            }
        }

        update_post_meta($post_id, "meta-box-user-approval", $post_author_info);
    }
}

add_action("save_post", "multiple_user_post_save_admin", 10, 3);

function multiple_user_post_add_meta_admin() {
    add_meta_box("multiple-user-post-meta-box", "Multiple User Post Meta Box", "multiple_user_post_markup_admin", "post", "side", "high", null);
    add_meta_box("multiple-user-meta-box-content", "User Content Sent For Approval", "multiple_user_post_meta_box_content_admin", "post", "normal", "high", null);
}

add_action("add_meta_boxes", "multiple_user_post_add_meta_admin");

function multiple_user_post_meta_box_content_admin($object) {
    $users_posts = get_post_meta($object->ID, "meta-box-user-approval", true);
    if ($users_posts) {
        foreach ($users_posts as $user => $post_id) {
            $user_info = get_user_by('id', $user);
            ?>
            <h3>Inputs From User: <i><?php echo $user_info->display_name; ?></i> </h3>
            <?php
            $user_content = get_post_field('post_content', $post_id);
            wp_editor($user_content, 'user_editor_' . $user, $settings = array());
            ?>
            <?php
        }
    } else {
        ?>
        <h3>No Content Found.</h3>
        <?php
    }
}

function multiple_user_post_status() {
    register_post_status('unread', array(
        'label' => _x('Unread', 'post'),
        'public' => false,
        'private' => true,
        'internal' => true,
        'protected' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Unread <span class="count">(%s)</span>', 'Unread <span class="count">(%s)</span>'),
    ));
}

add_action('init', 'multiple_user_post_status');


add_action('init', 'multiple_user_post_unread_init');

/**
 * Register a unread post type.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type
 */
function multiple_user_post_unread_init() {
    $labels = array(
        'name' => _x('Unread', 'post type general name', 'multiple-user-post'),
        'singular_name' => _x('Unread', 'post type singular name', 'multiple-user-post'),
        'menu_name' => _x('Unreads', 'admin menu', 'multiple-user-post'),
        'name_admin_bar' => _x('Unread', 'add new on admin bar', 'multiple-user-post'),
        'add_new' => _x('Add New', 'unread', 'multiple-user-post'),
        'add_new_item' => __('Add New Unread', 'multiple-user-post'),
        'new_item' => __('New Unread', 'multiple-user-post'),
        'edit_item' => __('Edit Unread', 'multiple-user-post'),
        'view_item' => __('View Unread', 'multiple-user-post'),
        'all_items' => __('All Unreads', 'multiple-user-post'),
        'search_items' => __('Search Unreads', 'multiple-user-post'),
        'parent_item_colon' => __('Parent Unreads:', 'multiple-user-post'),
        'not_found' => __('No unreads found.', 'multiple-user-post'),
        'not_found_in_trash' => __('No unreads found in Trash.', 'multiple-user-post')
    );

    $args = array(
        'labels' => $labels,
        'description' => __('Description.', 'multiple-user-post'),
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => false,
        'rewrite' => array('slug' => 'unread'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'author')
    );

    register_post_type('unread', $args);
}

/**
 * Flushes all Options
 *
 */
function multiple_user_post_activation() {
    //Under Contruction but no use for current scope
}

function multiple_user_post_deactivation() {
    //Under Contruction but no use for current scope
}

register_activation_hook(__FILE__, 'multiple_user_post_activation');
register_deactivation_hook(__FILE__, 'multiple_user_post_deactivation');
