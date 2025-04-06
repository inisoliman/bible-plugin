<?php
// تسجيل الـ JavaScript في بداية الملف
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'bible-scripts',
        plugin_dir_url(__FILE__) . 'assets/js/bible-scripts.js',
        array(),
        '1.0.0',
        true
    );
});

// عرض الآيات مع قوايم منسدلة
function display_bible_content($atts) {
    global $wpdb, $wp_query;
    $table_name = $wpdb->prefix . 'bible_verses';

    // استخراج المتغيرات من الشورتكود
    $atts = shortcode_atts(array(
        'book' => '',
        'chapter' => ''
    ), $atts);

    // إذا تم تمرير book وchapter من الشورتكود، استخدمهم
    $selected_book = !empty($atts['book']) ? $atts['book'] : (get_query_var('book') ? rawurldecode(sanitize_text_field(get_query_var('book'))) : '');
    $selected_chapter = !empty($atts['chapter']) ? intval($atts['chapter']) : (get_query_var('chapter') ? intval(get_query_var('chapter')) : '');

    // تنظيف اسم السفر
    if ($selected_book) {
        $selected_book = trim($selected_book); // إزالة المسافات الزيادة
        $selected_book = str_replace('-', ' ', $selected_book); // استبدال الـ - بمسافة
    }

    // رسالة تصحيح للتأكد من اسم السفر والأصحاح
    error_log('Selected Book in display_bible_content: ' . $selected_book);
    error_log('Selected Chapter in display_bible_content: ' . $selected_chapter);

    // جيب كل الأسفار
    $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");

    if (empty($books)) {
        return '<p>خطأ: لم يتم العثور على أي أسفار في قاعدة البيانات.</p>';
    }

    $output = '<div id="bible-container">';
    $output .= '<select id="bible-book" name="selected_book">';
    $output .= '<option value="">اختر السفر</option>';
    foreach ($books as $book) {
        $selected = ($book == $selected_book) ? 'selected' : '';
        $output .= "<option value='" . esc_attr($book) . "' $selected>" . esc_html($book) . "</option>";
    }
    $output .= '</select>';

    $chapters = [];
    if ($selected_book) {
        $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $selected_book));
    }

    $output .= ' <select id="bible-chapter" name="selected_chapter"><option value="">اختر الأصحاح</option>';
    foreach ($chapters as $chapter) {
        $selected = ($chapter == $selected_chapter) ? 'selected' : '';
        $output .= "<option value='" . esc_attr($chapter) . "' $selected>" . esc_html($chapter) . "</option>";
    }
    $output .= '</select>';
    $output .= '<div id="bible-verses">';

    if ($selected_book && $selected_chapter) {
        $verses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE book = %s AND chapter = %d",
            $selected_book, $selected_chapter
        ));

        // رسالة تصحيح للتأكد من الآيات
        error_log('Verses in display_bible_content: ' . print_r($verses, true));

        if (empty($verses)) {
            $output .= '<p>خطأ: لم يتم العثور على آيات لهذا الأصحاح.</p>';
            $output .= '<p>السفر: ' . esc_html($selected_book) . '، الأصحاح: ' . esc_html($selected_chapter) . '</p>';
        } else {
            $output .= '<div class="bible-controls">';
            $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
            $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
            $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
            $output .= '</div>';
            $output .= '<div id="verses-content">';
            $first_verse_text = '';
            foreach ($verses as $verse) {
                $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
                $verse_url = esc_url(home_url("/bible/" . $verse->book . "/{$verse->chapter}/{$verse->verse}"));
                $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse->text) . "' data-verse-url='" . esc_attr($verse_url) . "'><a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse->verse) . ".</a> " . esc_html($verse->text) . " <a href='" . esc_url($verse_url) . "'>[" . esc_html($reference) . "]</a></p>";
                if (empty($first_verse_text)) {
                    $first_verse_text = $verse->text;
                }
            }
            $output .= '</div>';

            $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $selected_book));
            $current_chapter_index = array_search($selected_chapter, $chapters);
            
            $output .= '<div class="chapter-navigation">';
            if ($current_chapter_index > 0) {
                $prev_chapter = $chapters[$current_chapter_index - 1];
                $prev_url = esc_url(home_url("/bible/" . $selected_book . "/{$prev_chapter}"));
                $output .= '<a href="' . esc_url($prev_url) . '"><i class="fas fa-arrow-right"></i> الأصحاح السابق (' . esc_html($prev_chapter) . ')</a>';
            }
            if ($current_chapter_index < count($chapters) - 1) {
                $next_chapter = $chapters[$current_chapter_index + 1];
                $next_url = esc_url(home_url("/bible/" . $selected_book . "/{$next_chapter}"));
                $output .= '<a href="' . esc_url($next_url) . '" style="float: left;"><i class="fas fa-arrow-left"></i> الأصحاح التالي (' . esc_html($next_chapter) . ')</a>';
            }
            $output .= '</div>';
        }
    }

    $output .= '</div>';
    $output .= '</div>';

    return $output;
}
add_shortcode('bible_content', 'display_bible_content');

