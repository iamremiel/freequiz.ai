<?php 
	 add_action( 'wp_enqueue_scripts', 'hello_child_enqueue_styles' );
	 function hello_child_enqueue_styles() {
 		  wp_enqueue_style( 'parent-style', get_stylesheet_directory_uri() . '/style.css',array(),time(),'all' ); 
 		  } 
          
 
// Shortcode: [list_all_post_types posts_per_page="5"]
function list_all_post_types_shortcode($atts) {
    $atts = shortcode_atts([
        'posts_per_page' => -1,
    ], $atts, 'list_all_post_types');

    // Get all public post types (built-in and custom)
    $types = get_post_types([
        'public' => true,
    ], 'names');

    if (empty($types)) {
        return '<p>No public post types found.</p>';
    }

    $output = '<div class="all-post-types-list">';
    foreach ($types as $type) {
        $output .= '<h3>' . esc_html(ucfirst($type)) . '</h3>';
        $query = new WP_Query([
            'post_type'      => $type,
            'posts_per_page' => intval($atts['posts_per_page']),
            'post_status'    => 'publish',
        ]);
        if ($query->have_posts()) {
            $output .= '<ul>';
            while ($query->have_posts()) {
                $query->the_post();
                $output .= sprintf(
                    '<li><a href="%1$s">%2$s</a></li>',
                    esc_url(get_permalink()),
                    esc_html(get_the_title())
                );
            }
            $output .= '</ul>';
            wp_reset_postdata();
        } else {
            $output .= '<p>No items in this post type.</p>';
        }
    }
    $output .= '</div>';
    return $output;
}
add_shortcode('list_all_post_types', 'list_all_post_types_shortcode');

//make the breadcrumb based on category
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs ) {
	// Get current term object if on taxonomy archive
	if ( is_tax( 'quizzes' ) ) {
		$term = get_queried_object();
	} elseif ( is_singular( 'quiz' ) ) {
		$terms = get_the_terms( get_the_ID(), 'quizzes' );
		$term = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0] : null;
	} else {
		return $crumbs;
	}

	if ( ! $term || ! $term instanceof WP_Term ) {
		return $crumbs;
	}

	$ancestors = array_reverse( get_ancestors( $term->term_id, 'quizzes' ) );

	$new_crumbs = [
		$crumbs[0], // Home
		// Optional: ['Quizzes', get_post_type_archive_link('quiz')],
	];

	foreach ( $ancestors as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'quizzes' );
		if ( $ancestor && ! is_wp_error( $ancestor ) ) {
			$new_crumbs[] = [ $ancestor->name, get_term_link( $ancestor ) ];
		}
	}

	$new_crumbs[] = [ $term->name, get_term_link( $term ) ];

	// Only append the post title on singular view (not taxonomy)
	if ( is_singular( 'quiz' ) ) {
		$new_crumbs[] = end( $crumbs );
	}

	return $new_crumbs;
});


//automatically populate the seo schema using qsm content
function custom_qsm_rankmath_schema_description($data, $jsonld) {
    if (!is_singular()) return $data;

    global $post;

    // Only modify if QSM quiz shortcode is present
    if (has_shortcode($post->post_content, 'qsm')) {
        // Extract quiz ID from shortcode
        preg_match('/\[qsm\s+quiz=([0-9]+)\]/', $post->post_content, $matches);
        if (isset($matches[1])) {
            $quiz_id = intval($matches[1]);

            // Get the QSM quiz post
            $quiz_post = get_post($quiz_id);
            if ($quiz_post && $quiz_post->post_type === 'qsm_quiz') {
                $quiz_content = strip_tags(strip_shortcodes($quiz_post->post_content));
                $quiz_description = wp_trim_words($quiz_content, 25, '...');

                // Update schema description
                if (isset($data['mainEntity'])) {
                    $data['mainEntity']['description'] = $quiz_description;
                } elseif (isset($data['description'])) {
                    $data['description'] = $quiz_description;
                }
            }
        }
    }

    return $data;
}
//add_filter('rank_math/json_ld', 'custom_qsm_rankmath_schema_description', 11, 2);


