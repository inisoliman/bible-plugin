document.addEventListener('copy', function(e) {
    var selection = window.getSelection();
    var selectedText = selection.toString().trim();
    
    if (selectedText) {
        var verseElement = selection.anchorNode.parentElement.closest('.verse-text');
        if (verseElement) {
            var verseUrl = verseElement.getAttribute('data-verse-url');
            var referenceElement = verseElement.querySelector('a:last-child');
            var reference = referenceElement ? referenceElement.textContent : '';
            
            var textToCopy = selectedText + ' ' + reference + '\n' + verseUrl;
            
            e.clipboardData.setData('text/plain', textToCopy);
            e.preventDefault();
        }
    }
});