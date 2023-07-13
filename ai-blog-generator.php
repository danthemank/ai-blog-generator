<?php
/**
 * Plugin Name: AI Blog Post Generator
 * Description: Generate blog posts using artificial intelligence tools.
 * Author: Media & Technology Group, LLC
 * Version: 1.1
 */

class ai_blog_post_generator {
    private $openai_api_key;
	private $ai_default_post_length;
	private $ai_post_default_status;
	private $ai_post_default_category;
	private $ai_post_default_comment_status;
	private $plugin_name;
	private $version;

    public function __construct() {
		$this->plugin_name = 'AI Blog Post Generator';
		$this->version = '1.1';
		
        $this->openai_api_key = get_option('ai_blog_generator_api_key');
		$this->ai_default_post_length = get_option('ai_default_post_length');
		$this->ai_post_default_status = get_option('ai_post_default_status');
		$this->ai_post_default_category = get_option('ai_post_default_category');
		$this->ai_post_default_comment_status = get_option('ai_post_default_comment_status');

        add_action('admin_menu', array($this, 'add_menu_link'));
        add_action('admin_post_generate_blog_post', array($this, 'handle_generator_form_submission'));
        add_action('admin_init', array($this, 'register_settings'));
		
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
	
	public function enqueue_admin_scripts() {
		wp_enqueue_script(
			'blog-generator-admin-script',
			plugin_dir_url(__FILE__) . 'js/scripts.js',
			array(),
			'1.0',
			true
		);
	}

    public function add_menu_link() {
        add_posts_page('AI Blog Generator', 'AI Generator', 'manage_options', 'ai-blog-generator', array($this, 'render_generator_page'));
        add_submenu_page('options-general.php', 'AI Blog Post Generator Settings', 'AI Generator Settings', 'manage_options', 'ai-blog-generator-settings', array($this, 'render_settings_page'));
    }

    public function render_generator_page() {
		if(isset($_GET['new_post']) && $_GET['new_post'] !== '') {
			echo '<div class="notice notice-success is-dismissible">';
			echo "<p>New post successfully created!</p>";
			echo "<p>" . esc_html( get_the_title($_GET['new_post']) ) . "</p>";
			
			//Display excerpt
			
			$categories = get_the_category($_GET['new_post']);
			if ( ! empty( $categories ) ) {
				echo '<p>In the <a href="' . esc_url( get_category_link( $categories[0]->term_id ) ) . '">' . esc_html( $categories[0]->name ) . '</a> category with tags: ';
			}
			
			$post_tags = get_the_tags($_GET['new_post']);
			$count = 0;

			if ( ! empty( $post_tags ) ) {
				foreach ( $post_tags as $tag ) {
					echo '<a href="' . esc_attr( get_tag_link( $tag->term_id ) ) . '">' . __( $tag->name ) . '</a>';
					if($count !== count($post_tags) - 1) { echo ', '; }
					$count++;
				}
			}
			
			echo '</p>';
			
			echo "<p><a href='" . site_url( '/wp-admin/post.php?post=' . $_GET['new_post'] . '&action=edit' ) . "'>Edit Post</a></p>";
			echo '</div>';
		}
		
		if(isset($_GET['error']) && $_GET['error'] !== '') {
			echo '<div class="notice notice-error is-dismissible">';
			echo "<p>There was an issue generating the blog post! Please contact support@mediatech.group and share this error message:</a></p>";
			echo "<p>" . $_GET['error'] . "</p>";
			echo '</div>';
		}
        ?>
        <div class="wrap">
            <div style="width:100%;display: flex;align-items: center;">
				<div style="width:50%;flex-grow: 1;"><h1><?php echo $this->plugin_name; ?></h1></div>
				<div style="width:50%;flex-grow: 1;"><p style="font-style:italic;text-align: right;">Version <?php echo $this->version; ?></p></div>
			</div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=generate_blog_post')); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ai-prompt">Prompt</label></th>
                        <td>
							<p style="font-style:italic;">Write me a blog post given the following instructions and description: </p>
							<textarea style="width:100%;" id="ai-prompt" name="ai_blog_generator_prompt"></textarea>
						</td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai-seo-terms">SEO Terms</label></th>
                        <td>
							<p style="font-style:italic;">Separate terms by comma (example: healthy dog food, puppy food, beagle): </p>
							<input type="text" style="width:100%;" id="ai-seo-terms" name="ai_blog_generator_seo_terms">
						</td>
                    </tr>
					<tr>
                        <th scope="row"><label for="ai_post_default_category">Post Category</label></th>
                        <td>
							<?php
								$categories = get_categories(array(
									'taxonomy' => 'category',
									'hide_empty' => false
								));

								if (!empty($categories)) {		
								?>
									<select id="ai_post_default_category" name="ai_post_default_category">
										<?php foreach ($categories as $category) { ?>
											<option value="<?php echo esc_attr($category->term_id); ?>"<?php if($this->ai_post_default_category == esc_attr($category->term_id)) { echo " selected='selected'"; } ?>>
												<?php echo esc_html($category->name); ?>
											</option>
										<?php } ?>
									</select>
								<?php
								}
								else {
									echo 'No categories found. Add your categories <a href="' . site_url( '/wp-admin/edit-tags.php?taxonomy=category' ) . '">here</a>.';
								}
							?>
						</td>
                    </tr>
					<tr>
                        <th scope="row"><label for="ai_post_default_comment_status">Allow Comments</label></th>
                        <td>
							<select id="ai_post_default_comment_status" name="ai_post_default_comment_status">
								<option value="">Choose</option>
								<option value="open"<?php if($this->ai_post_default_comment_status == "open") { echo " selected='selected'"; } ?>>Allow Comments</option>
								<option value="closed"<?php if($this->ai_post_default_comment_status == "closed") { echo " selected='selected'"; } ?>>Disallow Comments</option>
							</select>
						</td>
                    </tr>
                </table>
                <?php wp_nonce_field('generate_blog_post', 'generate_blog_post_nonce'); ?>
                <?php
					if(!isset($this->openai_api_key) || $this->openai_api_key !== "") {
						echo '<input type="submit" class="button button-primary" value="Generate Post">';
					}
					else {
						echo 'You must add your OpenAI API key on the <a href="' . site_url( '/wp-admin/options-general.php?page=ai-blog-generator-settings' ) . '">settings page</a> to use this function.';
					}
				?>
            </form>
        </div>
        <?php
    }

    public function handle_generator_form_submission() {
        if (isset($_POST['generate_blog_post_nonce']) && wp_verify_nonce($_POST['generate_blog_post_nonce'], 'generate_blog_post')) {
            if (isset($_POST['ai_blog_generator_prompt']) && isset($_POST['ai_blog_generator_seo_terms'])) {
                $prompt = sanitize_text_field($_POST['ai_blog_generator_prompt']);
                $seo_terms = sanitize_text_field($_POST['ai_blog_generator_seo_terms']);
				$post_category = $_POST['ai_post_default_category'];
				$post_comment_status = $_POST['ai_post_default_comment_status'];

                if (!empty($prompt)) {
                    $this->generate_blog_post($prompt, $seo_terms, $post_category, $post_comment_status);
                }
            }
        }
        exit;
    }
	
	public function generate_blog_post($prompt, $seo_terms, $post_category, $post_comment_status) {
		$prompt = "Write me a blog post given the following instructions and description: " . sanitize_text_field($prompt) . " Use the following keywords to optimize for search engines: " . $seo_terms;
		
		$default_post_length = $this->ai_default_post_length ?: '400';
		
		$system_content = 'You are a blog post generation assistant who focuses on creating search engine optimized content for user-provided topics. Write complete posts using ' . $default_post_length . ' as the maximum number of words.';

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
            'Authorization' => 'Bearer ' . $this->openai_api_key,
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
					
					$prefix = "Title: ";
					$start_index = strpos($generated_content, $prefix) + strlen($prefix);
					$end_index = strpos($generated_content, "\n", $start_index);

					if ($start_index !== false && $end_index !== false) {
						$generated_title = substr($generated_content, $start_index, $end_index - $start_index);

						// Remove leading/trailing whitespaces and newline characters
						$generated_title = trim($generated_title);
					}
					
					// Remove the title portion from the generated text
					$generated_content = str_replace($generated_title, '', $generated_content);
					
					$generated_content = str_replace($prefix . $generated_title . "\n", '', $generated_content);

					// Trim the generated text again to remove any leading/trailing whitespaces or newline characters
					$generated_content = trim($generated_content);
					
					 // Wrap section titles in <h3> tags
					$generated_content = preg_replace('/\n([A-Za-z\s:]+)\n/', "\n<h3>$1</h3>\n", $generated_content);

					$new_post = $this->generate_post($generated_title, $generated_content, $seo_terms, $post_category, $post_comment_status);
					$redirect_link = site_url( '/wp-admin/edit.php?page=ai-blog-generator&new_post=' . $new_post );
				}
				else {
					echo var_dump($body);
				}
			}
			else {
				$error_message = '';
				if (is_wp_error($response)) {
					$error_message = $response->get_error_message();
				} else {
					$error_message = 'Unknown error occurred';
				}

				// Log or display the error message
				error_log('ai API error: ' . $error_message);
				
				$redirect_link = site_url( '/wp-admin/edit.php?page=ai-blog-generator&error=' . $error_message );
			}
			
