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

function ai_blog_generator_seo_terms() {
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

function get_blog_topics() { ?>
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

function ai_blog_existent_generator() {
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
    $woocommerce_active = is_plugin_active('woocommerce/woocommerce.php');
    ?>
    <script>
        jQuery(document).ready(function() {
            var promptShownUn = false;
            var promptShownDa = false;
            jQuery("#more_fields").on("click", function() {
                var blogTooltipText = "Generate a blog post topic + relevant SEO terms based on existing posts within your website's blog.";
                var seoTooltipText = "Generate a list of SEO terms based on the Blog Post Topic.";
                var storeTooltipText = "Generate a blog post topic + relevant SEO terms based on existing products in your woocommerce store.";
                var pageTooltipText = "Generate a blog post topic + relevant SEO terms based on existing pages in your website.";
                var table = document.getElementById("scheduled-post-topics-table");
                var rowCount = jQuery("#scheduled-post-topics-table tbody tr.row").length;
                var newRow = table.insertRow(-1);
                newRow.className = "row";
                var newButton = table.insertRow(-1);
                newButton.className = "rowButton";
                var newUnsplash = table.insertRow(-1);
                newUnsplash.className = "unsplash_input"+ (rowCount + 1);
                newUnsplash.style.display = "none";
                var newDalle = table.insertRow(-1);
                newDalle.className = "dalle_input"+ (rowCount + 1);
                newDalle.style.display = "none";

                newRow.innerHTML = '<td><textarea placeholder="New Blog Topic" name="ai_blog_generator_prompt_seo_terms[][prompt]" id="prompt_' + (rowCount + 1) + 
                '" style="resize: none; width: 100%;"></textarea></td><td><textarea placeholder="SEO Terms" name="ai_blog_generator_prompt_seo_terms[][term]" id="term_' + (rowCount + 1) + 
                '" style="resize: none; width: 100%;"></textarea></td><td class="radios"><input type="radio" name="royalty'+ (rowCount + 1) +
                '" id="royalty'+ (rowCount + 1) +'">Royalty Free from Unsplash <br><input type="radio" name="dall-e'+ (rowCount + 1) +'" id="dall-e'+ (rowCount + 1) +'">Generate with DALL-E 2</td>';

                newUnsplash.innerHTML =`<td class="default_image${rowCount + 1}" colspan="3">
                                <div id="image_table_settings">
                                    <div>
                                        <label for="ai_post_featured_image${rowCount + 1}">
                                        ${'Featured Image'} 
                                        </label><br />
                                        <input type="text" id="ai_post_featured_image${rowCount + 1}" name="ai_post_featured_image${rowCount + 1}" style="width: 25%;" />
                                        <input type="button" class="button" id="upload_image_button${rowCount + 1}" value="${'Upload Image'}" />
                                        <input type="button" class="button" id="unsplash_search_button${rowCount + 1}" value="${'Search Unsplash'}" />
                                        <p class="description">
                                        ${'Select or upload the default featured image for new posts.'}  
                                        </p>
                                </div>
                                </div>
                                <div id="result_image${rowCount + 1}" class="image-results"></div>
                            </td>`;
                newDalle.innerHTML =`<tr class="dalle_input${rowCount + 1}" style="display: none;">
                                        <td class="dalle_image${rowCount + 1}" colspan="3">
                                            <div id='dalle_table'>
                                                <div>
                                                    <label for="ai_post_dalle${rowCount + 1}">
                                                    ${'Image Url'}
                                                    </label>
                                                    <br />
                                                    <input type="text" id="ai_post_dalle${rowCount + 1}" name="ai_post_dalle${rowCount + 1}" style="width: 25%;"/>
                                                    <input type="button" class="button" id="dalle_search_button${rowCount + 1}" value="${'Generate DALL-E 2'}" data-id="${rowCount + 1}"/>
                                                    <p class="description">
                                                    ${'Type what you want to generate with DALL-E 2.'}  
                                                    </p>
                                                </div>
                                            </div>
                                        <div id="dalle_result${rowCount + 1}" class="image-results"></div></td>
							        </tr>`;
                newButton.innerHTML = '<td><div class="tooltip"><button id="blog_button' + (rowCount + 1) + '" class="blog_button' + (rowCount + 1) + '" name="blog_button' + (rowCount + 1) + 
                '" type="button">Suggest Based on Blog</button><div class="tooltip-text">' + blogTooltipText + '</div></div><div class="tooltip"><button style="margin-left: 4px;" id="page_button' + (rowCount + 1) + 
                '" class="page_button' + (rowCount + 1) + '" name="page_button' + (rowCount + 1) + '" type="button">Suggest Based on Pages</button><div class="tooltip-text">'+pageTooltipText +
                '</div></div><td><div class="tooltip"><button style="margin-left: 4px;" id="seo_button' + (rowCount + 1) + '" class="seo_button' + (rowCount + 1) + '" name="seo_button' + (rowCount + 1) + 
                '"  type="button">Suggest SEO Terms Based on Current Post Title</button><div class="tooltip-text">' + seoTooltipText + '</div></div></td>';
                if (<?php echo $woocommerce_active ? 'true' : 'false'; ?>) {
                    newButton.innerHTML = '<td><div class="tooltip"><button id="blog_button' + (rowCount + 1) + '" class="blog_button' + (rowCount + 1) + '" name="blog_button' + (rowCount + 1) + 
                    '" type="button">Suggest Based on Blog</button><div class="tooltip-text">' + blogTooltipText + 
                    '</div></div><div class="tooltip"><button style="margin-left: 4px;" id="store_button' + (rowCount + 1) + '" class="store_button' + (rowCount + 1) + '" name="store_button' + (rowCount + 1) + 
                    '" type="button">Suggest Based on Store</button><div class="tooltip-text">'+ storeTooltipText +
                    '</div></div><div class="tooltip"><button style="margin-left: 4px;" id="page_button' + (rowCount + 1) + '" class="page_button' + (rowCount + 1) + '" name="page_button' + (rowCount + 1) + 
                    '" type="button">Suggest Based on Pages</button><div class="tooltip-text">'+pageTooltipText+
                    '</div></div></td><td><div class="tooltip"><button style="margin-left: 4px;" id="seo_button' + (rowCount + 1) + '" class="seo_button' + (rowCount + 1) + '" name="seo_button' + (rowCount + 1) + 
                    '"  type="button">Suggest SEO Terms Based on Current Post Title</button><div class="tooltip-text">' + seoTooltipText + '</div></div></td>';
                }
                
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
                        jQuery("#page_button" + adjustedIndex).on("click", function() {
                            jQuery.ajax({
                                type: "POST",
                                url: ajaxurl,
                                data: {
                                    action: "ai_blog_existent_pages_generator"
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
                    jQuery("#dall-e" + adjustedIndex).change(function() {
                        if (jQuery(this).is(':checked')) {
                            jQuery('.dalle_input'+adjustedIndex).css('display', 'table-row');
                            jQuery('#dalle_search_button'+adjustedIndex).on('click', function () {
                                var dataID = jQuery(this).data('id')
                               /*  if (!promptShownDa) {
                                    promptShownDa = true; */
                                    if (!jQuery(this).data('promptShown')) {
                                        jQuery(this).data('promptShown', true);

                                        var query = prompt('Enter your generation term for DALL-E 2 images:'+dataID);
                                        if (query && query.trim() !== '') {
                                            jQuery.ajax({
                                                url: ajaxurl, 
                                                type: 'POST',
                                                data: {
                                                    action: 'key_send' 
                                                },
                                                success: function(response) {
                                                    var openApiKey = response.open_api_key;
                                                    var data = {
                                                        prompt: query,
                                                        n: 10,
                                                        size: '512x512'
                                                    };
                                                    var headers = {
                                                        "Authorization": "Bearer "+openApiKey,
                                                        "Content-Type": "application/json"
                                                    };
                                                    jQuery.ajax({
                                                        url: 'https://api.openai.com/v1/images/generations',
                                                        type: 'POST',
                                                        headers: headers,
                                                        data: JSON.stringify(data),
                                                        success: function(data) {
                                                            var imageResults = jQuery('#dalle_result'+adjustedIndex);
                                                            imageResults.empty();
                                                            data.data.forEach(function(item) {

                                                                var imageElement = '<img src="'+item.url+'" data-url="' + item.url + '" class="thumbnail-image">';
                                                                imageResults.append(imageElement);
                                                            });
                                            
                                                            imageResults.find('img').on('click', function() {
                                                                var imageUrl = jQuery(this).data('url');
                                                                jQuery('#ai_post_dalle'+adjustedIndex).val(imageUrl);
                                                            });
                                                            promptShownDa = false;
                                                    },
                                                    error: function() {
                                                        alert('Error when generating images in DALL-E 2.');
                                                        promptShownDa = false;
                                                    }
                                                });
                                            }
                                        });
                                    } /* else {
                                        promptShownDa = false;
                                    } */
                                }
                            });
                    } else {
                        jQuery('#dalle_input').css('display', 'none');
                    }
                });
                jQuery("#royalty" + adjustedIndex).change(function() {
                    if (jQuery(this).is(':checked')) {
                        jQuery('.unsplash_input'+adjustedIndex).css('display', 'table-row');
                            jQuery('#unsplash_search_button'+adjustedIndex).on('click', function () {
                                if (!promptShownUn) {
                                    var query = prompt('Enter your search term for Unsplash images:');
                                    if (query && query.trim() !== '') {
                                        jQuery.ajax({
                                            url: ajaxurl, 
                                            type: 'POST',
                                            data: {
                                                action: 'key_send' 
                                            },
                                            success: function(response) {
                                                var unsplashApiKey = response.unsplash_api_key;
                                                jQuery.ajax({
                                                    url: 'https://api.unsplash.com/search/photos',
                                                    method: 'GET',
                                                    headers: {
                                                        'Authorization': 'Client-ID ' + unsplashApiKey
                                                    },
                                                    data: {
                                                        query: query
                                                    },
                                                    success: function(data) {
                                                        var imageResults = jQuery('#result_image'+adjustedIndex);
                                                        imageResults.empty();
                                        
                                                        data.results.forEach(function(photo) {
                                                            var imageElement = '<img src="' + photo.urls.thumb + '" alt="' + photo.alt_description + '" data-url="' + photo.urls.regular + '" class="thumbnail-image">';
                                                            imageResults.append(imageElement);
                                                        });
                                        
                                                        imageResults.find('img').on('click', function() {
                                                            var imageUrl = jQuery(this).data('url');
                                                            jQuery('#ai_post_featured_image'+adjustedIndex).val(imageUrl);
                                                        });
                                                    },
                                                    error: function() {
                                                        alert('Error when searching for images on Unsplash.');
                                                    }
                                                });
                                            }
                                        });
                                    }
                                    promptShownUn = true;
                                }
                            });

                            jQuery('#upload_image_button'+adjustedIndex).on('click', function () {
                            // Check if defaultImageField exists and is empty or not set
                                if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                                    wp.media.editor.send.attachment = function(props, attachment) {
                                        updateImage(attachment.url);
                                    };
                                wp.media.editor.open('#upload_image_button'+adjustedIndex);
                                } else {
                                    // If the field is already set, allow the user to update the image
                                    var imageUrl = defaultImageField.value;
                                    wp.media.editor.send.attachment = function(props, attachment) {
                                    updateImage(attachment.url);
                                };
                                wp.media.editor.open('#upload_image_button'+adjustedIndex, imageUrl);
                            }
                            return false;
                        });

                    } else {
                        jQuery('.unsplash_input').css('display', 'none');
                    }
                });
                promptShownUn = false;

            });
        });
    });
    </script>
<?php
}

add_action('admin_footer', 'get_store');

function get_store() { ?>
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

function ai_blog_existent_store_generator() {
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

add_action('admin_footer', 'get_page_suggest');

function get_page_suggest() { ?>
    <script>
        jQuery("#scheduled-post-topics-table tbody tr.rowButton").each(function(index) {
            var adjustedIndex = index + 1;
            jQuery("#page_button" + adjustedIndex).on("click", function() {
                jQuery.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        action: "ai_blog_existent_pages_generator"
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

add_action("wp_ajax_ai_blog_existent_pages_generator", "ai_blog_existent_pages_generator");

function ai_blog_existent_pages_generator () {
    $license_isactive = get_option('ai_blog_generator_license_isactive');
    $openai_api_key = get_option('ai_blog_generator_api_key');
    if (isset($license_isactive) && $license_isactive == 'active') {

        $random_pages = get_pages(array(
            'number' => 3, 
            'orderby' => 'rand', 
            'status' => 'publish',
            'exclude' => 1,

        ));
        if (count($random_pages) != 0) {  
            foreach ($random_pages as $page) {
                $categories = get_the_category($page->ID);
                $category_names = array();
                
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $category_names[] = $category->name;
                    }
                }
                $tags = get_the_tags($page->ID);
                $tag_names = array();
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $tag_names[] = $tag->name;
                    }
                }
                
                $page_details[] = array(
                    'title' => $page->post_title,
                    'category' => $category_names,
                    'tags' =>  $tag_names
                );
            }
        }     
        $prompt = 'Generate one blog post idea based on pages within in my website. Here are a few example pages: "' . sanitize_text_field($page_details[0]['title']). '" ' .  (!empty($page_details[0]['category'] ? 'in category ' .  sanitize_text_field($page_details[0]['category'][0]) : '')).''.(!empty($page_details[0]['tags']) ? ' with tags' . sanitize_text_field($page_details[0]['tags'][0]) : '') . ', "' .  sanitize_text_field($page_details[1]['title']) . '" ' . (!empty($page_details[1]['category'] ? 'in category ' . sanitize_text_field($page_details[1]['category'][0]) : '')).''.(!empty($page_details[1]['tags']) ? ' with tags' . sanitize_text_field($page_details[1]['tags'][0]) : '') . ', "' . sanitize_text_field($page_details[2]['title']) . '" ' .  (!empty($page_details[2]['category'] ? 'in category ' . sanitize_text_field($page_details[2]['category'][0]) : '')) .''. (!empty($page_details[2]['tags']) ? ' with tags' . sanitize_text_field($page_details[2]['tags'][0]) : '') . ' Provide only the blog post title.';
    
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

add_action('admin_footer', 'unsplash');

function unsplash() { ?>
    <script>
        jQuery("#scheduled-post-topics-table tbody tr.row").each(function(index) {
            var adjustedIndex = index + 1;
            jQuery("#royalty" + adjustedIndex).change(function() {
                if (jQuery(this).is(':checked')) {
                    jQuery('.unsplash_input'+adjustedIndex).css('display', 'table-row');
                        jQuery('#unsplash_search_button'+adjustedIndex).on('click', function () {
                            var query = prompt('Enter your search term for Unsplash images:');
                            if (query && query.trim() !== '') {
                                jQuery.ajax({
                                    url: ajaxurl, 
                                    type: 'POST',
                                    data: {
                                        action: 'key_send' 
                                    },
                                    success: function(response) {
                                        var unsplashApiKey = response.unsplash_api_key;
                                        jQuery.ajax({
                                            url: 'https://api.unsplash.com/search/photos',
                                            method: 'GET',
                                            headers: {
                                                'Authorization': 'Client-ID ' + unsplashApiKey
                                            },
                                            data: {
                                                query: query
                                            },
                                            success: function(data) {
                                                var imageResults = jQuery('#result_image'+adjustedIndex);
                                                imageResults.empty();
                                
                                                data.results.forEach(function(photo) {
                                                    var imageElement = '<img src="' + photo.urls.thumb + '" alt="' + photo.alt_description + '" data-url="' + photo.urls.regular + '" class="thumbnail-image">';
                                                    imageResults.append(imageElement);
                                                });
                                
                                                imageResults.find('img').on('click', function() {
                                                    var imageUrl = jQuery(this).data('url');
                                                    jQuery('#ai_post_featured_image'+adjustedIndex).val(imageUrl);
                                                });
                                            },
                                            error: function() {
                                                alert('Error when searching for images on Unsplash.');
                                            }
                                        });
                                    }
                                });
                            }
                        });

                        jQuery('#upload_image_button'+adjustedIndex).on('click', function () {
                        // Check if defaultImageField exists and is empty or not set
                            if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                                wp.media.editor.send.attachment = function(props, attachment) {
                                    updateImage(attachment.url);
                                };
                            wp.media.editor.open('#upload_image_button'+adjustedIndex);
                            } else {
                                // If the field is already set, allow the user to update the image
                                var imageUrl = defaultImageField.value;
                                wp.media.editor.send.attachment = function(props, attachment) {
                                updateImage(attachment.url);
                            };
                            wp.media.editor.open('#upload_image_button'+adjustedIndex, imageUrl);
                        }
                        return false;
                    });

                } else {
                    jQuery('.unsplash_input').css('display', 'none');
                }
            });
        });
    </script>
<?php
}


function unsplash_generator() {

}