// شورتكود لنموذج البحث
function bible_search_form($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';

    $output = '<form method="get" class="bible-search-form">';
    $output .= '<input type="text" name="bible_search" placeholder="ابحث في الكتاب المقدس..." value="' . (isset($_GET['bible_search']) ? esc_attr($_GET['bible_search']) : '') . '">';
    $output .= '<button type="submit"><i class="fas fa-search"></i> بحث</button>';
    $output .= '</form>';

    if (isset($_GET['bible_search']) && !empty($_GET['bible_search'])) {
        $search_term = sanitize_text_field($_GET['bible_search']);
        // تنظيف النص المدخل من التشكيل والهمزات
        $search_term_clean = $search_term;
        $search_term_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $search_term_clean);
        $search_term_clean = str_replace(array('أ', 'إ', 'آ'), 'ا', $search_term_clean);
        $search_term_clean = trim($search_term_clean);
        $search_term_like = '%' . $wpdb->esc_like($search_term_clean) . '%';

        // رسالة تصحيح للتأكد من النص المدخل
        error_log('Search term after cleaning: ' . $search_term_clean);
        error_log('Search term LIKE pattern: ' . $search_term_like);

        // جلب كل الأسفار للتحقق إذا كان البحث عن اسم سفر
        $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");
        $is_book_search = false;
        $book_name = '';
        foreach ($books as $book) {
            $book_clean = $book;
            $book_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $book_clean);
            $book_clean = str_replace(array('أ', 'إ', 'آ'), 'ا', $book_clean);
            $book_clean = trim($book_clean);
            if (strtolower($book_clean) == strtolower($search_term_clean)) {
                $is_book_search = true;
                $book_name = $book;
                break;
            }
        }

        if ($is_book_search) {
            // البحث عن اسم سفر، جلب كل الآيات في السفر
            $verses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE book = %s ORDER BY chapter, verse",
                $book_name
            ));

            if ($verses) {
                $output .= '<h2>السفر: ' . esc_html($book_name) . '</h2>';
                $output .= '<div class="bible-controls">';
                $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
                $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
                $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
                $output .= '</div>';
                $output .= '<div class="bible-content">';

                $current_chapter = 0;
                foreach ($verses as $verse) {
                    if ($verse->chapter != $current_chapter) {
                        if ($current_chapter != 0) {
                            $output .= '</div>'; // إغلاق الأصحاح السابق
                        }
                        $current_chapter = $verse->chapter;
                        $output .= '<h3>الأصحاح ' . esc_html($current_chapter) . '</h3>';
                        $output .= '<div class="chapter-verses">';
                    }

                    $verse_text_clean = $verse->text;
                    $verse_text_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $verse_text_clean);
                    $verse_text_clean = str_replace(array('أ', 'إ', 'آ'), 'ا', $verse_text_clean);

                    $highlight_class = (stripos($verse_text_clean, $search_term_clean) !== false) ? 'highlight' : '';

                    $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
                    $verse_url = esc_url(home_url("/bible/" . $verse->book . "/{$verse->chapter}/{$verse->verse}"));
                    $output .= '<p class="verse-text ' . $highlight_class . '" data-original-text="' . esc_attr($verse->text) . '" data-verse-url="' . esc_attr($verse_url) . '">';
                    $output .= '<a href="' . esc_url($verse_url) . '" class="verse-number">' . esc_html($verse->verse) . '.</a> ';
                    $output .= esc_html($verse->text) . ' ';
                    $output .= '<a href="' . esc_url($verse_url) . '">[' . esc_html($reference) . ']</a>';
                    $output .= '</p>';
                }
                $output .= '</div>'; // إغلاق الأصحاح الأخير
                $output .= '</div>';
            } else {
                $output .= '<p>لم يتم العثور على آيات في هذا السفر.</p>';
            }
        } else {
            // البحث العادي في النصوص
            $results_per_page = 10;
            $current_page = isset($_GET['search_page']) ? max(1, intval($_GET['search_page'])) : 1;
            $offset = ($current_page - 1) * $results_per_page;

            $query = "SELECT COUNT(*) FROM $table_name WHERE ";
            $query .= "(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s ";
            $query .= "OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s)";
            $total_results = $wpdb->get_var($wpdb->prepare($query, $search_term_like, $search_term_like));

            // رسالة تصحيح للتأكد من عدد النتائج
            error_log('Total search results: ' . $total_results);

            $query = "SELECT * FROM $table_name WHERE ";
            $query .= "(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s ";
            $query .= "OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s) ";
            $query .= "LIMIT %d OFFSET %d";
            $results = $wpdb->get_results($wpdb->prepare($query, $search_term_like, $search_term_like, $results_per_page, $offset));

            // رسالة تصحيح للتأكد من النتائج
            error_log('Search results: ' . print_r($results, true));

            if ($results) {
                $output .= '<h2>نتائج البحث (' . esc_html($total_results) . ' نتيجة):</h2>';
                $output .= '<div class="bible-controls">';
                $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
                $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
                $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
                $output .= '</div>';
                $output .= '<div class="bible-content">';
                foreach ($results as $result) {
                    $reference = $result->book . ' ' . $result->chapter . ':' . $result->verse;
                    $verse_url = esc_url(home_url("/bible/" . $result->book . "/{$result->chapter}/{$result->verse}"));
                    $output .= '<p class="verse-text" data-original-text="' . esc_attr($result->text) . '" data-verse-url="' . esc_attr($verse_url) . '">';
                    $output .= '<a href="' . esc_url($verse_url) . '" class="verse-number">' . esc_html($result->verse) . '.</a> ';
                    $output .= esc_html($result->text) . ' ';
                    $output .= '<a href="' . esc_url($verse_url) . '">[' . esc_html($reference) . ']</a>';
                    $output .= '</p>';
                }
                $output .= '</div>';

                // إضافة الترقيم
                $total_pages = ceil($total_results / $results_per_page);
                if ($total_pages > 1) {
                    $output .= '<div class="pagination">';
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $active = ($i == $current_page) ? 'style="font-weight: bold;"' : '';
                        $output .= '<a href="' . esc_url(add_query_arg(array('bible_search' => $search_term, 'search_page' => $i))) . '" ' . $active . '>' . esc_html($i) . '</a> ';
                    }
                    $output .= '</div>';
                }
            } else {
                $output .= '<p>لم يتم العثور على نتائج.</p>';
                $output .= '<p>الكلمة التي تم البحث عنها: ' . esc_html($search_term) . '</p>';
            }
        }
    }

    return $output;
}
add_shortcode('bible_search', 'bible_search_form');

