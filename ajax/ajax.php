<?php
add_action( 'admin_footer', 'get_seo_terms');

function get_seo_terms() { ?>
    <script>
        
        jQuery(document).ready(function () {
            jQuery("#seo_button").on("click", function () {
                var promptValue = jQuery("textarea[name='ai_blog_generator_prompt_seo_terms[][prompt]']").val();
    
                jQuery.ajax({
                    type: "POST", 
                    url: ajaxurl, 
                    data : {
                        action: "ai_blog_generator_seo_terms",
                        prompt: promptValue 
                    },
                    success: function (response) {
        
                        jQuery("textarea[name='ai_blog_generator_prompt_seo_terms[][term]']").val(response);
                    }
                });
            });
            });

    </script>
    <?php 
}

add_action("wp_ajax_ai_blog_generator_seo_terms", "ai_blog_generator_seo_terms");

function ai_blog_generator_seo_terms() {
    $license_isactive = get_option('ai_blog_generator_license_isactive');
    $openai_api_key = get_option('ai_blog_generator_api_key');
    
    if(isset($license_isactive) && $license_isactive == 'active') {
        $prompt = "Generate a series of relevant search engine optimization keywords based on the following blog post topic: " . sanitize_text_field($_POST['prompt']) ;
		
		$system_content = 'You are a blog post generation assistant who focuses on creating search engine optimized content for user-provided topics.';

        // Prepare the request data
        $data = array(
			'messages' => array(
				array(
					'role' => 'system',
					'content' => $system_content,
				),
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
			'model' => 'gpt-3.5-turbo',
            'max_tokens' => 1000,
			'temperature' => 0.7,
        );

        // Set up the HTTP request headers
        $headers = array(
            'Authorization' => 'Bearer ' . $openai_api_key,
            'Content-Type' => 'application/json',
        );

        $url = 'https://api.openai.com/v1/chat/completions';
		$args = array(
			'body' => json_encode($data),
			'headers' => $headers,
			'method' => 'POST',
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $args);
        
		if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
			if (!empty($body)) {
                $result = json_decode($body, true);
				
				if (isset($result['choices'][0]['message']['content'])) {
                    $generated_content = $result['choices'][0]['message']['content'];
					$generated_content = trim($generated_content);
                
                    echo $generated_content;
                    die();
				} else {
					echo var_dump($body);
				}
			} else {
				$error_message = '';
				if (is_wp_error($response)) {
					echo $error_message = $response->get_error_message();
                    die();
				} else {
					$error_message = 'Unknown error occurred';
                    echo $error_message;
                    die();
				}

				// Log or display the error message
				error_log('ai API error: ' . $error_message);
			}
		}
		else {
			echo "There was an error generating the Seo Terms suggestions.";
			echo "Error: " . wp_remote_retrieve_response_code($response);
		} 
    } 
}

add_action( 'admin_footer', 'get_blog_topics');

function get_blog_topics() { ?>
    <script>
        
        jQuery(document).ready(function () {
            jQuery("#blog_button").on("click", function () {
                jQuery.ajax({
                    type: "POST", 
                    url: ajaxurl, 
                    data : {
                        action: "ai_blog_existent_generator"
                    },
                    success: function (response) {
        
                        jQuery("textarea[name='ai_blog_generator_prompt_seo_terms[][prompt]']").val(response);
                        var promptValue = jQuery("textarea[name='ai_blog_generator_prompt_seo_terms[][prompt]']").val();
    
                        jQuery.ajax({
                            type: "POST", 
                            url: ajaxurl, 
                            data : {
                                action: "ai_blog_generator_seo_terms",
                                prompt: promptValue 
                            },
                        success: function (data) {
            
                            jQuery("textarea[name='ai_blog_generator_prompt_seo_terms[][term]']").val(data);
                            
                        } 
                        });
                    }
                });
            });
        });

    </script>
    <?php 
}

add_action("wp_ajax_ai_blog_existent_generator", "ai_blog_existent_generator");

function ai_blog_existent_generator() {
    $license_isactive = get_option('ai_blog_generator_license_isactive');
    $openai_api_key = get_option('ai_blog_generator_api_key');

   
    $random_posts = get_posts(array(
        'numberposts' => 3,
        'orderby' => 'rand',
        'exclude' => 1
    ));

    $post_details = array();

    foreach ($random_posts as $post) {
        $category = get_the_category($post->ID);   
        $category_name = $category[0]->name;
        $tags = get_the_tags($post->ID);
        $tag_names = array();
        if ($tags) {
            foreach ($tags as $tag) {
                $tag_names[] = $tag->name;
            }
        }
        $post_details[] = array(
            'title' => $post->post_title,
            'category' => $category_name,
            'tags' => $tag_names
        );
    }

    $prompt = 'Generate one blog post idea based on posts within a website. Here are a few example posts: "'. sanitize_text_field($post_details[0]['title']) .'" in category '.  sanitize_text_field($post_details[0]['category']) . (!empty($post_details[0]['tags']) ? ' with tags' . sanitize_text_field($post_details[0]['tags'][0 ]) : '').', "'.  sanitize_text_field($post_details[1]['title']) .'" in category '.  sanitize_text_field($post_details[1]['category']) . (!empty($post_details[1]['tags']) ? ' with tags' . sanitize_text_field($post_details[1]['tags'][0]) : '').', "'. sanitize_text_field($post_details[2]['title']) .'" in category '.  sanitize_text_field($post_details[2]['category']) . (!empty($post_details[2]['tags']) ? ' with tags' . sanitize_text_field($post_details[2]['tags'][0]) : ''). ' Provide only the blog post title.';
    $system_content = 'You are a blog post generation assistant who focuses on creating search engine optimized content for user-provided topics.';

    // Prepare the request data
    $data = array(
        'messages' => array(
            array(
                'role' => 'system',
                'content' => $system_content,
            ),
            array(
                'role' => 'user',
                'content' => $prompt,
            ),
        ),
        'model' => 'gpt-3.5-turbo',
        'max_tokens' => 1000,
        'temperature' => 0.7,
    );

    // Set up the HTTP request headers
    $headers = array(
        'Authorization' => 'Bearer ' . $openai_api_key,
        'Content-Type' => 'application/json',
    );

    $url = 'https://api.openai.com/v1/chat/completions';
    $args = array(
        'body' => json_encode($data),
        'headers' => $headers,
        'method' => 'POST',
        'timeout' => 80,
    );

    $response = wp_remote_request($url, $args);

    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $body = wp_remote_retrieve_body($response);
        if (!empty($body)) {
            $result = json_decode($body, true);
            
            if (isset($result['choices'][0]['message']['content'])) {
                $generated_content = $result['choices'][0]['message']['content'];
            
                // Trim the generated text again to remove any leading/trailing whitespaces or newline characters
                $generated_content = trim($generated_content);
        
                echo $generated_content;
                die();
            } else {
                echo var_dump($body);
            }
        } else {
            $error_message = '';
            if (is_wp_error($response)) {
					echo $error_message = $response->get_error_message();
                    die();
            } else {
                $error_message = 'Unknown error occurred';
                echo $error_message;
                die();
            }
            // Log or display the error message
            error_log('ai API error: ' . $error_message);
        }
    } else {
        echo "There was an error generating the post content.";
        echo "Error: " . wp_remote_retrieve_response_code($response);
    }
}
