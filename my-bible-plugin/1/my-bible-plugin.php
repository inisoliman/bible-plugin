<?php
/*
Plugin Name: My Bible Plugin
Description: عرض الكتاب المقدس مع البحث والشواهد
Version: 1.0
Author: اسمك
*/

// تفعيل الشورتكودات في الويدجت
add_filter('widget_text', 'do_shortcode');

// إضافة السكربتات والـ Styles
function bible_enqueue_scripts() {
    wp_enqueue_script('jquery'); // تأكد من تحميل jQuery
    wp_enqueue_script('bible-ajax', plugin_dir_url(__FILE__) . 'bible-ajax.js', array('jquery'), '1.0', true);
    wp_localize_script('bible-ajax', 'bibleAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));

    // إضافة Font Awesome لتحسين شكل الأزرار
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1');
}
add_action('wp_enqueue_scripts', 'bible_enqueue_scripts');

// عرض الآيات مع قوايم منسدلة بـ Ajax
function display_bible_content($atts) {
    global $wpdb, $wp_query;
    $table_name = $wpdb->prefix . 'bible_verses';

    // جيب كل الأسفار
    $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");

    // تحقق لو فيه كتاب وأصحاح في الرابط
    $selected_book = get_query_var('book') ? rawurldecode(sanitize_text_field(get_query_var('book'))) : '';
    $selected_chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : '';

    $output = '<div id="bible-container">';
    $output .= '<select id="bible-book" name="selected_book">';
    $output .= '<option value="">اختر السفر</option>';
    foreach ($books as $book) {
        $selected = ($book == $selected_book) ? 'selected' : '';
        $output .= "<option value='$book' $selected>$book</option>";
    }
    $output .= '</select>';

    // جيب الأصحاحات لو فيه كتاب محدد
    $chapters = [];
    if ($selected_book) {
        $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $selected_book));
    }

    $output .= ' <select id="bible-chapter" name="selected_chapter"><option value="">اختر الأصحاح</option>';
    foreach ($chapters as $chapter) {
        $selected = ($chapter == $selected_chapter) ? 'selected' : '';
        $output .= "<option value='$chapter' $selected>$chapter</option>";
    }
    $output .= '</select>';
    $output .= '<div id="bible-verses">';

    // لو فيه كتاب وأصحاح في الرابط، اعرض الآيات مباشرة
    if ($selected_book && $selected_chapter) {
        $verses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE book = %s AND chapter = %d",
            $selected_book, $selected_chapter
        ));

        $output .= '<div class="bible-controls">';
        $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
        $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
        $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
        $output .= '</div>';
        $output .= '<div id="verses-content">';
        $first_verse_text = '';
        foreach ($verses as $verse) {
            $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
            $verse_url = esc_url(home_url("/bible/" . urlencode($verse->book) . "/{$verse->chapter}/{$verse->verse}"));
            $output .= "<p class='verse-text' data-original-text='{$verse->text}' data-verse-url='{$verse_url}'><a href='{$verse_url}' class='verse-number'>{$verse->verse}.</a> {$verse->text} <a href='{$verse_url}'>[$reference]</a></p>";
            if (empty($first_verse_text)) {
                $first_verse_text = $verse->text;
            }
        }
        $output .= '</div>';

        // إضافة روابط الأصحاح السابق والتالي
        $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $selected_book));
        $current_chapter_index = array_search($selected_chapter, $chapters);
        
        $output .= '<div class="chapter-navigation">';
        if ($current_chapter_index > 0) {
            $prev_chapter = $chapters[$current_chapter_index - 1];
            $prev_url = esc_url(home_url("/bible/" . urlencode($selected_book) . "/{$prev_chapter}"));
            $output .= '<a href="' . $prev_url . '"><i class="fas fa-arrow-right"></i> الأصحاح السابق (' . $prev_chapter . ')</a>';
        }
        if ($current_chapter_index < count($chapters) - 1) {
            $next_chapter = $chapters[$current_chapter_index + 1];
            $next_url = esc_url(home_url("/bible/" . urlencode($selected_book) . "/{$next_chapter}"));
            $output .= '<a href="' . $next_url . '" style="float: left;"><i class="fas fa-arrow-left"></i> الأصحاح التالي (' . $next_chapter . ')</a>';
        }
        $output .= '</div>';
    }

    $output .= '</div>';
    $output .= '</div>';

    return $output;
}
add_shortcode('bible_content', 'display_bible_content');