			wp_redirect($redirect_link);
			exit;
		}
		else {
			echo "There was an error generating the post content.";
			echo "Error: " . wp_remote_retrieve_response_code($response);
		}
    }

    public function generate_post($post_title, $post_content, $seo_terms, $post_category, $post_comment_status) {
        $default_post_status = $this->ai_post_default_status ?: 'draft';
		$default_post_category = $this->ai_post_default_status ?: '0';
		$default_post_comment_status = $this->ai_post_default_comment_status ?: 'closed';
		if(!isset($post_category) || $post_category == '') { $post_category = $default_post_category; }
		if(!isset($post_comment_status) || $post_comment_status == '') { $post_comment_status = $default_post_comment_status; }
		
		// Create new post
        $post_data = array(
            'post_title'     => $post_title,
            'post_content'   => $post_content,
            'post_status'    => $default_post_status,
            'post_author'    => get_current_user_id(),
            'post_type'      => 'post',
			'post_excerpt'   => '',
			'comment_status' => $post_comment_status,
			'post_category'  => array( $post_category ),
			'tags_input'     => $seo_terms,
        );

        $post_id = wp_insert_post($post_data);
		wp_set_post_tags($post_id, $seo_terms, true);
        return $post_id;
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <div style="width:100%;display: flex;align-items: center;">
				<div style="width:50%;flex-grow: 1;"><h1><?php echo $this->plugin_name; ?></h1></div>
				<div style="width:50%;flex-grow: 1;"><p style="font-style:italic;text-align: right;">Version <?php echo $this->version; ?></p></div>
			</div>
            <form method="post" action="options.php">
                <?php settings_fields('ai_blog_generator_settings_group'); ?>
                <?php do_settings_sections('ai_blog_generator_settings_group'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('ai_blog_generator_settings_group', 'ai_blog_generator_api_key');
		register_setting('ai_blog_generator_settings_group', 'ai_default_post_length');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_status');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_category');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_comment_status');
		
		add_settings_section(
			'post_generator_main_section',
			'Post Generator Settings',
			array($this, 'post_generator_main_section_callback'),
			'ai_blog_generator_settings_group'
		);

		// Add existing options fields
		add_settings_field(
			'openai_api_key',
			'OpenAI API Key',
			array($this, 'openai_api_key_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_default_post_length',
			'Default Post Length',
			array($this, 'ai_default_post_length_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_default_status',
			'Default Post Status',
			array($this, 'ai_post_default_status_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_default_category',
			'Default Post Status',
			array($this, 'ai_post_default_category_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_default_comment_status',
			'Default Post Status',
			array($this, 'ai_post_default_comment_status_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
    }
	
	public function post_generator_main_section_callback() {
		echo '<p>This section contains settings for the ' . $this->plugin_name . '.</p>';
	}
	
	// Callback functions for the new fields
	public function openai_api_key_callback() {
		?>
		<input type="text" id="ai-api-key" name="ai_blog_generator_api_key" value="<?php echo esc_attr($this->openai_api_key); ?>" />
		<p style="font-style:italic;">(required: get yours <a href="https://platform.openai.com/account/api-keys" target="blank">here</a>)</p>
		<?php
	}
	
	public function ai_default_post_length_callback() {
		?>
		<input type="text" id="default_post_length" name="ai_default_post_length" value="<?php echo esc_attr($this->ai_default_post_length); ?>" />
		<p style="font-style:italic;">(approximate number of words)</p>
		<?php
	}
	
	public function ai_post_default_status_callback() {
		?>
		<select id="ai_post_default_status" name="ai_post_default_status">
			<option value="">Choose</option>
			<option value="draft"<?php if($this->ai_post_default_status == "draft") { echo " selected='selected'"; } ?>>Draft</option>
			<option value="publish"<?php if($this->ai_post_default_status == "publish") { echo " selected='selected'"; } ?>>Publish</option>
		</select>
		<?php
	}
	
	public function ai_post_default_category_callback() {
		$categories = get_categories(array(
			'taxonomy' => 'category',
			'hide_empty' => false
		));

		if (!empty($categories)) {		
		?>
			<select id="ai_post_default_category" name="ai_post_default_category">
				<?php foreach ($categories as $category) { ?>
					<option value="<?php echo esc_attr($category->term_id); ?>"<?php if($this->ai_post_default_category == esc_attr($category->term_id)) { echo " selected='selected'"; } ?>>
						<?php echo esc_html($category->name); ?>
					</option>
				<?php } ?>
			</select>
		<?php
		}
		else {
			echo 'No categories found. Add your categories <a href="' . site_url( '/wp-admin/edit-tags.php?taxonomy=category' ) . '">here</a>.';
		}
	}
	
	public function ai_post_default_comment_status_callback() {
		?>
		<select id="ai_post_default_comment_status" name="ai_post_default_comment_status">
			<option value="">Choose</option>
			<option value="open"<?php if($this->ai_post_default_comment_status == "open") { echo " selected='selected'"; } ?>>Allow Comments</option>
			<option value="closed"<?php if($this->ai_post_default_comment_status == "closed") { echo " selected='selected'"; } ?>>Disallow Comments</option>
		</select>
		<?php
	}
	
	public function save_post_generator_plugin_options($input) {
		update_option('ai_blog_generator_api_key', sanitize_text_field($input['ai-api-key']));
		update_option('ai_default_post_length', sanitize_text_field($input['default_post_length']));
		update_option('ai_post_default_status', sanitize_text_field($input['ai_post_default_status']));
		update_option('ai_post_default_category', sanitize_text_field($input['ai_post_default_category']));
		update_option('ai_post_default_comment_status', sanitize_text_field($input['ai_post_default_comment_status']));
	}
}

// Initialize the plugin
$ai_blog_post_generator = new ai_blog_post_generator();