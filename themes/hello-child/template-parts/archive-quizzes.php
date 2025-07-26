<?php
/**
 * The template for displaying archive pages.
 *
 * @package HelloElementor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<main id="content" class="site-main">
<?php
if ( function_exists( 'rank_math_the_breadcrumbs' ) && ! is_home() && !is_front_page() ) {
    rank_math_the_breadcrumbs();
}
?>



	<?php if ( apply_filters( 'hello_elementor_page_title', true ) ) : ?>
		<div class="page-header">
			<?php
			the_archive_title( '<h1 class="entry-title">', '</h1>' );
			the_archive_description( '<p class="archive-description">', '</p>' );
			?>
		</div>
	<?php endif; ?>

	<div class="page-content">
<?php
$current_term = get_queried_object();

if ( $current_term && ! is_wp_error( $current_term ) ) {
    // 1. Show child terms (subcategories)
    $child_terms = get_terms([
        'taxonomy' => 'quizzes',
        'parent'   => $current_term->term_id,
        'hide_empty' => false
    ]);

    if ( ! empty( $child_terms ) && ! is_wp_error( $child_terms ) ) {
        echo '<table>';
        foreach ( $child_terms as $child ) {
            echo '<tr>';
            echo '<td><strong><a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a></strong></td>';

            $grandchildren = get_terms([
                'taxonomy' => 'quizzes',
                'parent' => $child->term_id,
                'hide_empty' => false
            ]);

            echo '<td>';
            if ( ! empty( $grandchildren ) && ! is_wp_error( $grandchildren ) ) {
                foreach ( $grandchildren as $grandchild ) {
                    echo '<a href="' . esc_url( get_term_link( $grandchild ) ) . '">' . esc_html( $grandchild->name ) . '</a><br>';
                }
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // 2. Show posts in current term
    $quiz_posts = new WP_Query([
        'post_type' => 'quiz', // replace with your actual post type
        'tax_query' => [[
            'taxonomy' => 'quizzes',
            'field' => 'term_id',
            'terms' => $current_term->term_id,
             'include_children' => false
        ]]
    ]); ?>

    <?php if ( $current_term ) : ?>
   	<?php 
   		echo '<div class="quizzes-columns wp-block-group" style="margin-top: 40px;">';
   	?>
    <input type="hidden" id="quiz-term-id" value="<?php echo esc_attr( $current_term->term_id ); ?>">
    <input type="text" id="quiz-search" placeholder="Search quizzes..." style="padding:10px; width:100%; max-width:400px; margin-bottom:20px;">
    <div id="quiz-results">
        <?php
		if ( $quiz_posts->have_posts() ) {

		    echo '<div class="wp-block-columns has-4-columns is-layout-flex wp-block-columns-is-layout-flex" style="gap: 1.5rem; flex-wrap: wrap;">';

		    while ( $quiz_posts->have_posts() ) {
		        $quiz_posts->the_post();

		        echo '<div class="wp-block-column" >';

		        if ( has_post_thumbnail() ) {
		            echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail( get_the_ID(), 'full' ) . '</a>';
		        }

		        echo '<p class="wp-block-post-title" style="margin-top: 10px;"><a href="' . get_permalink() . '">' . get_the_title() . '</a></p>';

		        echo '</div>';
		    }

		    echo '</div>'; // End wp-block-columns
		 

		    wp_reset_postdata();
		}
		 else {
		        echo '<p style="color: #999;">No quizzes directly under this category.</p>';
		    }
		 echo '</div>'; // End wp-block-group
  	    ?>

    </div>
<?php endif; ?>

<?php
}
?>

	</div>

</main>