// Ajax لجلب الأصحاحات والآيات
function bible_get_chapters() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $book = sanitize_text_field($_POST['book']);

    $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $book));
    wp_send_json($chapters);
}
add_action('wp_ajax_bible_get_chapters', 'bible_get_chapters');
add_action('wp_ajax_nopriv_bible_get_chapters', 'bible_get_chapters');

function bible_get_verses() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $book = sanitize_text_field($_POST['book']);
    $chapter = intval($_POST['chapter']);

    $verses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE book = %s AND chapter = %d",
        $book, $chapter
    ));

    $output = '<div class="bible-controls">';
    $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
    $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
    $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
    $output .= '</div>';
    $output .= '<div id="verses-content">';
    $first_verse_text = '';
    foreach ($verses as $verse) {
        $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
        $verse_url = esc_url(home_url("/bible/" . urlencode($verse->book) . "/{$verse->chapter}/{$verse->verse}"));
        $output .= "<p class='verse-text' data-original-text='{$verse->text}' data-verse-url='{$verse_url}'><a href='{$verse_url}' class='verse-number'>{$verse->verse}.</a> {$verse->text} <a href='{$verse_url}'>[$reference]</a></p>";
        if (empty($first_verse_text)) {
            $first_verse_text = $verse->text;
        }
    }
    $output .= '</div>';

    // إضافة روابط الأصحاح السابق والتالي
    $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $book));
    $current_chapter_index = array_search($chapter, $chapters);

    $output .= '<div class="chapter-navigation">';
    if ($current_chapter_index > 0) {
        $prev_chapter = $chapters[$current_chapter_index - 1];
        $prev_url = esc_url(home_url("/bible/" . urlencode($book) . "/{$prev_chapter}"));
        $output .= '<a href="' . $prev_url . '"><i class="fas fa-arrow-right"></i> الأصحاح السابق (' . $prev_chapter . ')</a>';
    }
    if ($current_chapter_index < count($chapters) - 1) {
        $next_chapter = $chapters[$current_chapter_index + 1];
        $next_url = esc_url(home_url("/bible/" . urlencode($book) . "/{$next_chapter}"));
        $output .= '<a href="' . $next_url . '" style="float: left;"><i class="fas fa-arrow-left"></i> الأصحاح التالي (' . $next_chapter . ')</a>';
    }
    $output .= '</div>';

    // رجع بيانات إضافية للـ SEO
    $response = array(
        'html' => $output,
        'title' => esc_html($book . ' ' . $chapter . ' - الكتاب المقدس'),
        'description' => esc_html(wp_trim_words($first_verse_text, 20, '...'))
    );
    wp_send_json($response);
}
add_action('wp_ajax_bible_get_verses', 'bible_get_verses');
add_action('wp_ajax_nopriv_bible_get_verses', 'bible_get_verses');

