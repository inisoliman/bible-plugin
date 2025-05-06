document.addEventListener('copy', function(e) {
    var selectedText = window.getSelection().toString();
    var verses = document.querySelectorAll('.bible-content p');
    verses.forEach(function(verse) {
        if (verse.textContent.includes(selectedText)) {
            var reference = verse.querySelector('a').textContent;
            e.clipboardData.setData('text/plain', selectedText + ' ' + reference);
            e.preventDefault();
        }
    });
});