// شورتكود لعرض آية عشوائية
function random_verse_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $selected_book = get_option('bible_random_book', '');

    if ($selected_book) {
        $verse = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE book = %s ORDER BY RAND() LIMIT 1",
            $selected_book
        ));
    } else {
        $verse = $wpdb->get_row("SELECT * FROM $table_name ORDER BY RAND() LIMIT 1");
    }

    if ($verse) {
        $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
        $verse_url = esc_url(home_url("/bible/" . $verse->book . "/{$verse->chapter}/{$verse->verse}"));
        return "<p class='random-verse verse-text' data-original-text='" . esc_attr($verse->text) . "' data-verse-url='" . esc_attr($verse_url) . "'><a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse->verse) . ".</a> " . esc_html($verse->text) . " <a href='" . esc_url($verse_url) . "'>[" . esc_html($reference) . "]</a></p>";
    }
    return '<p>لم يتم العثور على آية عشوائية.</p>';
}
add_shortcode('random_verse', 'random_verse_shortcode');

// شورتكود لعرض آية اليوم
function daily_verse_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $selected_book = get_option('bible_random_book', '');

    $current_date = current_time('Y-m-d');
    $transient_key = 'daily_verse_' . $current_date;

    $verse_data = get_transient($transient_key);
    if (false === $verse_data) {
        if ($selected_book) {
            $verse = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE book = %s ORDER BY RAND() LIMIT 1",
                $selected_book
            ));
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
            set_transient($transient_key, $verse_data, DAY_IN_SECONDS);
        }
    }

    if ($verse_data) {
        $reference = $verse_data['book'] . ' ' . $verse_data['chapter'] . ':' . $verse_data['verse'];
        $verse_url = esc_url(home_url("/bible/" . $verse_data['book'] . "/{$verse_data['chapter']}/{$verse_data['verse']}"));
        return "<p class='daily-verse verse-text' data-original-text='" . esc_attr($verse_data['text']) . "' data-verse-url='" . esc_attr($verse_url) . "'><a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_data['verse']) . ".</a> " . esc_html($verse_data['text']) . " <a href='" . esc_url($verse_url) . "'>[" . esc_html($reference) . "]</a></p>";
    }
    return '<p>لم يتم العثور على آية اليوم.</p>';
}
add_shortcode('daily_verse', 'daily_verse_shortcode');