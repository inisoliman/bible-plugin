<?php
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