// البحث
function bible_search_form() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';

    $output = '<form method="get" action="' . esc_url(get_permalink()) . '">';
    $output .= '<input type="text" name="bible_search" placeholder="ابحث في الكتاب المقدس" value="' . (isset($_GET['bible_search']) ? esc_attr($_GET['bible_search']) : '') . '">';
    $output .= '<input type="submit" value="بحث">';
    $output .= '</form>';

    if (isset($_GET['bible_search']) && !empty($_GET['bible_search'])) {
        $search_term = sanitize_text_field($_GET['bible_search']);
        // إزالة التشكيل من النص المدخل
        $search_term_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $search_term);
        // توحيد الحروف العربية (إ, أ, آ إلى ا)
        $search_term_clean = str_replace(
            array('إ', 'أ', 'آ'),
            'ا',
            $search_term_clean
        );
        $search_term_like = '%' . $wpdb->esc_like($search_term_clean) . '%';

        // عدد النتايج في الصفحة
        $results_per_page = 10;
        $current_page = isset($_GET['search_page']) ? max(1, intval($_GET['search_page'])) : 1;
        $offset = ($current_page - 1) * $results_per_page;

        // البحث في عمود text وعمود book مع إزالة التشكيل وتوحيد الحروف
        $query = "SELECT COUNT(*) FROM $table_name WHERE ";
        $query .= "(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s ";
        $query .= "OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s)";
        $total_results = $wpdb->get_var($wpdb->prepare($query, $search_term_like, $search_term_like));

        $query = "SELECT * FROM $table_name WHERE ";
        $query .= "(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s ";
        $query .= "OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s) ";
        $query .= "LIMIT %d OFFSET %d";
        $results = $wpdb->get_results($wpdb->prepare($query, $search_term_like, $search_term_like, $results_per_page, $offset));

        if ($results) {
            $output .= '<h2>نتائج البحث (' . $total_results . ' نتيجة):</h2>';
            $output .= '<div class="bible-controls">';
            $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
            $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
            $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
            $output .= '</div>';
            $output .= '<div class="bible-content">';
            foreach ($results as $result) {
                $reference = $result->book . ' ' . $result->chapter . ':' . $result->verse;
                $verse_url = esc_url(home_url("/bible/" . urlencode($result->book) . "/{$result->chapter}/{$result->verse}"));
                $output .= '<p class="verse-text" data-original-text="' . esc_attr($result->text) . '" data-verse-url="' . $verse_url . '">';
                $output .= '<a href="' . $verse_url . '" class="verse-number">' . $result->verse . '.</a> ';
                $output .= $result->text . ' ';
                $output .= '<a href="' . $verse_url . '">[' . esc_html($reference) . ']</a>';
                $output .= '</p>';
            }
            $output .= '</div>';

            // إضافة الترقيم
            $total_pages = ceil($total_results / $results_per_page);
            if ($total_pages > 1) {
                $output .= '<div class="pagination">';
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active = ($i == $current_page) ? 'style="font-weight: bold;"' : '';
                    $output .= '<a href="' . esc_url(add_query_arg(array('bible_search' => $search_term, 'search_page' => $i))) . '" ' . $active . '>' . $i . '</a> ';
                }
                $output .= '</div>';
            }
        } else {
            $output .= '<p>لم يتم العثور على نتائج.</p>';
            $output .= '<p>الكلمة التي تم البحث عنها: ' . esc_html($search_term) . '</p>';
            $output .= '<p>الاستعلام المستخدم: ';
            $output .= '<code>SELECT * FROM ' . $table_name . ' WHERE (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, \'ً\', \'\'), \'َ\', \'\'), \'ُ\', \'\'), \'ِ\', \'\'), \'ْ\', \'\'), \'ّ\', \'\'), \'إ\', \'ا\'), \'أ\', \'ا\'), \'آ\', \'ا\') LIKE \'%' . esc_html($search_term_clean) . '%\' OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, \'ً\', \'\'), \'َ\', \'\'), \'ُ\', \'\'), \'ِ\', \'\'), \'ْ\', \'\'), \'ّ\', \'\'), \'إ\', \'ا\'), \'أ\', \'ا\'), \'آ\', \'ا\') LIKE \'%' . esc_html($search_term_clean) . '%\')</code></p>';
        }
    }

    return $output;
}
add_shortcode('bible_search', 'bible_search_form');

// قواعد إعادة الكتابة للروابط
function bible_rewrite_rules() {
    // قاعدة للآية الفردية
    add_rewrite_rule(
        '^bible/(.+)/([0-9]+)/([0-9]+)/?$',
        'index.php?pagename=bible&book=$matches[1]&chapter=$matches[2]&verse=$matches[3]',
        'top'
    );
    // قاعدة للأصحاح
    add_rewrite_rule(
        '^bible/(.+)/([0-9]+)/?$',
        'index.php?pagename=bible&book=$matches[1]&chapter=$matches[2]',
        'top'
    );
    // قاعدة لصفحة الكتاب المقدس بدون تحديد
    add_rewrite_rule(
        '^bible/?$',
        'index.php?pagename=bible',
        'top'
    );
}
add_action('init', 'bible_rewrite_rules');

function bible_query_vars($vars) {
    $vars[] = 'book';
    $vars[] = 'chapter';
    $vars[] = 'verse';
    return $vars;
}
add_filter('query_vars', 'bible_query_vars');

