jQuery(document).ready(function($) {
    if (typeof aqs_obj === 'undefined') {
        console.warn('aqs_obj is not defined yet.');
        return;
    }

    console.log('aqs_obj loaded:', aqs_obj);

    const $input = $('#quiz-search-input');
    const $output = $('#quiz-search-results');

    $input.on('input', function() {
        const term = $(this).val();
        if (term.length < 3) {
            $output.html('').hide();
            return;
        }

        $.post(aqs_obj.ajax_url, {
            action: 'aqs_quiz_search',
            nonce: aqs_obj.nonce,
            term: term
        }, function(response) {
            let html = '';
            if (response.length) {
                html = '<ul>';
                response.forEach(item => {
                    // Add data-quiz-id to <a> for logging
                    html += `<li><a href="${item.url}" class="quiz-result-link" data-quiz-id="${item.id}">${item.title}</a></li>`;
                });
                html += '</ul>';
            } else {
                html = '<p>No quizzes found.</p>';
            }

            $output.html(html).show();
        });
    });

    // Handle click on quiz link to log the search
    $(document).on('click', '.quiz-result-link', function(e) {
        e.preventDefault();
        const quizId = $(this).data('quiz-id');

        if (quizId) {
            $.post(aqs_obj.ajax_url, {
                action: 'aqs_log_quiz_click',
                nonce: aqs_obj.nonce,
                quiz_id: quizId
            });
            location.href = $(this).attr('href');
        }

    });
});
