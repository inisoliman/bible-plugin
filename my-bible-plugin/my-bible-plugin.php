<?php
/*
Plugin Name: My Bible Plugin
Description: عرض الكتاب المقدس مع البحث والشواهد
Version: 1.0
Author: اسمك
*/

// منع الوصول المباشر للملف
if (!defined('ABSPATH')) {
    exit;
}

// تفعيل الشورتكودات في الويدجت
add_filter('widget_text', 'do_shortcode');

// تحميل ملفات الـ CSS والـ JS
function bible_enqueue_scripts() {
    // تحميل jQuery
    wp_enqueue_script('jquery');

    // تحميل Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');

    // تحميل الـ CSS
    wp_enqueue_style('bible-styles', plugin_dir_url(__FILE__) . 'assets/css/bible-styles.css', array(), '1.0');

    // تحميل الـ JS
    wp_enqueue_script('bible-ajax', plugin_dir_url(__FILE__) . 'assets/js/bible-ajax.js', array('jquery'), '1.0', true);
    wp_enqueue_script('bible-copy', plugin_dir_url(__FILE__) . 'assets/js/bible-copy.js', array('jquery'), '1.0', true);

    // تمرير بيانات للـ JavaScript
    wp_localize_script('bible-ajax', 'bibleAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'base_url' => home_url('/bible/') // المسار الأساسي
    ));
}
add_action('wp_enqueue_scripts', 'bible_enqueue_scripts');

// تحميل ملفات الإضافة
require_once plugin_dir_path(__FILE__) . 'includes/rewrite.php';
require_once plugin_dir_path(__FILE__) . 'includes/templates.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';

// إنشاء جدول الآيات عند تفعيل الإضافة
function bible_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        book varchar(100) NOT NULL,
        chapter int NOT NULL,
        verse int NOT NULL,
        text text NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY book_chapter_verse (book, chapter, verse)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'bible_create_table');

// إضافة صفحتي القراءة وعرض الآيات عند تفعيل الإضافة
function bible_create_pages() {
    // إنشاء صفحة القراءة
    $bible_read_page = array(
        'post_title' => 'قراءة الكتاب المقدس',
        'post_name' => 'bible_read',
        'post_content' => '[bible_content]',
        'post_status' => 'publish',
        'post_type' => 'page',
    );
    if (!get_page_by_path('bible_read')) {
        wp_insert_post($bible_read_page);
    }

    // إنشاء صفحة عرض الآيات
    $bible_page = array(
        'post_title' => 'الكتاب المقدس',
        'post_name' => 'bible',
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'page',
    );
    if (!get_page_by_path('bible')) {
        wp_insert_post($bible_page);
    }
}
register_activation_hook(__FILE__, 'bible_create_pages');

// إضافة صفحة إعدادات في لوحة التحكم
function bible_plugin_settings_menu() {
    add_options_page(
        'إعدادات إضافة الكتاب المقدس',
        'إعدادات الكتاب المقدس',
        'manage_options',
        'bible-plugin-settings',
        'bible_plugin_settings_page'
    );
}
add_action('admin_menu', 'bible_plugin_settings_menu');

function bible_plugin_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");

    // حفظ الإعدادات
    if (isset($_POST['bible_settings_submit'])) {
        $selected_book = sanitize_text_field($_POST['bible_random_book']);
        update_option('bible_random_book', $selected_book);
        echo '<div class="updated"><p>تم حفظ الإعدادات بنجاح.</p></div>';
    }

    $selected_book = get_option('bible_random_book', '');
    ?>
    <div class="wrap">
        <h1>إعدادات إضافة الكتاب المقدس</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bible_random_book">اختر سفر للآيات العشوائية واليومية:</label></th>
                    <td>
                        <select name="bible_random_book" id="bible_random_book">
                            <option value="">كل الأسفار</option>
                            <?php foreach ($books as $book) : ?>
                                <option value="<?php echo esc_attr($book); ?>" <?php selected($selected_book, $book); ?>><?php echo esc_html($book); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">اختر سفر معين إذا كنت تريد أن تكون الآيات العشوائية واليومية من هذا السفر فقط.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="bible_settings_submit" class="button-primary" value="حفظ التغييرات">
            </p>
        </form>
    </div>
    <?php
}

// تسجيل الإعدادات
function bible_register_settings() {
    register_setting('bible-plugin-settings-group', 'bible_random_book');
}
add_action('admin_init', 'bible_register_settings');