function bible_custom_template($template) {
    global $wp_query, $wpdb;

    // تحقق إذا كان الرابط يبدأ بـ /bible
    if (get_query_var('pagename') == 'bible') {
        $book = get_query_var('book') ? rawurldecode(sanitize_text_field(get_query_var('book'))) : null;
        $chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : null;
        $verse = get_query_var('verse') ? intval(get_query_var('verse')) : null;
        
        $table_name = $wpdb->prefix . 'bible_verses';

        // رسائل تصحيح
        error_log('Query Vars - Book: ' . $book . ', Chapter: ' . $chapter . ', Verse: ' . $verse);

        if ($book && $chapter && $verse !== null) {
            // عرض آية فردية
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE book = %s AND chapter = %d AND verse = %d",
                $book, $chapter, $verse
            ));

            // رسالة تصحيح للاستعلام
            error_log('Verse Query Result: ' . print_r($result, true));

            if ($result) {
                $wp_query->is_404 = false;
                $wp_query->is_single = true;

                // ضيف عنوان ديناميكي للصفحة
                add_filter('pre_get_document_title', function() use ($result) {
                    return esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse) . ' - الكتاب المقدس';
                }, 999);

                // ضيف Meta Description
                add_action('wp_head', function() use ($result) {
                    $description = esc_html(wp_trim_words($result->text, 20, '...'));
                    echo '<meta name="description" content="' . $description . '">';
                });

                ob_start();
                ?>
                <h1><?php echo esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse); ?></h1>
                <div class="bible-controls">
                    <button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>
                    <button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>
                    <button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>
                </div>
                <p class="verse-text" data-original-text="<?php echo esc_attr($result->text); ?>" data-verse-url="<?php echo esc_url(home_url("/bible/" . urlencode($result->book) . "/{$result->chapter}/{$result->verse}")); ?>">
                    <a href="<?php echo esc_url(home_url("/bible/" . urlencode($result->book) . "/{$result->chapter}/{$result->verse}")); ?>" class="verse-number"><?php echo $result->verse; ?>.</a> 
                    <?php echo esc_html($result->text); ?> 
                    <a href="<?php echo esc_url(home_url("/bible/" . urlencode($result->book) . "/{$result->chapter}/{$result->verse}")); ?>">
                        [<?php echo esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse); ?>]
                    </a>
                </p>
                <div class="chapter-navigation">
                    <a href="<?php echo esc_url(home_url("/bible/" . urlencode($result->book) . "/{$result->chapter}")); ?>"><i class="fas fa-book-open"></i> العودة إلى الأصحاح الكامل (<?php echo esc_html($result->book . ' ' . $result->chapter); ?>)</a>
                </div>
                <?php
                $content = ob_get_clean();

                // ضيف المحتوى للصفحة
                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            } else {
                // لو الآية مش موجودة، اعرض رسالة
                $wp_query->is_404 = false;
                $wp_query->is_single = true;

                ob_start();
                ?>
                <h1>الآية غير موجودة</h1>
                <p>لم يتم العثور على الآية: <?php echo esc_html($book . ' ' . $chapter . ':' . $verse); ?></p>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            }
        } elseif ($book && $chapter) {
            // عرض أصحاح كامل
            $verses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE book = %s AND chapter = %d",
                $book, $chapter
            ));

            if ($verses) {
                $wp_query->is_404 = false;
                $wp_query->is_page = true;

                // ضيف عنوان ديناميكي للصفحة
                add_filter('pre_get_document_title', function() use ($book, $chapter) {
                    return esc_html($book . ' ' . $chapter) . ' - الكتاب المقدس';
                }, 999);

                // ضيف Meta Description
                $first_verse = $wpdb->get_var($wpdb->prepare(
                    "SELECT text FROM $table_name WHERE book = %s AND chapter = %d AND verse = 1",
                    $book, $chapter
                ));
                add_action('wp_head', function() use ($first_verse, $book, $chapter) {
                    $description = $first_verse ? esc_html(wp_trim_words($first_verse, 20, '...')) : 'اقرأ ' . esc_html($book) . ' الأصحاح ' . $chapter . ' من الكتاب المقدس.';
                    echo '<meta name="description" content="' . $description . '">';
                });

                ob_start();
                ?>
                <h1><?php echo esc_html($book . ' ' . $chapter); ?></h1>
                <?php echo do_shortcode('[bible_content]'); ?>
                <?php
                $content = ob_get_clean();

                // ضيف المحتوى للصفحة
                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            } else {
                // لو الأصحاح مش موجود، اعرض رسالة
                $wp_query->is_404 = false;
                $wp_query->is_page = true;

                ob_start();
                ?>
                <h1>الأصحاح غير موجود</h1>
                <p>لم يتم العثور على الأصحاح: <?php echo esc_html($book . ' ' . $chapter); ?></p>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            }
        } else {
            // عرض صفحة الكتاب المقدس الرئيسية
            $wp_query->is_404 = false;
            $wp_query->is_page = true;

            add_filter('pre_get_document_title', function() {
                return 'الكتاب المقدس - اختر سفر وأصحاح';
            }, 999);

            add_action('wp_head', function() {
                echo '<meta name="description" content="اقرأ الكتاب المقدس، اختر السفر والأصحاح لقراءة الآيات، أو ابحث عن كلمة معينة.">';
            });

            ob_start();
            ?>
            <h1>الكتاب المقدس</h1>
            <?php echo do_shortcode('[bible_content]'); ?>
            <?php
            $content = ob_get_clean();

            add_filter('the_content', function() use ($content) {
                return $content;
            });

            return get_page_template();
        }
    }

    return $template;
}
add_filter('template_include', 'bible_custom_template');