//Put QSM schema on the quiz pages using shortcode
function get_qsm_questions_json($atts) {
	if (!is_singular('quiz')) {
  		return '<!-- Dont show to any other places other than quiz posts -->';
	}
    global $wpdb;

    
    // Get quiz ID from shortcode or current post
    $quiz_id = isset($atts['id']) ? intval($atts['id']) : get_the_ID();
    
    // Get QSM quiz ID from post ID
    $quiz_table = $wpdb->prefix . 'mlw_quizzes';
    $qsm_quiz = $wpdb->get_row($wpdb->prepare(
        "SELECT quiz_id, quiz_name
        FROM {$quiz_table} 
        WHERE quiz_id = %d",
        $quiz_id
    ));
    $qsm_quiz_type = $wpdb->get_row($wpdb->prepare(
        "SELECT quiz_settings
        FROM {$quiz_table} 
        WHERE quiz_id = %d",
        $quiz_id
    ));

   	$quiz_options = maybe_unserialize($qsm_quiz_type->quiz_settings);

    $get_options = maybe_unserialize($quiz_options['quiz_options']);

    $get_quiz_text = maybe_unserialize($quiz_options['quiz_text']);

    //0 =quiz, 1 = survey, 2 = simple form
    $form_type = $get_options['form_type'];
    //0 = Correct/incorrect, 1 = Point, 3 both
    $grading_system = $get_options['system']; 



/*    echo "<pre>";
     print_r(maybe_unserialize($get_options));
     echo "</pre>";
*/
    
    
    if (!$qsm_quiz) {
        return '<!-- QSM quiz not found -->';
    }


    
    // Get questions
    $questions_table = $wpdb->prefix . 'mlw_questions';
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT question_id, question_name 
        FROM {$questions_table} 
        WHERE quiz_id = %d 
        AND deleted = 0 
        ORDER BY question_order ASC 
        LIMIT 5",
        $qsm_quiz->quiz_id
    ));
    
    if (empty($questions)) {
        return '<!-- No questions found -->';
    }

    $get_about_text = get_field('about');
    $get_the_description_text = get_field('description');
    
    // Build schema structure
    $quiz_schema = [
        "@context" => "https://schema.org",
        "@type"    => "Quiz",
        "name"     => esc_html(get_the_title()),
        "description" => esc_html($get_the_description_text),
        "url" => get_permalink(),
        "about" => esc_html($get_about_text),
	  "interactionStatistic" => [
	    "@type" => "InteractionCounter",
	    "interactionType"=> "https://schema.org/AnswerAction",
	    "userInteractionCount"=> get_qsm_overall_attempts($quiz_id)
	  ],
        "mainEntity" => []
    ];
    
    // Process questions
    foreach ($questions as $question) {
        // Get answers from question settings (new QSM versions)
        $question_settings = $wpdb->get_var($wpdb->prepare(
            "SELECT answer_array 
            FROM {$questions_table} 
            WHERE question_id = %d",
            $question->question_id
        ));

        $qsm_question = $wpdb->get_var($wpdb->prepare(
            "SELECT question_settings
            FROM {$questions_table} 
            WHERE question_id = %d",
            $question->question_id
        ));

        $question_name = maybe_unserialize($qsm_question);
     
  
        
        $answers = [];
        if ($question_settings) {
            // Decode QSM's answer array format
            $settings = maybe_unserialize($question_settings);
            if (is_array($settings) && !empty($settings[0])) {
                $answers = array_map(function($a) {
                    return [
                        'answer' => $a[0],
                        'correct' => ($a[2] == 1)
                    ];
                }, $settings);
            }
        }
        
        // Fallback to old table if answers exist there
        if (empty($answers)) {
            $answers_table = $wpdb->prefix . 'mlw_answers';
            // Check if old table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $answers_table
            ));
            
            if ($table_exists) {
                $answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT answer, correct 
                    FROM {$answers_table} 
                    WHERE question_id = %d 
                    ORDER BY answer_order ASC",
                    $question->question_id
                ), ARRAY_A);
            }
        }
        
        if (empty($answers)) continue;
        
        $correct_answers = [];
        $incorrect_answers = [];
        
        foreach ($answers as $answer) {
            if (!empty($answer['correct'])) {
                $correct_answers[] = $answer['answer'];
            } else {
                $incorrect_answers[] = $answer['answer'];
            }
        }

        $settings = maybe_unserialize($question_settings);

        // Fallback: use question_name if available, otherwise use first answer or question ID
		$question_text = !empty($question->question_name) 
		    ? $question->question_name 
		    : $question_name['question_title'];
        
        // Build question schema
        $question_schema = [
            "@type" => "Question",
            "name"  => wp_strip_all_tags($question_text),
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text"  => !empty($correct_answers) ? implode(', ', $correct_answers) : $answers[0]['answer']
            ]
        ];
        
        // Add incorrect answers if available
        if (!empty($incorrect_answers) && $grading_system == 0) {
            $question_schema['suggestedAnswer'] = array_map(function($answer) {
                return [
                    "@type" => "Answer",
                    "text"  => $answer
                ];
            }, $incorrect_answers);
        }
        
        $quiz_schema["mainEntity"][] = $question_schema;
    }
    
    // Return JSON-LD output
    return '<script type="application/ld+json">' . 
           wp_json_encode($quiz_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . 
           '</script>';
}
add_shortcode('qsm_quiz_schema', 'get_qsm_questions_json');

