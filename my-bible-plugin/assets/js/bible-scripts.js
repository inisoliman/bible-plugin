function changeFontSize(change) {
    console.log("changeFontSize called with change: " + change);
    const verses = document.querySelectorAll(".verse-text");
    console.log("Found verses: ", verses);
    if (verses.length === 0) {
        console.error("No elements with class verse-text found!");
    }
    verses.forEach(verse => {
        const currentSize = parseFloat(window.getComputedStyle(verse).fontSize);
        console.log("Current font size: " + currentSize);
        const newSize = currentSize + change;
        if (newSize >= 12 && newSize <= 48) {
            verse.style.fontSize = newSize + "px";
            console.log("New font size set to: " + newSize);
        } else {
            console.log("New size out of bounds: " + newSize);
        }
    });
}

function removeTashkeel(text) {
    return text.replace(/[\u0617-\u061A\u064B-\u065F\u06D6-\u06ED]/g, "");
}

function toggleTashkeel() {
    console.log("toggleTashkeel called");
    const verses = document.querySelectorAll(".verse-text");
    const button = document.getElementById("toggle-tashkeel");
    verses.forEach(verse => {
        const originalText = verse.getAttribute("data-original-text");
        const currentText = verse.childNodes[2].textContent.trim();
        if (currentText === originalText) {
            verse.childNodes[2].textContent = " " + removeTashkeel(originalText);
            button.innerHTML = "<i class=\"fas fa-language\"></i> إظهار التشكيل";
        } else {
            verse.childNodes[2].textContent = " " + originalText;
            button.innerHTML = "<i class=\"fas fa-language\"></i> إلغاء التشكيل";
        }
    });
}