// إضافة الشواهد عند النسخ
function bible_copy_script() {
    wp_enqueue_script('bible-copy', plugin_dir_url(__FILE__) . 'bible-copy.js', array(), '1.0', true);
}
add_action('wp_enqueue_scripts', 'bible_copy_script');

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

// شورتكود لآية عشوائية
function random_verse_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $selected_book = get_option('bible_random_book', '');

    if ($selected_book) {
        $verse = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE book = %s ORDER BY RAND() LIMIT 1", $selected_book));
    } else {
        $verse = $wpdb->get_row("SELECT * FROM $table_name ORDER BY RAND() LIMIT 1");
    }

    if ($verse) {
        $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
        $verse_url = esc_url(home_url("/bible/" . urlencode($verse->book) . "/{$verse->chapter}/{$verse->verse}"));
        $output = "<p class='random-verse verse-text' data-original-text='{$verse->text}' data-verse-url='{$verse_url}'><a href='{$verse_url}' class='verse-number'>{$verse->verse}.</a> {$verse->text} <a href='{$verse_url}'>[$reference]</a></p>";
        return $output;
    }
    return '<p>لم يتم العثور على آية.</p>';
}
add_shortcode('random_verse', 'random_verse_shortcode');

// شورتكود لآية يومية (تتغير مرة واحدة كل يوم)
function daily_verse_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $selected_book = get_option('bible_random_book', '');

    // احسب اليوم الحالي (بتوقيت الموقع)
    $current_date = current_time('Y-m-d');
    $transient_key = 'daily_verse_' . $current_date;

    // جرب جيب الآية من الـ Transient
    $verse_data = get_transient($transient_key);
    if (false === $verse_data) {
        // لو مفيش آية مخزنة، جيب آية عشوائية
        if ($selected_book) {
            $verse = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE book = %s ORDER BY RAND() LIMIT 1", $selected_book));
        } else {
            $verse = $wpdb->get_row("SELECT * FROM $table_name ORDER BY RAND() LIMIT 1");
        }

        if ($verse) {
            $verse_data = array(
                'text' => $verse->text,
                'book' => $verse->book,
                'chapter' => $verse->chapter,
                'verse' => $verse->verse
            );
            // احفظ الآية لمدة 24 ساعة (86400 ثانية)
            set_transient($transient_key, $verse_data, 86400);
        }
    }

    if ($verse_data) {
        $reference = $verse_data['book'] . ' ' . $verse_data['chapter'] . ':' . $verse_data['verse'];
        $verse_url = esc_url(home_url("/bible/" . urlencode($verse_data['book']) . "/{$verse_data['chapter']}/{$verse_data['verse']}"));
        $output = "<p class='daily-verse verse-text' data-original-text='{$verse_data['text']}' data-verse-url='{$verse_url}'><a href='{$verse_url}' class='verse-number'>{$verse_data['verse']}.</a> {$verse_data['text']} <a href='{$verse_url}'>[$reference]</a></p>";
        return $output;
    }
    return '<p>لم يتم العثور على آية.</p>';
}
add_shortcode('daily_verse', 'daily_verse_shortcode');

// إضافة CSS لتحسين شكل الأزرار ورقم الآية
function bible_plugin_styles() {
    echo '<style>
        .bible-controls {
            margin-bottom: 15px;
        }
        .bible-controls button {
            margin-right: 10px;
            padding: 8px 12px;
            cursor: pointer;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .bible-controls button:hover {
            background-color: #e0e0e0;
        }
        .bible-controls button i {
            margin-left: 5px;
        }
        .verse-number {
            display: inline-block;
            font-weight: bold;
            margin-right: 5px;
            color: #555;
            text-decoration: none;
        }
        .verse-number:hover {
            text-decoration: underline;
        }
        .verse-text {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .chapter-navigation {
            margin-top: 20px;
            overflow: hidden;
        }
        .chapter-navigation a {
            padding: 10px 15px;
            background-color: #f0f0f0;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .chapter-navigation a:hover {
            background-color: #e0e0e0;
        }
        .chapter-navigation a i {
            margin-left: 5px;
        }
    </style>';
}
add_action('wp_head', 'bible_plugin_styles');

// تحديث الـ Permalinks عند تفعيل الإضافة
function bible_plugin_activation() {
    bible_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bible_plugin_activation');

// تحديث الـ Permalinks عند إلغاء تفعيل الإضافة
function bible_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'bible_plugin_deactivation');
?>