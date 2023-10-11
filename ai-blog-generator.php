<?php
/**
 * Plugin Name: AI Blog Post Generator
 * Plugin URI: https://mediatech.group
 * Description: Generate blog posts using artificial intelligence tools.
 * Author: Media & Technology Group, LLC
 * Author URI: https://mediatech.group
 * Version: 1.8.4
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: 'https://mediatech.group/plugin-updates/ai-blog-post-generator/
 * Text Domain: ai-blog-post-generator
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;
require_once plugin_dir_path( __FILE__ ).'ajax/ajax.php';
class ai_blog_post_generator {
	private $license_key;
	private $license_isactive;
	private $openai_api_key;
	private $ai_default_post_length;
	private $ai_post_default_status;
	private $ai_post_default_category;
	private $ai_post_default_comment_status;
	private $ai_default_generate_excerpt;
	private $ai_default_excerpt_length;
	private $ai_post_default_language;
	
	private $ai_post_default_featured_image;
	private $unsplash_api_key;
	private $unsplash_secret_key;
	
	private $saved_prompts;
	private $saved_terms;
	private $schedule_frequency;
	private $languages;
	private $plugin_name;
	private $version;
	public $cache_key;
	public $cache_allowed;
	public $plugin_slug;

    public function __construct() {
		$this->plugin_name = 'AI Blog Post Generator';
		$this->version = '1.8.4';
		$this->cache_key = 'ai_blog_post_gen_upd';
		$this->cache_allowed = false;
		$this->plugin_slug = plugin_basename( __DIR__ );
		
		$this->license_key = get_option('ai_blog_generator_license_key');
		$this->license_isactive = get_option('ai_blog_generator_license_isactive');
		
        $this->openai_api_key = get_option('ai_blog_generator_api_key');
		$this->ai_default_post_length = get_option('ai_default_post_length');
		$this->ai_post_default_status = get_option('ai_post_default_status');
		$this->ai_post_default_category = get_option('ai_post_default_category');
		$this->ai_post_default_comment_status = get_option('ai_post_default_comment_status');
		$this->ai_default_generate_excerpt = get_option('ai_default_generate_excerpt');
		$this->ai_default_excerpt_length = get_option('ai_default_excerpt_length');
		$this->ai_post_default_language = get_option('ai_post_default_language');
		
		$this->ai_post_default_featured_image = get_option('ai_post_default_featured_image');
		$this->unsplash_api_key = get_option('ai_post_unsplash_api_key');
		$this->unsplash_secret_key = get_option('ai_post_unsplash_secret_key');
		
		$this->languages = array(
			'en' => 'English',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'it' => 'Italian',
			'pt' => 'Portuguese',
			'ru' => 'Russian',
			'ja' => 'Japanese',
			'zh' => 'Chinese',
			'ar' => 'Arabic',
			// Add more languages as needed
		);
		
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			$prompt_seo_terms = get_option('ai_blog_generator_prompt_seo_terms');
			$this->saved_prompts = array();
			$this->saved_terms = array();
			
			if (!empty($prompt_seo_terms) && is_array($prompt_seo_terms)) {
				foreach ($prompt_seo_terms as $prompt_terms) {
					if (isset($prompt_terms['prompt'])) {
						$this->saved_prompts[] = $prompt_terms;
					}
					else if (isset($prompt_terms['term'])) {
						$this->saved_terms[] = $prompt_terms;
					}
				}
			}
			
			$this->schedule_frequency = get_option('ai_blog_generator_schedule_frequency');
			
			if (!empty($this->schedule_frequency)) {
				add_action('init', array($this, 'schedule_post_generation'));
				add_action('ai_blog_generator_generate_posts', array($this, 'generate_scheduled_post'));
			}
		}

        add_action('admin_menu', array($this, 'add_menu_link'));
        add_action('admin_post_generate_blog_post', array($this, 'handle_generator_form_submission'));
        add_action('admin_init', array($this, 'register_settings'));
		
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_style'));
		
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
    }

	
	public function request() {
		$remote = get_transient( $this->cache_key );

		if( false === $remote || ! $this->cache_allowed ) {
			$remote = wp_remote_get(
				'https://mediatech.group/wp-content/plugin-updates/ai-blog-post-generator/info.json',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);

			if(
				is_wp_error( $remote )
				|| 200 !== wp_remote_retrieve_response_code( $remote )
				|| empty( wp_remote_retrieve_body( $remote ) )
			) {
				return false;
			}

			set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );
		}

		$remote = json_decode( wp_remote_retrieve_body( $remote ) );

		return $remote;
	}
	
	function info( $res, $action, $args ) {
		// print_r( $action );
		// print_r( $args );

		// do nothing if you're not getting plugin information right now
		if( 'plugin_information' !== $action ) {
			return $res;
		}

		// do nothing if it is not our plugin
		if( $this->plugin_slug !== $args->slug ) {
			return $res;
		}

		// get updates
		$remote = $this->request();

		if( ! $remote ) {
			return $res;
		}

		$res = new stdClass();

		$res->name = $remote->name;
		$res->slug = $remote->slug;
		$res->version = $remote->version;
		$res->tested = $remote->tested;
		$res->requires = $remote->requires;
		$res->author = $remote->author;
		$res->author_profile = $remote->author_profile;
		$res->download_link = $remote->download_url;
		$res->trunk = $remote->download_url;
		$res->requires_php = $remote->requires_php;
		$res->last_updated = $remote->last_updated;

		$res->sections = array(
			'description' => $remote->sections->description,
			'installation' => $remote->sections->installation,
			'changelog' => $remote->sections->changelog
		);

		if( ! empty( $remote->banners ) ) {
			$res->banners = array(
				'low' => $remote->banners->low,
				'high' => $remote->banners->high
			);
		}

		return $res;
	}
	
	public function update( $transient ) {
		if ( empty($transient->checked ) ) {
			return $transient;
		}

		$remote = $this->request();

		if(
			$remote
			&& version_compare( $this->version, $remote->version, '<' )
			&& version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' )
			&& version_compare( $remote->requires_php, PHP_VERSION, '<' )
		) {
			$res = new stdClass();
			$res->slug = $this->plugin_slug;
			$res->plugin = plugin_basename( __FILE__ );
			$res->new_version = $remote->version;
			$res->tested = $remote->tested;
			$res->package = $remote->download_url;

			$transient->response[ $res->plugin ] = $res;
		}

		return $transient;
	}

	public function purge( $upgrader, $options ){
		if (
			$this->cache_allowed
			&& 'update' === $options['action']
			&& 'plugin' === $options[ 'type' ]
		) {
			// just clean the cache when new plugin version is installed
			delete_transient( $this->cache_key );
		}
	}
	
	public function check_license() {
		$remote_address = 'https://mediatech.group/wp-json/lmfwc/v2/licenses/validate/' . $this->license_key . '?consumer_key=ck_2f23c57a984551bba08e3b5d8f6e59b6b0cd5484&consumer_secret=cs_86b7379a65ccced73943315d16854f200376dc53';

		$remote = wp_remote_get(
			$remote_address,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json; charset=UTF-8',
				)
			)
		);
		
		if(
			is_wp_error( $remote )
			|| 200 !== wp_remote_retrieve_response_code( $remote )
			|| empty( wp_remote_retrieve_body( $remote ) )
		) {
			$remote_error = json_decode( wp_remote_retrieve_body( $remote ) );
			return $remote_error->message;
		}
		else {
			$remote = json_decode( wp_remote_retrieve_body( $remote ) );
			$timesActivated = $remote->data->timesActivated;
			
			if(isset($timesActivated) && $timesActivated >= 1) {
				return 'active';
			}
			else {
				return "Your license is not activated.";
			}
		}
	}
	
	public function activate_license() {
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			return 'active';
		}
		else {
			$pattern = "/^MTG-[A-E1-5]{5}-[A-E1-5]{5}-AIBPG$/";
			
			if(preg_match($pattern, $this->license_key)) {
				$remote_address = 'https://mediatech.group/wp-json/lmfwc/v2/licenses/activate/' . $this->license_key . '?consumer_key=ck_2f23c57a984551bba08e3b5d8f6e59b6b0cd5484&consumer_secret=cs_86b7379a65ccced73943315d16854f200376dc53';
				$remote = wp_remote_get(
					$remote_address,
					array(
						'timeout' => 10,
						'headers' => array(
							'Accept' => 'application/json',
							'Content-Type' => 'application/json; charset=UTF-8',
						)
					)
				);
		
				if(
					is_wp_error( $remote )
					|| 200 !== wp_remote_retrieve_response_code( $remote )
					|| empty( wp_remote_retrieve_body( $remote ) )
				) {
					$remote_error = json_decode( wp_remote_retrieve_body( $remote ) );
					return $remote_error->message;
				}
				else {
					$remote = json_decode( wp_remote_retrieve_body( $remote ) );
					$timesActivated = $remote->data->timesActivated;
					$timesActivatedMax = $remote->data->timesActivatedMax;
					
					if($timesActivated <= $timesActivatedMax) {
						if (!empty($remote->data->createdAt) && !empty($remote->data->validFor)) {
							$createdAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $remote->data->createdAt, new DateTimeZone('UTC'));
							$validForDays = $remote->data->validFor;
							
							// Calculate the expiration date
							$expiresAt = $createdAt->modify("+{$validForDays} days");
							
							// Compare the expiration date with the current date
							$currentDate = new DateTimeImmutable();
							if ($expiresAt > $currentDate) {
								update_option('ai_blog_generator_license_isactive', 'active');
								return 'active';
							} else {
								return "Your license appears to be expired.";
							}
						} else {
							return "Your license appears to be expired.";
						}
					}
					else {
						return "Your license may may have reached the limit for the number of activations. Times Activated: " . $timesActivated . " | Max Activations: " . $timesActivatedMax;
					}
				}
			}
			else { return false; }
		}
	}
	
	public function deactivate_license() {
		$remote_address = 'https://mediatech.group/wp-json/lmfwc/v2/licenses/deactivate/' . $this->license_key . '?consumer_key=ck_2f23c57a984551bba08e3b5d8f6e59b6b0cd5484&consumer_secret=cs_86b7379a65ccced73943315d16854f200376dc53';

		$remote = wp_remote_get(
			$remote_address,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json; charset=UTF-8',
				)
			)
		);
		
		if(
			is_wp_error( $remote )
			|| 200 !== wp_remote_retrieve_response_code( $remote )
			|| empty( wp_remote_retrieve_body( $remote ) )
		) {
			$remote_error = json_decode( wp_remote_retrieve_body( $remote ) );
			return $remote_error->message;
		}
		else {
			update_option('ai_blog_generator_license_isactive', '');
			return 'deactive';
		}
	}
	
	public function enqueue_admin_scripts() {
		if(isset($this->unsplash_api_key) && !empty($this->unsplash_api_key)) {
			wp_enqueue_script('unsplash-api', 'https://unpkg.com/unsplash-js@7.3.0/dist/unsplash.min.js');
			wp_add_inline_script('unsplash-api', 'const unsplash = new UnsplashJS.default({ accessKey: "'. $this->unsplash_api_key . '" });');
		}
		
		// Enqueue media scripts
		wp_enqueue_media();
		
		wp_enqueue_script(
			'blog-generator-admin-script',
			plugin_dir_url(__FILE__) . 'js/scripts.js',
			array( 'jquery', 'media-editor' ),
			'1.15',
			true
		);
		
		$localized_data = array(
			'prompts' => $this->saved_prompts,
			'terms' => $this->saved_terms,
		);
		wp_localize_script('blog-generator-admin-script', 'customData', $localized_data);
	}
	
	public function enqueue_admin_style() {
		// Enqueue the admin style
		wp_enqueue_style( 'blog-generator-admin-style', plugin_dir_url(__FILE__) . 'css/styles.css', array(), '1.2', 'all' );
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
			echo '<p>There was an issue generating the blog post! Please contact <a href="mailto:support@mediatech.group">support@mediatech.group</a> and share this error message:</p>';
			echo "<p>" . $_GET['error'] . "</p>";
			echo '</div>';
		}
        ?>
        <div class="wrap">
            <div style="width:100%;display: flex;align-items: center;">
				<div style="width:50%;flex-grow: 1;"><h1><?php echo $this->plugin_name; ?></h1></div>
				<div style="width:50%;flex-grow: 1;"><p style="font-style:italic;text-align: right;">Version <?php echo $this->version; ?></p></div>
			</div>
			
			<?php
				// Display the existing prompt and terms pairs
				if(isset($this->license_isactive) && $this->license_isactive == 'active') {
					if (!empty($this->saved_prompts)) {
						echo '<select id="saved-prompts-select" name="saved_prompts">';
						foreach ($this->saved_prompts as $index => $prompt) {
							$term = isset($this->saved_terms[$index]) ? $this->saved_terms[$index] : ''; // Get the corresponding term

							echo '<option value="' . esc_attr($index) . '">';
							echo esc_html($prompt['prompt']);
							echo '</option>';
						}
						echo '</select>';
						echo '<button id="update-prompt-terms-btn">Update Prompt and Terms</button>';
					}
					else {
						echo 'Save blog post topics and seo term pairs on the <a href="/wp-admin/options-general.php?page=ai-blog-generator-settings">Settings Page</a> for use in Automatic post creation or for choosing manually here.';
					}
				}
			?>
			
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
							<input type="text" style="width:100%;" id="ai-seo-terms" name="ai_blog_generator_seo_terms" />
						</td>
                    </tr>
					<tr>
                        <th scope="row"><label for="ai_default_post_length">Post Length</label></th>
                        <td>
							<input type="text" id="ai_default_post_length" name="ai_default_post_length" value="<?php echo esc_attr($this->ai_default_post_length); ?>" />
							<p style="font-style:italic;">(approximate number of words)</p>
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
					<tr>
                        <th scope="row"><label for="ai_default_generate_excerpt">Generate Post Excerpt</label></th>
                        <td>
							<input type="checkbox" id="ai_default_generate_excerpt" name="ai_default_generate_excerpt" value="yes" <?php if(esc_attr($this->ai_default_generate_excerpt) == "yes") { echo 'checked="checked>'; } ?> /> Yes
							<p style="font-style:italic;">(this is a separate API call)</p>
						</td>
                    </tr>
					<tr>
                        <th scope="row"><label for="ai_default_generate_excerpt_length">Post Excerpt Length</label></th>
                        <td>
							<input type="text" id="ai_default_generate_excerpt_length" name="ai_default_excerpt_length" value="<?php if(isset($this->ai_default_excerpt_length) && esc_attr($this->ai_default_excerpt_length) !== '' ) { echo esc_attr($this->ai_default_excerpt_length); } else { echo '55'; } ?>" />
							<p style="font-style:italic;">(recommended: 55 words)</p>
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
					<tr>
                        <th scope="row"><label for="ai_post_default_language">Post Language</label></th>
                        <td>
							<?php
								echo '<select name="ai_post_default_language" id="ai_post_default_language">';
			
								foreach ($this->languages as $code => $name) {
									$selected = ($this->ai_post_default_language === $code) ? 'selected' : '';
									echo '<option value="' . $code . '" ' . $selected . '>' . $name . '</option>';
								}

								echo '</select>';
							?>
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
				$post_length = sanitize_text_field($_POST['ai_default_post_length']);
				$post_category = $_POST['ai_post_default_category'];
				$generate_excerpt = $_POST['ai_default_generate_excerpt'];
				$excerpt_length = $_POST['ai_default_generate_excerpt'];
				$post_comment_status = $_POST['ai_post_default_comment_status'];
				$post_language = $_POST['ai_post_default_language'];

                if (!empty($prompt)) {
                    $this->generate_blog_post($prompt, $seo_terms, $post_length, $post_category, $generate_excerpt, $excerpt_length, $post_comment_status, $post_language);
                }
            }
        }
        exit;
    }
	
	public function generate_blog_post($prompt, $seo_terms, $post_length, $post_category, $generate_excerpt, $excerpt_length, $post_comment_status, $language) {
		$prompt = "Write me a blog post given the following instructions and description: " . sanitize_text_field($prompt) . " Use the following keywords to optimize for search engines: " . $seo_terms . ' Write s complete post using ' . $post_length . ' as the maximum number of words. Write the post in the ' . $this->languages[$language] . ' language.';
		
		if(!isset($post_length) || $post_length == '') {
			$post_length = $this->ai_default_post_length ?: '400';
		}
		
		if(!isset($language) || !array_key_exists($language, $this->languages)) {
			$language = 'en';
		}
		
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
					//Store original result so we can feed it back for generating the excerpt
					$original_result = $generated_content;
					
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
					
					if (strpos($generated_content, $prefix) === 0) {
						$generated_content = substr($generated_content, strlen($prefix));
					}
					
					//$generated_content = str_replace($prefix . $generated_title . "\n", '', $generated_content);

					// Trim the generated text again to remove any leading/trailing whitespaces or newline characters
					$generated_content = trim($generated_content);
					
					// Replace \n with <p> tags
					$generated_content = preg_replace('/(^|\n)(.*?)(\n|$)/s', '$1<p>$2</p>$3', $generated_content);

					// Wrap section titles in <h3> tags
					$generated_content = preg_replace('/<p>([A-Za-z\s:]+)<\/p>/', '<h3>$1</h3>', $generated_content);

					$generated_excerpt = "";
					if($generate_excerpt == 'yes') {
						$generated_excerpt = $this->generate_post_excerpt($original_result, $excerpt_length, $language);
					}

					$new_post = $this->generate_post($generated_title, $generated_content, $seo_terms, $post_category, $post_comment_status, $generated_excerpt);
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
	
	public function generate_post_excerpt($original_result, $excerpt_length, $language) {
		$prompt = "Write me a blog post excerpt (this should only be one paragraph with no title) that summarizes this blog post given its title and content that was already generated: " . $original_result . 'Write the blog post excerpt using no more than' . $excerpt_length . ' as the maximum number of words. Write the post excerpt in the ' . $this->languages[$language] . ' language.';
		
		if(!isset($excerpt_length) || $excerpt_length == '') {
			$excerpt_length = $this->ai_default_excerpt_length ?: '55';
		}
		
		if(!isset($language) || !array_key_exists($language, $this->languages)) {
			$language = 'en';
		}
		
		$system_content = 'You are a blog post generation assistant who focuses on creating search engine optimized content.';

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
					return $result['choices'][0]['message']['content'];
				}
				else {
					return "There was a problem generating the excerpt.";
				}
			}
			else {
				return "There was a problem generating the excerpt.";
			}
		}
		else {
			return "There was a problem generating the excerpt.";
		}
	}

    public function generate_post($post_title, $post_content, $seo_terms, $post_category, $post_comment_status, $generated_excerpt) {
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
			'post_excerpt'   => $generated_excerpt,
			'comment_status' => $post_comment_status,
			'post_category'  => array( $post_category ),
			'tags_input'     => $seo_terms,
        );

        $post_id = wp_insert_post($post_data);
		wp_set_post_tags($post_id, $seo_terms, true);
		
		if (!empty($this->ai_post_default_featured_image)) {
			// Attach the default featured image to the post
			$image_id = attachment_url_to_postid($this->ai_post_default_featured_image);
			if ($image_id) {
				set_post_thumbnail($post_id, $image_id);
			}
		}
        return $post_id;
    }

    public function render_settings_page() {
		if(isset($_GET['activate-license']) && $_GET['activate-license'] == true) {
			
			$license_activation = $this->activate_license();
	
			if($license_activation == "active") {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>License successfully activated!</p>';
				echo '</div>';
			}
			else {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>There was an issue activating your license. Error code: ' . $license_activation . '</p>';
				echo '<p>Please contact <a href="mailto:support@mediatech.group">support@mediatech.group</a>.</a></p>';
				echo '</div>';
			}
		}
		else {
			if(isset($_GET['deactivate-license']) && $_GET['deactivate-license'] == true) {
				$license_deactivation = $this->deactivate_license();
				if($license_deactivation == "deactive") {
					echo '<div class="notice notice-success is-dismissible">';
					echo '<p>License successfully deactivated!</p>';
					echo '</div>';
				}
				else {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>There was an issue deactivating your license. Error code: ' . $license_deactivation . '</p>';
					echo '<p>Please contact <a href="mailto:support@mediatech.group">support@mediatech.group</a>.</a></p>';
					echo '</div>';
				}
			}
			/* else {
				if($this->license_isactive == 'active') {
					if($this->check_license() == 'active') {
						
					}
				}
			} */
		}
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
        register_setting('ai_blog_generator_settings_group', 'ai_blog_generator_license_key');
		register_setting('ai_blog_generator_settings_hidden', 'ai_blog_generator_license_isactive');
		register_setting('ai_blog_generator_settings_group', 'ai_blog_generator_api_key');
		register_setting('ai_blog_generator_settings_group', 'ai_default_post_length');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_status');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_category');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_comment_status');
		register_setting('ai_blog_generator_settings_group', 'ai_default_generate_excerpt');
		register_setting('ai_blog_generator_settings_group', 'ai_default_excerpt_length');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_language');
		register_setting('ai_blog_generator_settings_group', 'ai_post_default_featured_image');
		register_setting('ai_blog_generator_settings_group', 'ai_post_unsplash_api_key');
		register_setting('ai_blog_generator_settings_group', 'ai_post_unsplash_secret_key');
		
		add_settings_section(
			'post_generator_main_section',
			'Post Generator Settings',
			array($this, 'post_generator_main_section_callback'),
			'ai_blog_generator_settings_group'
		);

		// Add existing options fields
		add_settings_field(
			'License_key',
			'License Key',
			array($this, 'ai_blog_generator_license_key_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
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
			'Default Post Category',
			array($this, 'ai_post_default_category_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_default_generate_excerpt',
			'Generate Excerpt by Default',
			array($this, 'ai_default_generate_excerpt_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_default_excerpt_length',
			'Default Post Excerpt Length',
			array($this, 'ai_default_excerpt_length_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_default_comment_status',
			'Default Post Comment Status',
			array($this, 'ai_post_default_comment_status_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_default_language',
			'Default Post Language',
			array($this, 'ai_post_default_language_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_default_featured_image',
			'Default Featured Image',
			array($this, 'ai_post_default_featured_image_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_unsplash_api_key',
			'Unsplash API Key',
			array($this, 'ai_post_unsplash_api_key_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		add_settings_field(
			'ai_post_unsplash_secret_key',
			'Unsplash Secret Key',
			array($this, 'ai_post_unsplash_secret_key_callback'),
			'ai_blog_generator_settings_group',
			'post_generator_main_section'
		);
		
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			register_setting('ai_blog_generator_settings_group', 'ai_blog_generator_prompt_seo_terms');
			register_setting('ai_blog_generator_settings_group', 'ai_blog_generator_schedule_frequency');

			add_settings_field(
				'ai_blog_generator_prompt_seo_terms',
				'Topics and Terms',
				array($this, 'ai_blog_generator_prompt_seo_terms_callback'),
				'ai_blog_generator_settings_group',
				'post_generator_main_section'
			);

			add_settings_field(
				'ai_schedule_frequency',
				'Schedule Frequency',
				array($this, 'ai_schedule_frequency_callback'),
				'ai_blog_generator_settings_group',
				'post_generator_main_section'
			);
		}
    }
	
	public function post_generator_main_section_callback() {
		echo '<p>This section contains settings for the ' . $this->plugin_name . '.</p>';
	}
	
	// Callback functions for the new fields
	public function ai_blog_generator_license_key_callback() {

		if ( empty( $this->license_key )) {
					?>
			<form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=ai-blog-generator-settings&activate-license=true')); ?>" id="license-form">
				<input type="text" id="ai_blog_generator_license_key" name="ai_blog_generator_license_key"  />
				<?php 
					echo '<input type="submit" name="activate_license" value="Activate License">';
					echo '<p style="font-style:italic;">(this plugin is FREE, but there are lots of other amazing features in the premium version <a href="https://mediatech.group/product/ai-blog-post-generator-premium-licensing/" target="blank">here</a>)</p>';
				?>
			<form>
		<?php
		} else {
			?>
			<input type="text" id="ai_blog_generator_license_key" name="ai_blog_generator_license_key" value="<?php echo esc_attr($this->license_key); ?>" />
			<?php
			$license_isactive = get_option('ai_blog_generator_license_isactive');
			if(isset($license_isactive) && $license_isactive == 'active')
			{
				echo '<span style="color:#007f00;">Valid License</span>';
				echo ' | <a href="' . esc_url(admin_url( 'options-general.php?page=ai-blog-generator-settings&deactivate-license=true' ) ). '">Deactivate</a>';
			}
			else {
				echo '<a href="' . esc_url(admin_url( 'options-general.php?page=ai-blog-generator-settings&activate-license=true' )). '">Now activates the license</a>';
				echo '<p style="font-style:italic;">(this plugin is FREE, but there are lots of other amazing features in the premium version <a href="https://mediatech.group/product/ai-blog-post-generator-premium-licensing/" target="blank">here</a>)</p>';
			}
		}
	}
	
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
	
	public function ai_default_generate_excerpt_callback() {
		?>
		<input type="checkbox" id="default_generate_excerpt" name="ai_default_generate_excerpt" value="yes" <?php if(esc_attr($this->ai_default_generate_excerpt) == "yes") { echo 'checked="checked>'; } ?> /> Yes
		<p style="font-style:italic;">(this is a separate API call)</p>
		<?php
	}
	
	public function ai_default_excerpt_length_callback() {
		?>
		<input type="text" id="default_excerpt_length" name="ai_default_excerpt_length" value="<?php if(isset($this->ai_default_excerpt_length) && esc_attr($this->ai_default_excerpt_length) !== '' ) { echo esc_attr($this->ai_default_excerpt_length); } else { echo '55'; } ?>" />
		<p style="font-style:italic;">(recommended: 55 words)</p>
		<?php
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
	
	public function ai_post_default_language_callback() {
		echo '<select name="ai_post_default_language" id="ai_post_default_language">';
    
		foreach ($this->languages as $code => $name) {
			$selected = ($this->ai_post_default_language === $code) ? 'selected' : '';
			echo '<option value="' . $code . '" ' . $selected . '>' . $name . '</option>';
		}

		echo '</select>';
	}
	
	public function ai_blog_generator_prompt_seo_terms_callback() {
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			?>
			<table class="widefat striped" id="scheduled-post-topics-table">
			<thead>
				<tr>
					<th style="padding-left:15px;">Blog Topics</th>
					<th style="padding-left:15px;">SEO Terms</th>
				</tr>
			</thead>
			<tbody>
				<?php
					// Display the existing prompt and terms pairs

					if (!empty($this->saved_prompts)) {
						foreach ($this->saved_prompts as $index => $prompt) {
							$term = isset($this->saved_terms[$index]) ? $this->saved_terms[$index] : ''; // Get the corresponding term

							echo '<tr>';
							// Display the textarea for prompt
								echo '<td><textarea placeholder="New Blog Prompt" name="ai_blog_generator_prompt_seo_terms[][prompt]" id="prompt_1" style="resize: none; width: 100%;">' . esc_textarea($prompt['prompt']) . '</textarea></td>';
								// Display the textarea for terms
								echo '<td><textarea placeholder="SEO Terms" name="ai_blog_generator_prompt_seo_terms[][term]" id="term_1" style="resize: none; width: 100%;">' . esc_textarea($term['term']) . '</textarea></td>';
							echo '</tr>';
						}
					} else {
						// Display the textarea for prompt
						echo '<tr class="row"><td><textarea placeholder="New Blog Prompt" name="ai_blog_generator_prompt_seo_terms[][prompt]" id="prompt_1" style="resize: none; width: 100%;"></textarea></td>';

						// Display the textarea for terms
						echo '<td><textarea placeholder="SEO Terms" name="ai_blog_generator_prompt_seo_terms[][term]" id="term_1" style="resize: none; width: 100%;"></textarea></td></tr>';
					}
				?>
			<tr class ="rowButton">
				<td>
					<div class="tooltip">
						<button id="blog_button1" class="blog_button1" name="blog_button1" type="button">Suggest Based on Blog</button>
						<div class="tooltip-text">
							<?php
							echo "Generate a blog post topic + relevant SEO terms based on existing posts within your website's blog.";
							?>
						</div>
					</div>
					<div class="tooltip">
							<button id="store_button1" class="store_button1" name="store_button1" type="button">Suggest Based on Store</button>
							<div class="tooltip-text">
								<?php
								echo "Generate a blog post topic + relevant SEO terms based on existing products in your woocommerce store.";
								?>
							</div>
					</div>
				</td>
				<td>
					<div class="tooltip">
						<button id="seo_button1" class="seo_button1" name="seo_button1" type="button">Suggest SEO Terms Based on Current Post Title</button>
						<div class="tooltip-text">
							<?php
							echo "Generate a list of SEO terms based on the Blog Post Topic.";
							?>
						</div>
					</div>
				</td>
			</tr>
			</tbody>
		</table>
		<input style="margin-top: 15px;" type="button" id="more_fields" value="Add More" class="btn btn-info" />
		<?php
		}
	}
	
	public function ai_schedule_frequency_callback() {
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
		?>
		<select id="ai-schedule-frequency" name="ai_blog_generator_schedule_frequency">
			<option value="">-- Select Frequency --</option>
			<option value="daily" <?php selected($this->schedule_frequency, 'daily'); ?>>Daily</option>
			<option value="weekly-beginning" <?php selected($this->schedule_frequency, 'weekly-beginning'); ?>>Weekly (Beginning of Week)</option>
			<option value="weekly-end" <?php selected($this->schedule_frequency, 'weekly-end'); ?>>Weekly (End of Week)</option>
			<option value="biweekly" <?php selected($this->schedule_frequency, 'biweekly'); ?>>Biweekly</option>
			<option value="monthly" <?php selected($this->schedule_frequency, 'monthly'); ?>>Monthly</option>
		</select>
		<?php
		}
	}
	
	public function ai_post_default_featured_image_callback() {
		?>
		<div id='default_image_table'>
			<div>
				<label for="ai_post_default_featured_image">
				<?php _e('Default Featured Image', 'textdomain'); ?>
				</label>
				<br />
				<input type="text" id="ai_post_default_featured_image" name="ai_post_default_featured_image" value="<?php echo esc_attr($this->ai_post_default_featured_image); ?>" size="50" />
				<input type="button" class="button" id="upload_image_button" value="<?php _e('Upload Image', 'textdomain'); ?>" />
				<?php
					if(isset($this->unsplash_api_key) && esc_attr($this->unsplash_api_key) !== '') {
						echo '<input type="button" class="button" id="unsplash_search_button" value="' . _e('Search Unsplash', 'textdomain') . '" />';
					}
				?>
				<p class="description">
					<?php _e('Select or upload the default featured image for new posts.', 'textdomain'); ?>
				</p>
			</div>
			<div>
				<img id="default_image_preview" src="<?php echo $this->ai_post_default_featured_image; ?>" />
			</div>
		</div>
		<?php
	}
	
	public function ai_post_unsplash_api_key_callback() {
		?>
		<input type="text" id="ai_post_unsplash_api_key" name="ai_post_unsplash_api_key" value="<?php if(isset($this->unsplash_api_key) && esc_attr($this->unsplash_api_key) !== '') { echo esc_attr($this->unsplash_api_key); } else { echo ''; } ?>" />
		<p style="font-style:italic;">(you will need to create an account and keys at Unsplash)</p>
		<?php
	}
	
	public function ai_post_unsplash_secret_key_callback() {
		?>
		<input type="text" id="ai_post_unsplash_secret_key" name="ai_post_unsplash_secret_key" value="<?php if(isset($this->unsplash_secret_key) && esc_attr($this->unsplash_secret_key) !== '') { echo esc_attr($this->unsplash_secret_key); } else { echo ''; } ?>" />
		<?php
	}
	
	public function save_post_generator_plugin_options($input) {
		update_option('ai_blog_generator_license_key', sanitize_text_field($input['ai_blog_generator_license_key']));
		update_option('ai_blog_generator_api_key', sanitize_text_field($input['ai-api-key']));
		update_option('ai_default_post_length', sanitize_text_field($input['default_post_length']));
		update_option('ai_post_default_status', sanitize_text_field($input['ai_post_default_status']));
		update_option('ai_post_default_category', sanitize_text_field($input['ai_post_default_category']));
		update_option('ai_default_generate_excerpt', sanitize_text_field($input['ai_default_generate_excerpt']));
		update_option('ai_default_excerpt_length', sanitize_text_field($input['ai_default_excerpt_length']));
		update_option('ai_post_default_comment_status', sanitize_text_field($input['ai_post_default_comment_status']));
		update_option('ai_post_default_language', sanitize_text_field($input['ai_post_default_language']));
		update_option('ai_post_default_featured_image', sanitize_text_field($input['ai_post_default_featured_image']));
		update_option('ai_post_unsplash_api_key', sanitize_text_field($input['ai_post_unsplash_api_key']));
		update_option('ai_post_unsplash_secret_key', sanitize_text_field($input['ai_post_unsplash_secret_key']));
		
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			$submitted_data = $input['ai_blog_generator_prompt_seo_terms'];
			$formatted_data = array('prompts' => array(), 'terms' => array());

			foreach ($submitted_data as $entry) {
				if(isset($entry['prompt']) && $entry['prompt'] !== "") {
					$formatted_data['prompts'][] = sanitize_text_field($entry['prompt']);
					$formatted_data['terms'][] = sanitize_text_field($entry['terms']);
				}
			}

			update_option('ai_blog_generator_prompt_seo_terms', serialize($formatted_data));
			update_option('ai_blog_generator_schedule_frequency', $input['ai-schedule-frequency']);
		}
	}
	
	public function schedule_post_generation() {
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			$frequency = $this->schedule_frequency;
			if ($frequency === 'daily') {
				$time = strtotime('tomorrow');
				wp_schedule_single_event($time, 'ai_blog_generator_generate_posts');
			} elseif ($frequency === 'weekly-beginning') {
				$time = strtotime('next week');
				wp_schedule_single_event($time, 'ai_blog_generator_generate_posts');
			} elseif ($frequency === 'weekly-end') {
				$time = strtotime('next week +6 days');
				wp_schedule_single_event($time, 'ai_blog_generator_generate_posts');
			} elseif ($frequency === 'biweekly') {
				$time = strtotime('+2 weeks');
				wp_schedule_single_event($time, 'ai_blog_generator_generate_posts');
			} elseif ($frequency === 'monthly') {
				$time = strtotime('next month');
				wp_schedule_single_event($time, 'ai_blog_generator_generate_posts');
			}
		}
    }
	
	public function generate_scheduled_post() {	
		$prompt_seo_terms = [];
		if(isset($this->license_isactive) && $this->license_isactive == 'active') {
			if (!empty($this->saved_prompts)) {
				//We need pick one, maybe a setting is needed to determine how best to do this, but for now we will just take the first one that is available
				$prompt = $this->saved_prompts[0];
				$seo_terms = $this->saved_terms[0];
				$post_length = $this->ai_default_post_length;
				$post_category = $this->ai_post_default_category;
				$generate_excerpt = $this->ai_default_generate_excerpt;
				$excerpt_length = $this->ai_default_excerpt_length;
				$post_comment_status = $this->ai_post_default_comment_status;
				$post_language = $this->ai_post_default_language;

				if (!empty($prompt)) {
					$this->generate_blog_post($prompt, $seo_terms, $post_length, $post_category, $generate_excerpt, $excerpt_length, $post_comment_status, $post_language);
				}

				// Update the stored prompts after generating posts
				unset($prompt_seo_terms[$prompt]);
				update_option('ai_blog_generator_prompt_seo_terms', $prompt_seo_terms);
			}
		}
    }
	
	public function generate_seo_terms($blog_topic) {
		/* 
		//Make setting for default number of generated keywords/phrases
		$prompt = "Give me a of SEO keywords and keyphrases (up to 5) for a blog post about " . $blog_topic . ". The list should be in a comma-separated format and not in a numbered list.";
		
		$system_content = 'You are a search engine optimization expert preparing keywords and keyphrases that will be implemented within a new blog post which is written in a following step.';

        */
	}
}

// Initialize the plugin
$ai_blog_post_generator = new ai_blog_post_generator();