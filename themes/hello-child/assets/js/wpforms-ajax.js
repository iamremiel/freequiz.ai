jQuery(function($) {

    // 1. Load the WPForm via AJAX
    function loadWpForm(formId) {
        const container = $('#wpform-container');

        $.ajax({
            url: wpforms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'load_wpform', // <-- must match your PHP handler
                form_id: formId,
                nonce: wpforms_ajax.nonce
            },
            beforeSend: function() {
                container.html('<div class="loading-spinner">Loading form...</div>');
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    container.html(response.data.html);
                    console.log('✅ Form HTML inserted into #wpform-container');

                    // 2. Reinitialize WPForms Modern after DOM is updated
                    setTimeout(function () {
                        console.log('⏳ Reinit attempt starting…');
                        if (
                            typeof WPForms !== 'undefined' &&
                            WPForms.FrontendModern &&
                            typeof WPForms.FrontendModern.ready === 'function'
                        ) {
                            WPForms.FrontendModern.ready();
                            console.log('✅ WPForms Modern reinitialized after AJAX.');
                        } else {
                            console.warn('⚠️ WPForms Modern not available or already initialized.');
                        }
                    }, 150);
                } else {
                    container.html('<div class="error">Form load failed.</div>');
                    console.error('❌ AJAX success but form HTML missing.');
                }
            },
            error: function(xhr, status, error) {
                container.html('<div class="error">AJAX request failed.</div>');
                console.error('❌ AJAX error:', error);
            }
        });
    }

    // 3. Wait until container exists in DOM
    const waitForFormContainer = setInterval(function () {
        if ($('#wpform-container').length) {
            console.log('✅ Found #wpform-container in DOM');
            clearInterval(waitForFormContainer);
            loadWpForm(23); // replace with your actual form ID
        }
    }, 500);
});
