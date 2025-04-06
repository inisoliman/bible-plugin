<?php
function bible_get_chapters() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $book = sanitize_text_field($_POST['book']);

    // تنظيف اسم السفر
    $book = trim($book);
    // هنعلق السطر ده مؤقتًا
    // $book = str_replace(array('إ', 'أ', 'آ'), 'ا', $book);

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

    // تنظيف اسم السفر
    $book = trim($book);
    // هنعلق السطر ده مؤقتًا
    // $book = str_replace(array('إ', 'أ', 'آ'), 'ا', $book);

    $verses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE book = %s AND chapter = %d",
        $book, $chapter
    ));

    // رسالة تصحيح
    error_log('Verses in bible_get_verses: ' . print_r($verses, true));

    $output = '<div class="bible-controls">';
    $output .= '<button id="toggle-tashkeel" onclick="toggleTashkeel()"><i class="fas fa-language"></i> إلغاء التشكيل</button>';
    $output .= '<button id="increase-font" onclick="changeFontSize(2)"><i class="fas fa-plus"></i> تكبير الخط</button>';
    $output .= '<button id="decrease-font" onclick="changeFontSize(-2)"><i class="fas fa-minus"></i> تصغير الخط</button>';
    $output .= '</div>';
    $output .= '<div id="verses-content">';
    $first_verse_text = '';
    foreach ($verses as $verse) {
        $reference = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
        $verse_url = esc_url(home_url("/bible/" . str_replace(' ', '-', $verse->book) . "/{$verse->chapter}/{$verse->verse}"));
        $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse->text) . "' data-verse-url='" . esc_attr($verse_url) . "'><a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse->verse) . ".</a> " . esc_html($verse->text) . " <a href='" . esc_url($verse_url) . "'>[" . esc_html($reference) . "]</a></p>";
        if (empty($first_verse_text)) {
            $first_verse_text = $verse->text;
        }
    }
    $output .= '</div>';

    $chapters = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s", $book));
    $current_chapter_index = array_search($chapter, $chapters);

    $output .= '<div class="chapter-navigation">';
    if ($current_chapter_index > 0) {
        $prev_chapter = $chapters[$current_chapter_index - 1];
        $prev_url = esc_url(home_url("/bible/" . str_replace(' ', '-', $book) . "/{$prev_chapter}"));
        $output .= '<a href="' . esc_url($prev_url) . '"><i class="fas fa-arrow-right"></i> الأصحاح السابق (' . esc_html($prev_chapter) . ')</a>';
    }
    if ($current_chapter_index < count($chapters) - 1) {
        $next_chapter = $chapters[$current_chapter_index + 1];
        $next_url = esc_url(home_url("/bible/" . str_replace(' ', '-', $book) . "/{$next_chapter}"));
        $output .= '<a href="' . esc_url($next_url) . '" style="float: left;"><i class="fas fa-arrow-left"></i> الأصحاح التالي (' . esc_html($next_chapter) . ')</a>';
    }
    $output .= '</div>';

    $response = array(
        'html' => $output,
        'title' => esc_html($book . ' ' . $chapter . ' - الكتاب المقدس'),
        'description' => esc_html(wp_trim_words($first_verse_text, 20, '...'))
    );
    wp_send_json($response);
}
add_action('wp_ajax_bible_get_verses', 'bible_get_verses');
add_action('wp_ajax_nopriv_bible_get_verses', 'bible_get_verses');