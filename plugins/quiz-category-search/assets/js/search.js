document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('quiz-search');
    const results = document.getElementById('quiz-results');
    const termIdEl = document.getElementById('quiz-term-id');

    if (!input || !results || !termIdEl) return;

    const term_id = termIdEl.value;
    let timeout;

    input.addEventListener('keyup', function () {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const search = input.value.trim();

            // Only proceed if search is empty (reset) or has 3+ characters
            if (search.length > 0 && search.length < 3) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'search_quizzes');
            formData.append('search', search); // Can be empty
            formData.append('term_id', term_id);

            fetch(qcs_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(res => res.text())
                .then(html => {
                    results.innerHTML = html;
                });
        }, 300);
    });
});