//get overall attempts to the specific quiz
function get_qsm_overall_attempts($quiz_id) {
    global $wpdb;
    $results_table = $wpdb->prefix . 'mlw_results';

    $attempts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$results_table} WHERE quiz_id = %d",
        $quiz_id
    ));

    return intval($attempts);
}


//add the quiz attempts value directly to acf, do note this will not show on the editor
add_filter('acf/format_value/name=quiz_attempts', function($value, $post_id, $field) {
    $quiz_id = get_qsm_quiz_id_from_content($post_id);
    if (!$quiz_id) return 0;

    return get_qsm_overall_attempts($quiz_id);
}, 10, 3);


//make the quiz attemp firld read only
add_filter('acf/load_field/name=quiz_attempts', function($field) {
    $field['readonly'] = 1;
    return $field;
});


//get quiz id from quiz post
function get_qsm_quiz_id_from_content($post_id) {
    $content = get_post_field('post_content', $post_id);

    preg_match('/\[qsm\s+quiz=(\d+)\]/', $content, $matches);

    return isset($matches[1]) ? intval($matches[1]) : null;
}


// Add schema to wp_head automatically for quiz posts
function auto_output_qsm_quiz_schema() {
    if (!is_singular('quiz')) return;

    global $post, $wpdb;
    $post_id = $post->ID;
    $quiz_id = get_qsm_quiz_id_from_content($post_id);
    if (!$quiz_id) return;

    // Quiz table
    $quiz_table = $wpdb->prefix . 'mlw_quizzes';
    $qsm_quiz_type = $wpdb->get_row($wpdb->prepare(
        "SELECT quiz_settings FROM {$quiz_table} WHERE quiz_id = %d",
        $quiz_id
    ));
    $quiz_options = maybe_unserialize($qsm_quiz_type->quiz_settings);
    $get_options = maybe_unserialize($quiz_options['quiz_options']);
    $get_quiz_text = maybe_unserialize($quiz_options['quiz_text']);

    $form_type = $get_options['form_type']; // 0 = quiz
    $grading_system = $get_options['system']; // 0 = correct/incorrect

    // Quiz title and fields
    $get_about_text = get_field('about', $post_id);
    $get_description_text = get_field('description', $post_id);

    $quiz_schema = [
        "@context" => "https://schema.org",
        "@type"    => "Quiz",
        "name"     => esc_html(get_the_title($post_id)),
        "description" => esc_html($get_description_text),
        "url" => get_permalink($post_id),
        "about" => esc_html($get_about_text),
        "interactionStatistic" => [
            "@type" => "InteractionCounter",
            "interactionType" => "https://schema.org/AnswerAction",
            "userInteractionCount" => get_qsm_overall_attempts($quiz_id),
        ],
        "mainEntity" => []
    ];

    // Fetch questions
    $questions_table = $wpdb->prefix . 'mlw_questions';
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT question_id, question_name 
         FROM {$questions_table} 
         WHERE quiz_id = %d AND deleted = 0 
         ORDER BY question_order ASC 
         LIMIT 5",
        $quiz_id
    ));

    if (!$questions) return;

    foreach ($questions as $question) {
        $question_settings = $wpdb->get_var($wpdb->prepare(
            "SELECT answer_array FROM {$questions_table} WHERE question_id = %d",
            $question->question_id
        ));
        $qsm_question = $wpdb->get_var($wpdb->prepare(
            "SELECT question_settings FROM {$questions_table} WHERE question_id = %d",
            $question->question_id
        ));
        $question_name = maybe_unserialize($qsm_question);

        $answers = [];
        if ($question_settings) {
            $settings = maybe_unserialize($question_settings);
            if (is_array($settings) && !empty($settings[0])) {
                $answers = array_map(function($a) {
                    return [
                        'answer' => $a[0],
                        'correct' => ($a[2] == 1)
                    ];
                }, $settings);
            }
        }

        // fallback old table
        if (empty($answers)) {
            $answers_table = $wpdb->prefix . 'mlw_answers';
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s", $answers_table
            ));
            if ($table_exists) {
                $answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT answer, correct 
                     FROM {$answers_table} 
                     WHERE question_id = %d 
                     ORDER BY answer_order ASC",
                    $question->question_id
                ), ARRAY_A);
            }
        }

        if (empty($answers)) continue;

        $correct_answers = [];
        $incorrect_answers = [];

        foreach ($answers as $answer) {
            if (!empty($answer['correct'])) {
                $correct_answers[] = $answer['answer'];
            } else {
                $incorrect_answers[] = $answer['answer'];
            }
        }

        $question_text = !empty($question->question_name)
            ? $question->question_name
            : $question_name['question_title'];

        $question_schema = [
            "@type" => "Question",
            "name" => wp_strip_all_tags($question_text),
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => !empty($correct_answers) ? implode(', ', $correct_answers) : $answers[0]['answer']
            ]
        ];

        if (!empty($incorrect_answers) && $grading_system == 0) {
            $question_schema['suggestedAnswer'] = array_map(function($a) {
                return ["@type" => "Answer", "text" => $a];
            }, $incorrect_answers);
        }

        $quiz_schema["mainEntity"][] = $question_schema;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($quiz_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}
