jQuery(document).ready(function($) {
    // عند تغيير السفر
    $('#bible-book').on('change', function() {
        var book = $(this).val();
        if (book) {
            $.ajax({
                url: bibleAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bible_get_chapters',
                    book: book
                },
                success: function(response) {
                    var chapters = response;
                    var chapterSelect = $('#bible-chapter');
                    chapterSelect.empty();
                    chapterSelect.append('<option value="">اختر الأصحاح</option>');
                    $.each(chapters, function(index, chapter) {
                        chapterSelect.append('<option value="' + chapter + '">' + chapter + '</option>');
                    });
                    $('#bible-verses').empty();
                },
                error: function(xhr, status, error) {
                    console.log('Error in bible_get_chapters: ' + error);
                }
            });
        } else {
            $('#bible-chapter').empty().append('<option value="">اختر الأصحاح</option>');
            $('#bible-verses').empty();
        }
    });

    // عند تغيير الأصحاح
    $('#bible-chapter').on('change', function() {
        var book = $('#bible-book').val();
        var chapter = $(this).val();
        if (book && chapter) {
            $.ajax({
                url: bibleAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bible_get_verses',
                    book: book,
                    chapter: chapter
                },
                success: function(response) {
                    $('#bible-verses').html(response.html);
                    document.title = response.title;
                    $('meta[name="description"]').attr('content', response.description);
                    var baseUrl = bibleAjax.base_url;
                    // استبدال المسافات بـ - في اسم السفر
                    var encodedBook = book.replace(/\s+/g, '-');
                    history.pushState({}, response.title, baseUrl + encodedBook + '/' + chapter);
                },
                error: function(xhr, status, error) {
                    console.log('Error in bible_get_verses: ' + error);
                }
            });
        } else {
            $('#bible-verses').empty();
        }
    });
});

// دالة لإلغاء التشكيل
function toggleTashkeel() {
    var verses = document.querySelectorAll('.verse-text');
    verses.forEach(function(verse) {
        var originalText = verse.getAttribute('data-original-text');
        var currentText = verse.innerHTML;
        var verseNumber = verse.querySelector('.verse-number').outerHTML;
        var verseUrl = verse.getAttribute('data-verse-url');
        var reference = verse.querySelector('a:last-child').outerHTML;

        if (currentText.includes('ً') || currentText.includes('َ') || currentText.includes('ُ') || currentText.includes('ِ') || currentText.includes('ْ') || currentText.includes('ّ')) {
            var noTashkeel = originalText.replace(/[\u0617-\u061A\u064B-\u065F\u06D6-\u06ED]/g, '');
            verse.innerHTML = verseNumber + ' ' + noTashkeel + ' ' + reference;
        } else {
            verse.innerHTML = verseNumber + ' ' + originalText + ' ' + reference;
        }
    });

    var button = document.getElementById('toggle-tashkeel');
    button.innerText = button.innerText === 'إلغاء التشكيل' ? 'إعادة التشكيل' : 'إلغاء التشكيل';
    button.innerHTML = '<i class="fas fa-language"></i> ' + button.innerText;
}

// دالة لتغيير حجم الخط
function changeFontSize(change) {
    var versesContainer = document.querySelector('#verses-content');
    if (versesContainer) {
        var verses = versesContainer.querySelectorAll('.verse-text');
        verses.forEach(function(verse) {
            var currentSize = parseFloat(window.getComputedStyle(verse).fontSize);
            var newSize = currentSize + change;
            verse.style.fontSize = newSize + 'px';
        });
    }
}