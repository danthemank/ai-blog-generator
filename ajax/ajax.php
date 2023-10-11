<?php
add_action('admin_footer', 'get_seo_terms');

function get_seo_terms()
{ ?>
    <script>
        jQuery(document).ready(function() {
            jQuery("#scheduled-post-topics-table tbody tr.rowButton").each(function(index) {
                var adjustedIndex = index + 1;
                jQuery("#seo_button" + adjustedIndex).on("click", function() {
                    var promptValue = jQuery("#prompt_" + adjustedIndex).val();

                    jQuery.ajax({
                        type: "POST",
                        url: ajaxurl,
                        data: {
                            action: "ai_blog_generator_seo_terms",
                            prompt: promptValue
                        },
                        success: function(response) {

                            jQuery("#term_" + adjustedIndex).val(response);
                        }
                    });
                });
            });
        });
    </script>
<?php
}

add_action("wp_ajax_ai_blog_generator_seo_terms", "ai_blog_generator_seo_terms");

function ai_blog_generator_seo_terms()
{
    $license_isactive = get_option('ai_blog_generator_license_isactive');
    $openai_api_key = get_option('ai_blog_generator_api_key');

    if (isset($license_isactive) && $license_isactive == 'active') {
        $prompt = "Generate a series of relevant search engine optimization keywords based on the following blog post topic: " . sanitize_text_field($_POST['prompt']);

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
        } else {
            echo "There was an error generating the Seo Terms suggestions.";
            echo "Error: " . wp_remote_retrieve_response_code($response);
        }
    }
}

add_action('admin_footer', 'get_blog_topics');