add_action('wp_head', 'auto_output_qsm_quiz_schema');


function show_all_post_meta_shortcode($atts) {
    global $post;

    // Ensure there's a valid post object
    if (!isset($post->ID)) {
        return 'No post found.';
    }

    $post_id = $post->ID;
    $meta = get_post_meta($post_id);

    if (empty($meta)) {
        return 'No post meta found for this post.';
    }

    $output = '<div class="post-meta"><h3>Post Meta for Post ID: ' . esc_html($post_id) . '</h3><ul>';

    foreach ($meta as $key => $values) {
        foreach ($values as $value) {
            $output .= '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html(print_r($value, true)) . '</li>';
        }
    }

    $output .= '</ul></div>';

    return $output;
}
add_shortcode('show_post_meta', 'show_all_post_meta_shortcode');


function show_qsm_quiz_meta($quiz_id = 1) {
    $meta = get_post_meta($quiz_id);

    if (empty($meta)) {
        echo "No meta found for QSM quiz with ID {$quiz_id}.";
        return;
    }

    echo "<h3>Meta for QSM Quiz ID {$quiz_id}</h3><ul>";
    foreach ($meta as $key => $values) {
        foreach ($values as $value) {
            echo '<li><strong>' . esc_html($key) . '</strong>: ' . esc_html(print_r($value, true)) . '</li>';
        }
    }
    echo '</ul>';
}

