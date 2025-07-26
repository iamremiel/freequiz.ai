<?php
/**
 * Plugin Name: AJAX Quiz Search
 * Description: AJAX-powered search for quizzes, and a widget for common searched categories.
 * Version: 1.0
 * Author: Freequiz.ai
 */

add_action('wp_enqueue_scripts', 'aqs_enqueue_scripts');
function aqs_enqueue_scripts() {
    wp_enqueue_script('ajax-quiz-search', plugin_dir_url(__FILE__) . 'ajax-quiz-search.js', ['jquery'], time(), true);
    wp_localize_script('ajax-quiz-search', 'aqs_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aqs_nonce'),
    ]);
    wp_enqueue_style('ajax-quiz-style', plugin_dir_url(__FILE__) . 'ajax-quiz-search.css');

}

// Create log table on activation
register_activation_hook(__FILE__, 'aqs_create_log_table');
function aqs_create_log_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'quiz_search_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quiz_id BIGINT UNSIGNED NOT NULL,
        searched_at DATETIME NOT NULL,
        INDEX (quiz_id),
        INDEX (searched_at)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// AJAX search handler
add_action('wp_ajax_aqs_quiz_search', 'aqs_quiz_search');
add_action('wp_ajax_nopriv_aqs_quiz_search', 'aqs_quiz_search');

function aqs_quiz_search() {
    check_ajax_referer('aqs_nonce', 'nonce');

    $term = sanitize_text_field($_POST['term'] ?? '');
    $results = [];

    $query = new WP_Query([
        'post_type' => 'quiz',
        's' => $term,
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ]);

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $results[] = [
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'id'    => $post->ID 
            ];
        }
    }

    wp_send_json($results);
}

add_action('wp_ajax_aqs_log_quiz_click', 'aqs_log_quiz_click');
add_action('wp_ajax_nopriv_aqs_log_quiz_click', 'aqs_log_quiz_click');

function aqs_log_quiz_click() {
    check_ajax_referer('aqs_nonce', 'nonce');

    if (!isset($_POST['quiz_id']) || !is_numeric($_POST['quiz_id'])) {
        wp_send_json_error('Invalid quiz ID');
    }

    global $wpdb;

    $wpdb->insert("{$wpdb->prefix}quiz_search_log", [
        'quiz_id'     => intval($_POST['quiz_id']),
        'searched_at' => current_time('mysql')
    ]);

    wp_send_json_success('Logged');
}



// [quiz_search] shortcode
add_shortcode('quiz_search', function() {
    return '<input type="text" id="quiz-search-input" placeholder="Search quizzes..." autocomplete="off" />
            <div id="quiz-search-results"></div>';
});

// [quiz_common_categories] shortcode
add_shortcode('quiz_common_categories', function() {
    global $wpdb;

    $search_counts = $wpdb->get_results("
        SELECT quiz_id, COUNT(*) as count
        FROM {$wpdb->prefix}quiz_search_log
        GROUP BY quiz_id
        ORDER BY count DESC
        LIMIT 50
    ");

    if (!$search_counts) return '<p>No search data available yet.</p>';

    $quiz_ids = wp_list_pluck($search_counts, 'quiz_id');
    $quiz_search_counts = wp_list_pluck($search_counts,'count');
    $taxonomy = 'quizzes';
    $terms = wp_get_object_terms($quiz_ids, $taxonomy, ['fields' => 'all']);

    if (is_wp_error($terms) || empty($terms)) {
        return '<p>No categories found.</p>';
    }

    $counts = [];
    foreach ($terms as $term) {
        $counts[$term->term_id] = isset($counts[$term->term_id]) ? $counts[$term->term_id] + 1 : 1;
    }
    arsort($counts);


    $html = '<div class="pad-t-20px"><p><strong>Common Quiz Searches:</strong></p><ul class="quiz-common-categories">';
    $i = 0;
    foreach (array_slice($counts, 0, 6, true) as $term_id => $count) {
        $term = get_term($term_id, $taxonomy);
        $html .= '<li><a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a> (' . $quiz_search_counts[$i] . ')</li>';
        $i++;
    }
    $html .= '</ul></div>';

    return $html;
});
?>
