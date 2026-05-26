// Helper: add/remove 'show' class
function showWithClass($el) {
    $el.addClass("show");
}
function hideWithClass($el) {
    $el.removeClass("show");
}

// Helper to escape HTML
function escapeHtml(str) {
    if (!str) return "";
    return str.replace(/[&<>]/g, function (m) {
        if (m === "&") return "&amp;";
        if (m === "<") return "&lt;";
        if (m === ">") return "&gt;";
        return m;
    });
}

const isValidEmail = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val.trim());

const isValidPhone = (val) =>
    /^[0-9]{9,15}$/.test(val.replace(/[\s\-\(\)]/g, ""));

// Password: min 8 chars, at least 1 letter + 1 number
const isStrongPassword = (val) =>
    val.length >= 8 && /[a-zA-Z]/.test(val) && /[0-9]/.test(val);

// Waafi number: starts with 6, exactly 9 digits
const isValidWaafiPhone = (val) => /^[67]\d{8}$/.test(val.trim());