function shortcode_qsm_quiz_meta($atts) {
    $atts = shortcode_atts([
        'id' => 1
    ], $atts);

    ob_start();
    show_qsm_quiz_meta(intval($atts['id']));
    return ob_get_clean();
}
add_shortcode('qsm_quiz_meta', 'shortcode_qsm_quiz_meta');

//add widget
function mytheme_widgets_init() {
    register_sidebar([
        'name'          => __('Sidebar'),
        'id'            => 'sidebar-1',
        'before_widget' => '<div class="widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ]);
}
add_action('widgets_init', 'mytheme_widgets_init');


//use a custom menu
class Hello_Submenu_Toggle_Walker extends Walker_Nav_Menu {
    public function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"sub-menu\">\n";
    }

    public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
        $classes = empty( $item->classes ) ? array() : (array) $item->classes;

        $class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item ) );
        $class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

        $output .= '<li' . $class_names . '>';

        $atts = array();
        $atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
        $atts['target'] = ! empty( $item->target )     ? $item->target     : '';
        $atts['rel']    = ! empty( $item->xfn )        ? $item->xfn        : '';
        $atts['href']   = ! empty( $item->url )        ? $item->url        : '';

        $attributes = '';
        foreach ( $atts as $attr => $value ) {
            if ( ! empty( $value ) ) {
                $value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

        $title = apply_filters( 'the_title', $item->title, $item->ID );
        $title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

        $item_output  = $args->before;
        $item_output .= '<a'. $attributes .'>';
        $item_output .= $args->link_before . $title . $args->link_after;
        $item_output .= '</a>';

        // Add the span toggle if item has children
        if (in_array('menu-item-has-children', $classes)) {
            $item_output .= '<span class="submenu-toggle" tabindex="0" role="button" aria-expanded="false" aria-label="Toggle submenu">▾</span>';
        }

        $item_output .= $args->after;

        $output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
    }
}

