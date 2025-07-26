<?php
/*
Plugin Name: Quiz Category Search
Description: Adds AJAX search for quiz posts under current taxonomy term.
Version: 1.0
Author: Freequiz.ai
*/

add_action('wp_enqueue_scripts', 'qcs_enqueue_scripts');
function qcs_enqueue_scripts() {
    if ( is_tax('quizzes') ) {
        wp_enqueue_script(
            'quiz-category-search',
            plugin_dir_url(__FILE__) . 'assets/js/search.js',
            ['jquery'],
            null,
            true
        );
        wp_localize_script('quiz-category-search', 'qcs_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
}

add_action('wp_ajax_search_quizzes', 'qcs_ajax_search_quizzes');
add_action('wp_ajax_nopriv_search_quizzes', 'qcs_ajax_search_quizzes');

function qcs_ajax_search_quizzes() {
    $term_id = intval($_POST['term_id']);
    $search = sanitize_text_field($_POST['search']);

    global $wpdb;

    $search_term = '%' . $wpdb->esc_like($search) . '%';

    $sql = $wpdb->prepare("
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'quiz'
          AND p.post_status = 'publish'
          AND tt.taxonomy = 'quizzes'
          AND tt.term_id = %d
          AND (p.post_title LIKE %s OR p.post_content LIKE %s)
        ORDER BY p.post_date DESC
        LIMIT 10
    ", $term_id, $search_term, $search_term);

    $results = $wpdb->get_results($sql);

    if ( $results ) {
        echo '<div class="wp-block-columns has-4-columns is-layout-flex" style="gap: 1.5rem; flex-wrap: wrap;">';
        foreach ( $results as $row ) {
            $post = get_post( $row->ID );
            setup_postdata( $post );
            echo '<div class="wp-block-column">';
            if ( has_post_thumbnail( $post ) ) {
                echo '<a href="' . get_permalink( $post ) . '">' . get_the_post_thumbnail( $post->ID, 'medium' ) . '</a>';
            }
            echo '<p class="wp-block-post-title" style="margin-top: 10px;"><a href="' . get_permalink( $post ) . '">' . get_the_title( $post ) . '</a></p>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p>No matching quizzes found.</p>';
    }

    wp_die();
}
