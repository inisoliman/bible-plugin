jQuery(document).ready(function($) {
    console.log('Bible Ajax JS Loaded'); // رسالة للتأكد إن الملف شغال

    $('#bible-book').on('change', function() {
        var book = $(this).val();
        console.log('Selected Book:', book); // رسالة للتأكد إن السفر اتختار

        if (book) {
            $.ajax({
                url: bibleAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bible_get_chapters',
                    book: book
                },
                success: function(response) {
                    console.log('Chapters Response:', response); // رسالة لعرض الاستجابة
                    $('#bible-chapter').html('<option value="">اختر الأصحاح</option>');
                    $.each(response, function(index, chapter) {
                        $('#bible-chapter').append('<option value="' + chapter + '">' + chapter + '</option>');
                    });
                    $('#bible-verses').html('');
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching chapters:', status, error); // رسالة خطأ لو حصل مشكلة
                }
            });
        } else {
            $('#bible-chapter').html('<option value="">اختر الأصحاح</option>');
            $('#bible-verses').html('');
        }
    });

    $('#bible-chapter').on('change', function() {
        var book = $('#bible-book').val();
        var chapter = $(this).val();
        console.log('Selected Book:', book, 'Chapter:', chapter); // رسالة للتأكد إن الأصحاح اتختار

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
                    console.log('Verses Response:', response); // رسالة لعرض الاستجابة
                    $('#bible-verses').html(response.html);
                    // تحديث العنوان في علامة التبويب
                    document.title = response.title;
                    // تحديث Meta Description
                    let metaDesc = document.querySelector('meta[name="description"]');
                    if (metaDesc) {
                        metaDesc.setAttribute('content', response.description);
                    } else {
                        metaDesc = document.createElement('meta');
                        metaDesc.setAttribute('name', 'description');
                        metaDesc.setAttribute('content', response.description);
                        document.head.appendChild(metaDesc);
                    }
                    // تحديث الرابط في شريط العنوان
                    const newUrl = window.location.origin + '/bible/' + encodeURIComponent(book) + '/' + chapter;
                    history.pushState({ book: book, chapter: chapter }, '', newUrl);
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching verses:', status, error); // رسالة خطأ لو حصل مشكلة
                }
            });
        }
    });

    // التعامل مع زر الرجوع/التقدم في المتصفح
    window.onpopstate = function(event) {
        if (event.state && event.state.book && event.state.chapter) {
            $('#bible-book').val(event.state.book);
            $('#bible-chapter').val(event.state.chapter);
            $.ajax({
                url: bibleAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bible_get_verses',
                    book: event.state.book,
                    chapter: event.state.chapter
                },
                success: function(response) {
                    $('#bible-verses').html(response.html);
                    document.title = response.title;
                    let metaDesc = document.querySelector('meta[name="description"]');
                    if (metaDesc) {
                        metaDesc.setAttribute('content', response.description);
                    }
                }
            });
        }
    };
});

// دالة لإلغاء/تفعيل التشكيل
function toggleTashkeel() {
    const verses = document.querySelectorAll('.verse-text');
    const button = document.getElementById('toggle-tashkeel');
    const isTashkeelOff = button.textContent === 'تفعيل التشكيل';

    verses.forEach(verse => {
        const originalText = verse.getAttribute('data-original-text');
        const verseNumberLink = verse.querySelector('.verse-number');
        const verseNumber = verseNumberLink ? verseNumberLink.textContent : '';
        const verseUrl = verse.getAttribute('data-verse-url');
        const referenceLink = verse.querySelector('a:not(.verse-number)');

        if (isTashkeelOff) {
            // إرجاع النص الأصلي مع التشكيل
            verse.innerHTML = `<a href="${verseUrl}" class="verse-number">${verseNumber}</a> ${originalText} ${referenceLink.outerHTML}`;
        } else {
            // إزالة التشكيل
            const cleanText = originalText.replace(/[\u0617-\u061A\u064B-\u065F\u06D6-\u06ED]/g, '');
            verse.innerHTML = `<a href="${verseUrl}" class="verse-number">${verseNumber}</a> ${cleanText} ${referenceLink.outerHTML}`;
        }
    });

    // تغيير نص الزر
    button.textContent = isTashkeelOff ? 'إلغاء التشكيل' : 'تفعيل التشكيل';
}

// دالة لتكبير/تصغير الخط
function changeFontSize(change) {
    const verses = document.querySelectorAll('.verse-text');
    verses.forEach(verse => {
        const currentSize = parseFloat(window.getComputedStyle(verse).fontSize);
        const newSize = currentSize + change;
        if (newSize >= 12 && newSize <= 32) { // حدود للحجم (بين 12px و 32px)
            verse.style.fontSize = newSize + 'px';
        }
    });
}

// إضافة الرابط عند النسخ
document.addEventListener('copy', function(e) {
    const selection = window.getSelection();
    const selectedText = selection.toString().trim();

    if (selectedText) {
        // ابحث عن أقرب عنصر verse-text يحتوي على النص المنسوخ
        let range = selection.getRangeAt(0);
        let verseElement = range.commonAncestorContainer;
        while (verseElement && !verseElement.classList?.contains('verse-text')) {
            verseElement = verseElement.parentElement;
        }

        if (verseElement) {
            const verseUrl = verseElement.getAttribute('data-verse-url');
            const verseNumber = verseElement.querySelector('.verse-number').textContent;
            const reference = verseElement.querySelector('a:not(.verse-number)').textContent;

            // إنشاء النص المنسوخ مع الرابط
            const textWithReference = `${verseNumber} ${selectedText} ${reference}\n${verseUrl}`;

            // وضع النص في الحافظة
            e.preventDefault();
            e.clipboardData.setData('text/plain', textWithReference);
        }
    }
});