function get_blog_topics()
{ ?>
    <script>
        jQuery("#scheduled-post-topics-table tbody tr.rowButton").each(function(index) {
            var adjustedIndex = index + 1;
            jQuery("#blog_button" + adjustedIndex).on("click", function() {
                jQuery.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        action: "ai_blog_existent_generator"
                    },
                    success: function(response) {

                        jQuery("#prompt_" + adjustedIndex).val(response);

                        jQuery.ajax({
                            type: "POST",
                            url: ajaxurl,
                            data: {
                                action: "ai_blog_generator_seo_terms",
                                prompt: response
                            },
                            success: function(data) {

                                jQuery("#term_" + adjustedIndex).val(data);

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

function ai_blog_existent_generator()
{
    $license_isactive = get_option('ai_blog_generator_license_isactive');
    $openai_api_key = get_option('ai_blog_generator_api_key');
    if (isset($license_isactive) && $license_isactive == 'active') {

        $posts = get_posts(array(
            'exclude' => 1
        ));
        $post_count = count($posts);
    
       if( $post_count != 0 ) {
    
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
        
           $prompt = 'Generate one blog post idea based on posts within a website. Here are a few example posts: "' . sanitize_text_field($post_details[0]['title']) . '" in category ' .  sanitize_text_field($post_details[0]['category']) . (!empty($post_details[0]['tags']) ? ' with tags' . sanitize_text_field($post_details[0]['tags'][0]) : '') . ', "' .  sanitize_text_field($post_details[1]['title']) . '" in category ' .  sanitize_text_field($post_details[1]['category']) . (!empty($post_details[1]['tags']) ? ' with tags' . sanitize_text_field($post_details[1]['tags'][0]) : '') . ', "' . sanitize_text_field($post_details[2]['title']) . '" in category ' .  sanitize_text_field($post_details[2]['category']) . (!empty($post_details[2]['tags']) ? ' with tags' . sanitize_text_field($post_details[2]['tags'][0]) : '') . ' Provide only the blog post title.';
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
    }
}

add_action('admin_footer', 'more_fields');

function more_fields() {
?>
    <script>
        jQuery(document).ready(function() {
            jQuery("#more_fields").on("click", function() {
                var blogTooltipText = "Generate a blog post topic + relevant SEO terms based on existing posts within your website's blog.";
                var seoTooltipText = "Generate a list of SEO terms based on the Blog Post Topic.";
                var storeTooltipText = "Generate a blog post topic + relevant SEO terms based on existing products in your woocommerce store.";
                var table = document.getElementById("scheduled-post-topics-table");
                var rowCount = jQuery("#scheduled-post-topics-table tbody tr.row").length;
                var newRow = table.insertRow(-1);
                newRow.className = "row";
                var newButton = table.insertRow(-1);
                newButton.className = "rowButton";
                newRow.innerHTML = '<td><textarea placeholder="New Blog Topic" name="ai_blog_generator_prompt_seo_terms[][prompt]" id="prompt_' + (rowCount + 1) + '" style="resize: none; width: 100%;"></textarea></td><td><textarea placeholder="SEO Terms" name="ai_blog_generator_prompt_seo_terms[][term]" id="term_' + (rowCount + 1) + '" style="resize: none; width: 100%;"></textarea></td>';
                newButton.innerHTML = '<td><div class="tooltip"><button id="blog_button' + (rowCount + 1) + '" class="blog_button' + (rowCount + 1) + '" name="blog_button' + (rowCount + 1) + '" type="button">Suggest Based on Blog</button><div class="tooltip-text">' + blogTooltipText + '</div></div><div class="tooltip"><button id="store_button' + (rowCount + 1) + '" class="store_button' + (rowCount + 1) + '" name="store_button' + (rowCount + 1) + '" type="button">Suggest Based on Store</button><div class="tooltip-text">'+ storeTooltipText +'</div></div></td><td><div class="tooltip"><button id="seo_button' + (rowCount + 1) + '" class="seo_button' + (rowCount + 1) + '" name="seo_button' + (rowCount + 1) + '"  type="button">SEO Suggestions</button><div class="tooltip-text">' + seoTooltipText + '</div></div></td>';


                jQuery("#scheduled-post-topics-table tbody tr.rowButton").each(function(index) {
                    if (index !== 0) {
                        var adjustedIndex = index + 1;
                        jQuery("#blog_button" + adjustedIndex).on("click", function() {
                            jQuery.ajax({
                                type: "POST",
                                url: ajaxurl,
                                data: {
                                    action: "ai_blog_existent_generator"
                                },
                                success: function(response) {
    
                                    jQuery("#prompt_" + adjustedIndex).val(response);
                                    jQuery.ajax({
                                        type: "POST",
                                        url: ajaxurl,
                                        data: {
                                            action: "ai_blog_generator_seo_terms",
                                            prompt: response
                                        },
                                        success: function(data) {
    
                                            jQuery("#term_" + adjustedIndex).val(data);
    
                                        }
                                    });
                                }
                            });
                        });
                        jQuery("#seo_button" + adjustedIndex).on("click", function() {
                            var promptValue = jQuery("#prompt_" + adjustedIndex).val();

                            jQuery.ajax({
                                type: "POST",
                                url: ajaxurl,
                                data: {
                                    action: "ai_blog_generator_seo_terms",
                                    prompt: promptValue
                                },
                                success: function(response) {

                                    jQuery("#term_" + adjustedIndex).val(response);
                                }
                            });
                        });
                        jQuery("#store_button" + adjustedIndex).on("click", function() {
                            jQuery.ajax({
                                type: "POST",
                                url: ajaxurl,
                                data: {
                                    action: "ai_blog_existent_store_generator"
                                },
                                success: function(response) {

                                    jQuery("#prompt_" + adjustedIndex).val(response);

                                    jQuery.ajax({
                                        type: "POST",
                                        url: ajaxurl,
                                        data: {
                                            action: "ai_blog_generator_seo_terms",
                                            prompt: response
                                        },
                                        success: function(data) {

                                            jQuery("#term_" + adjustedIndex).val(data);

                                        }
                                    });
                                }
                            });
                        });
                    }
                });
            });
        });
    </script>
<?php
}

add_action('admin_footer', 'get_store');

function get_store()
{ ?>
    <script>
        jQuery("#scheduled-post-topics-table tbody tr.rowButton").each(function(index) {
            var adjustedIndex = index + 1;
            jQuery("#store_button" + adjustedIndex).on("click", function() {
                jQuery.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        action: "ai_blog_existent_store_generator"
                    },
                    success: function(response) {

                        jQuery("#prompt_" + adjustedIndex).val(response);

                        jQuery.ajax({
                            type: "POST",
                            url: ajaxurl,
                            data: {
                                action: "ai_blog_generator_seo_terms",
                                prompt: response
                            },
                            success: function(data) {

                                jQuery("#term_" + adjustedIndex).val(data);

                            }
                        });
                    }
                });
            });
        });
    </script>
<?php
}

add_action("wp_ajax_ai_blog_existent_store_generator", "ai_blog_existent_store_generator");

function ai_blog_existent_store_generator()
{
    $license_isactive = get_option('ai_blog_generator_license_isactive');
    $openai_api_key = get_option('ai_blog_generator_api_key');
    if (isset($license_isactive) && $license_isactive == 'active') {
        $products = wc_get_products(array(
            'limit' => 3,
            'orderby' => 'rand', 
            'status' => 'publish',
        ));
    
        if (count($products) != 0) {
            foreach ($products as $product) {
                $product_id = $product->get_id();
                $product_name = $product->get_name();
                $product_categories = wp_get_post_terms($product_id, 'product_cat');
                $category_names = array();
                foreach ($product_categories as $category) {
                    $category_names[] = $category->name;
                }
    
                $product_tags = wp_get_post_terms($product_id, 'product_tag');
                $tag_names = array();
                foreach ($product_tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            
                $product_details[] = array(
                    'title' => $product_name,
                    'category' => $category_names,
                    'tags' =>  $tag_names
                );
            }
        }        
    
        $prompt = 'Generate one blog post idea based on products within an ecommerce website. Here are a few example products: "' . sanitize_text_field($product_details[0]['title']) . '" in category ' .  sanitize_text_field($product_details[0]['category'][0]) . (!empty($product_details[0]['tags']) ? ' with tags' . sanitize_text_field($product_details[0]['tags'][0]) : '') . ', "' .  sanitize_text_field($product_details[1]['title']) . '" in category ' .  sanitize_text_field($product_details[1]['category'][0]) . (!empty($product_details[1]['tags']) ? ' with tags' . sanitize_text_field($product_details[1]['tags'][0]) : '') . ', "' . sanitize_text_field($product_details[2]['title']) . '" in category ' .  sanitize_text_field($product_details[2]['category'][0]) . (!empty($product_details[2]['tags']) ? ' with tags' . sanitize_text_field($product_details[2]['tags'][0]) : '') . ' Provide only the blog post title.';
    
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
}