//add shortcode for wpforms + hubspot
function wpforms_hubspot_wrapper_shortcode($atts) {
    $atts = shortcode_atts([
        'wpform_id'   => '',
        'portal_id'   => '',
        'hubspot_form_id'  => '',
        'class' => '',
    ], $atts, 'hubspot_form');

    if (empty($atts['wpform_id'])) return '<p><strong>Missing wpform_id</strong></p>';
    if (empty($atts['portal_id'])) return '<p><strong>Missing portal_id</strong></p>';
    if (empty($atts['hubspot_form_id'])) return '<p><strong>Missing hubspot_id</strong></p>';

    ob_start();
    ?>
    <div class="hubspot-forwarder <?php echo $atts['class'];?>"
         data-portal-id="<?php echo esc_attr($atts['portal_id']); ?>"
         data-form-id="<?php echo esc_attr($atts['hubspot_form_id']); ?>">
        <?php echo do_shortcode('[wpforms id="' . esc_attr($atts['wpform_id']) . '"]'); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hubspot_form', 'wpforms_hubspot_wrapper_shortcode');
/*how to use/
[hubspot_form wpform_id="1234" portal_id="243399050" hubspot_form_id="99c2a641-46c6-4e4c-93ec-af799fe08461"]

*/

//map the wpforms fields to hubspot field


// Hook into WPForms form submission
add_action('wpforms_process_complete', 'send_wpforms_to_hubspot', 10, 4);
function send_wpforms_to_hubspot($fields, $entry, $form_data, $entry_id) {

    // Define the Webhook URL (Zapier)
    $webhook_url = defined('ZAPIER_WEBHOOK_URL') ? ZAPIER_WEBHOOK_URL : '';

    // Initialize variables
    $firstname = '';
    $lastname = '';
    $email = '';
    $send_quiz_results = '';
    $subscribe_to_newletters = '';
    $quiz_results =  isset($fields[14]['value']) ? $fields[14]['value'] : null;

    // Match fields based on field labels (e.g., 'First Name', 'Email', etc.)
        foreach ($fields as $field) {
            if (isset($field['name'])) {
                switch (strtolower($field['name'])) {
                    case 'name':
                        $firstname = $field['first'] ?? '';
                        $lastname = $field['last'] ?? '';
                        break;
                    case 'email':
                        $email = $field['value'] ?? '';
                        break;
                    case 'send my results':
                        $send_quiz_results = $field['value'] ?? '';
                        break;
                    case 'subscribe':
                        $subscribe_to_newletters = $field['value'] ?? '';
                        break;
                }
            } 
        }

   


    // Build context
    $context = [
        'pageUri'  => $_SERVER['HTTP_REFERER'] ?? get_permalink() ?: '',
        'pageName' => get_the_title() ?: 'WPForms Submission'
    ];

    // Add HubSpot tracking cookie if present
    if (!empty($_COOKIE['hubspotutk'])) {
        $context['hutk'] = $_COOKIE['hubspotutk'];
    }

    // Build payload for hubspot
    $hub_payload = [
        'fields' => [
            ['name' => 'email', 'value' => $email],
            ['name' => 'firstname', 'value' => $firstname],
            ['name' => 'lastname', 'value' => $lastname],
            ['name' => 'send_my_results', 'value' => $send_quiz_results],
            ['name' => 'ip_address', 'value' => $_SERVER['REMOTE_ADDR'] ?? ''],
            ['name' => 'user_agent', 'value' => $_SERVER['HTTP_USER_AGENT'] ?? ''],
        ],
        'context' => $context
    ];

if (empty($quiz_results)) {
    error_log('Quiz results is empty!');
}

    // Build payload for hubspot
    $zap_payload = array_merge([
        'email'            => $email,
        'firstname'        => $firstname,
        'lastname'         => $lastname,
        'send_my_results'  => $send_quiz_results,
        'subscribe_to_newsletters'  => $subscribe_to_newletters,
        'quiz_results_html' => $quiz_results,
        'source' => 'wpforms',
        
    ], $context);


    // HubSpot Form Details
    $hubspot_portal_id = '243399050'; // Replace with your actual portal ID
    $hubspot_form_guid = '99c2a641-46c6-4e4c-93ec-af799fe08461'; // Replace with your actual form GUID

    $url = "https://api.hsforms.com/submissions/v3/integration/submit/{$hubspot_portal_id}/{$hubspot_form_guid}";

    // Send to HubSpot
    $hubspot_response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($hub_payload),
        'timeout' => 10
    ]);

    // Send to Zapier
    $zapier_response = wp_remote_post($webhook_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($zap_payload),
        'timeout' => 10
    ]);

    // Error logging (optional)
    if (is_wp_error($hubspot_response)) {
        error_log('HubSpot API Error: ' . $hubspot_response->get_error_message());
    } elseif (wp_remote_retrieve_response_code($hubspot_response) !== 200) {
        error_log('HubSpot API Response: ' . wp_remote_retrieve_body($hubspot_response));
    }

    if (is_wp_error($zapier_response)) {
        error_log('Zapier API Error: ' . $zapier_response->get_error_message());
    } elseif (wp_remote_retrieve_response_code($zapier_response) !== 200) {
        error_log('Zapier API Response: ' . wp_remote_retrieve_body($zapier_response));
    }
}




 ?>