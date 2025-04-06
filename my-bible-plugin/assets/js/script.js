// إزالة التشكيل من النص
function removeTashkeel(text) {
    return text.replace(/[\u0617-\u061A\u064B-\u065F\u06D6-\u06ED]/g, '');
}

// تبديل التشكيل
function toggleTashkeel() {
    const verses = document.querySelectorAll('.verse-text');
    const button = document.getElementById('toggle-tashkeel');
    verses.forEach(verse => {
        const originalText = verse.getAttribute('data-original-text');
        const currentText = verse.childNodes[2].textContent.trim(); // النص بعد رقم الآية والمسافة
        if (currentText === originalText) {
            verse.childNodes[2].textContent = ' ' + removeTashkeel(originalText);
            button.innerHTML = '<i class="fas fa-language"></i> إظهار التشكيل';
        } else {
            verse.childNodes[2].textContent = ' ' + originalText;
            button.innerHTML = '<i class="fas fa-language"></i> إلغاء التشكيل';
        }
    });
}

// تغيير حجم الخط
function changeFontSize(change) {
    const verses = document.querySelectorAll('.verse-text');
    verses.forEach(verse => {
        const currentSize = parseFloat(window.getComputedStyle(verse).fontSize);
        const newSize = currentSize + change;
        if (newSize >= 12 && newSize <= 48) { // حدود لحجم الخط
            verse.style.fontSize = newSize + 'px';
        }
    });
}

// تحديث الرابط عند اختيار سفر وأصحاح
document.addEventListener('DOMContentLoaded', function() {
    const bookSelect = document.getElementById('bible-book');
    const chapterSelect = document.getElementById('bible-chapter');
    const versesDiv = document.getElementById('bible-verses');

    if (bookSelect) {
        bookSelect.addEventListener('change', function() {
            const book = this.value;
            chapterSelect.innerHTML = '<option value="">اختر الأصحاح</option>';
            versesDiv.innerHTML = '';
            if (book) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=bible_get_chapters&book=' + encodeURIComponent(book)
                })
                .then(response => response.json())
                .then(chapters => {
                    chapters.forEach(chapter => {
                        const option = document.createElement('option');
                        option.value = chapter;
                        option.textContent = chapter;
                        chapterSelect.appendChild(option);
                    });
                });
            }
        });
    }

    if (chapterSelect) {
        chapterSelect.addEventListener('change', function() {
            const book = bookSelect.value;
            const chapter = this.value;
            if (book && chapter) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=bible_get_verses&book=' + encodeURIComponent(book) + '&chapter=' + chapter
                })
                .then(response => response.json())
                .then(data => {
                    versesDiv.innerHTML = data.html;
                    document.title = data.title;
                    const metaDescription = document.querySelector('meta[name="description"]');
                    if (metaDescription) {
                        metaDescription.setAttribute('content', data.description);
                    }
                    history.pushState({}, data.title, '/bible/' + book.replace(/ /g, '-') + '/' + chapter + '/');
                });
            }
        });
    }
});