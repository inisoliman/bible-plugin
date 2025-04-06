<?php
function bible_custom_template($template) {
    global $wp_query, $wpdb;

    // تسجيل الـ JavaScript
    add_action('wp_enqueue_scripts', function() {
        wp_enqueue_script(
            'bible-scripts',
            plugin_dir_url(__FILE__) . 'assets/js/bible-scripts.js',
            array(),
            '1.0.0',
            true
        );
    });

    // تحقق إذا كان الرابط هو bible_read (صفحة القراءة)
    if (get_query_var('pagename') == 'bible_read') {
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

    // تحقق إذا كان الرابط هو bible (لعرض آية أو أصحاح)
    if (get_query_var('pagename') == 'bible') {
        // جرب استخراج القيم باستخدام get_query_var
        $book = get_query_var('book') ? rawurldecode(sanitize_text_field(get_query_var('book'))) : '';
        $chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : 0;
        $verse = get_query_var('verse') ? intval(get_query_var('verse')) : null;

        // لو get_query_var ما جابش قيم، نستخرج القيم يدويًا من الرابط
        if (empty($book)) {
            $request_uri = $_SERVER['REQUEST_URI'];
            $path = parse_url($request_uri, PHP_URL_PATH);
            $path_parts = explode('/', trim($path, '/'));

            if (isset($path_parts[1]) && $path_parts[1] == 'bible') {
                $book = isset($path_parts[2]) ? rawurldecode($path_parts[2]) : '';
                $chapter = isset($path_parts[3]) ? intval($path_parts[3]) : 0;
                $verse = isset($path_parts[4]) ? intval($path_parts[4]) : null;
            }
        }

        // تنظيف اسم السفر
        if ($book) {
            $book = trim($book);
            $book = str_replace('-', ' ', $book);
            $book_clean = $book;
            $book_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $book_clean);
            $book_clean = str_replace("\u{0651}", '', $book_clean);
            $book_clean = str_replace(array('أ', 'إ', 'آ'), 'ا', $book_clean);
            $book_clean = trim($book_clean);
        }

        // رسالة تصحيح للتأكد من اسم السفر
        error_log('Book after cleaning in bible_custom_template: ' . $book);
        error_log('Book clean for comparison: ' . $book_clean);

        // لو ما لقيناش book أو chapter، نرجع خطأ
        if (empty($book) || !$chapter) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;

            ob_start();
            ?>
            <h1>خطأ في الرابط</h1>
            <p>يرجى التأكد من الرابط. يجب أن يكون على الشكل: /bible/اسم السفر/رقم-الأصحاح/ أو /bible/اسم السفر/رقم-الأصحاح/رقم-الآية/</p>
            <?php
            $content = ob_get_clean();

            add_filter('the_content', function() use ($content) {
                return $content;
            });

            return get_page_template();
        }

        $table_name = $wpdb->prefix . 'bible_verses';

        if ($verse !== null) {
            // عرض آية منفردة
            // جلب كل الأسفار للتحقق من تطابق الاسم
            $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");
            $matched_book = '';
            foreach ($books as $db_book) {
                $db_book_clean = $db_book;
                $db_book_clean = str_replace('-', ' ', $db_book_clean); // التأكد من استبدال الـ - بمسافة
                $db_book_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $db_book_clean);
                $db_book_clean = str_replace("\u{0651}", '', $db_book_clean);
                $db_book_clean = str_replace(array('أ', 'إ', 'آ'), 'ا', $db_book_clean);
                $db_book_clean = trim($db_book_clean);
                error_log('Comparing: ' . $db_book_clean . ' with ' . $book_clean);
                if (strtolower($db_book_clean) == strtolower($book_clean)) {
                    $matched_book = $db_book;
                    break;
                }
            }

            if (empty($matched_book)) {
                $wp_query->is_404 = false;
                $wp_query->is_single = true;

                ob_start();
                ?>
                <h1>السفر غير موجود</h1>
                <p>لم يتم العثور على السفر: <?php echo esc_html($book); ?></p>
                <p>تأكد من أن اسم السفر موجود في قاعدة البيانات.</p>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            }

            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE book = %s AND chapter = %d AND verse = %d",
                $matched_book, $chapter, $verse
            ));

            // رسالة تصحيح للتأكد من الآية
            error_log('Verse Query Result in bible_custom_template: ' . print_r($result, true));

            if ($result) {
                $wp_query->is_404 = false;
                $wp_query->is_single = true;

                add_filter('pre_get_document_title', function() use ($result) {
                    return esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse) . ' - الكتاب المقدس';
                }, 999);

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
                <p class="verse-text" data-original-text="<?php echo esc_attr($result->text); ?>" data-verse-url="<?php echo esc_url(home_url("/bible/" . $result->book . "/{$result->chapter}/{$result->verse}")); ?>">
                    <a href="<?php echo esc_url(home_url("/bible/" . $result->book . "/{$result->chapter}/{$result->verse}")); ?>" class="verse-number"><?php echo esc_html($result->verse); ?>.</a> 
                    <?php echo esc_html($result->text); ?> 
                    <a href="<?php echo esc_url(home_url("/bible/" . $result->book . "/{$result->chapter}/{$result->verse}")); ?>">
                        [<?php echo esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse); ?>]
                    </a>
                </p>
                <div class="chapter-navigation">
                    <a href="<?php echo esc_url(home_url("/bible/" . $result->book . "/{$result->chapter}")); ?>"><i class="fas fa-book-open"></i> العودة إلى الأصحاح الكامل (<?php echo esc_html($result->book . ' ' . $result->chapter); ?>)</a>
                </div>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            } else {
                $wp_query->is_404 = false;
                $wp_query->is_single = true;

                ob_start();
                ?>
                <h1>الآية غير موجودة</h1>
                <p>لم يتم العثور على الآية: <?php echo esc_html($book . ' ' . $chapter . ':' . $verse); ?></p>
                <p>تأكد من أن السفر والأصحاح والآية موجودة في قاعدة البيانات.</p>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            }
        } else {
            // عرض أصحاح كامل
            // نفس التحقق من اسم السفر
            $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");
            $matched_book = '';
            foreach ($books as $db_book) {
                $db_book_clean = $db_book;
                $db_book_clean = str_replace('-', ' ', $db_book_clean); // التأكد من استبدال الـ - بمسافة
                $db_book_clean = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $db_book_clean);
                $db_book_clean = str_replace("\u{0651}", '', $db_book_clean);
                $db_book_clean = str_replace(array('أ', 'إ', 'آ'), 'ا', $db_book_clean);
                $db_book_clean = trim($db_book_clean);
                if (strtolower($db_book_clean) == strtolower($book_clean)) {
                    $matched_book = $db_book;
                    break;
                }
            }

            if (empty($matched_book)) {
                $wp_query->is_404 = false;
                $wp_query->is_page = true;

                ob_start();
                ?>
                <h1>السفر غير موجود</h1>
                <p>لم يتم العثور على السفر: <?php echo esc_html($book); ?></p>
                <p>تأكد من أن اسم السفر موجود في قاعدة البيانات.</p>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            }

            $verses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE book = %s AND chapter = %d",
                $matched_book, $chapter
            ));

            // رسالة تصحيح للتأكد من الآيات
            error_log('Verses for Chapter in bible_custom_template: ' . print_r($verses, true));

            if ($verses) {
                $wp_query->is_404 = false;
                $wp_query->is_page = true;

                add_filter('pre_get_document_title', function() use ($matched_book, $chapter) {
                    return esc_html($matched_book . ' ' . $chapter) . ' - الكتاب المقدس';
                }, 999);

                $first_verse = $wpdb->get_var($wpdb->prepare(
                    "SELECT text FROM $table_name WHERE book = %s AND chapter = %d AND verse = 1",
                    $matched_book, $chapter
                ));
                add_action('wp_head', function() use ($first_verse, $matched_book, $chapter) {
                    $description = $first_verse ? esc_html(wp_trim_words($first_verse, 20, '...')) : 'اقرأ ' . esc_html($matched_book) . ' الأصحاح ' . $chapter . ' من الكتاب المقدس.';
                    echo '<meta name="description" content="' . $description . '">';
                });

                ob_start();
                ?>
                <h1><?php echo esc_html($matched_book . ' ' . $chapter); ?></h1>
                <?php echo do_shortcode("[bible_content book='$matched_book' chapter='$chapter']"); ?>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            } else {
                $wp_query->is_404 = false;
                $wp_query->is_page = true;

                ob_start();
                ?>
                <h1>الأصحاح غير موجود</h1>
                <p>لم يتم العثور على الأصحاح: <?php echo esc_html($book . ' ' . $chapter); ?></p>
                <p>تأكد من أن السفر والأصحاح موجودة في قاعدة البيانات.</p>
                <?php
                $content = ob_get_clean();

                add_filter('the_content', function() use ($content) {
                    return $content;
                });

                return get_page_template();
            }
        }
    }

    return $template;
}
add_filter('template_include', 'bible_custom_template');