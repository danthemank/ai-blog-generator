/* function add_fields() {
	document.getElementById("scheduled-post-topics-table").insertRow(-1).innerHTML = '<tr><td><textarea placeholder="New Blog Topic" th:field="${topicTermsSet.topic}" name="ai_blog_generator_prompt_seo_terms[][prompt]" style="resize: none; width: 100%;"></textarea></td><td><textarea placeholder="SEO Terms" th:field="${topicTermsSet.terms}" name="ai_blog_generator_prompt_seo_terms[][term]" style="resize: none; width: 100%;"></textarea></td></tr>';
} */

document.addEventListener('DOMContentLoaded', function() {
	var updateBtn = document.getElementById('update-prompt-terms-btn');
	var selectInput = document.getElementById('saved-prompts-select');
	var prompts = customData.prompts;
	var terms = customData.terms;

	if(updateBtn) {
		updateBtn.addEventListener('click', function() {
		var selectedIndex = selectInput.value;
		var selectedPrompt = prompts[selectedIndex]['prompt'];
		var selectedTerms = terms[selectedIndex]['term'];

		document.getElementById('ai-prompt').value = selectedPrompt;
		document.getElementById('ai-seo-terms').value = selectedTerms;
		});
	}
	
	var defaultImageField = document.querySelector('#ai_post_default_featured_image');
	var defaultImagePreview = document.querySelector('#default_image_preview');
	var uploadButton = document.querySelector('#upload_image_button');
	var unsplashSearchButton = document.querySelector('#unsplash_search_button');

	// Function to update the image URL and preview
	function updateImage(url) {
		defaultImageField.value = url;
		defaultImagePreview.src = url;
	}

	function searchUnsplash(query) {
		var searchTerm = query;
		var apiKey = unsplashApiKey; 

		jQuery.ajax({
			url: 'https://api.unsplash.com/search/photos',
			method: 'GET',
			headers: {
				'Authorization': 'Client-ID ' + apiKey
			},
			data: {
				query: searchTerm
			},
			success: function(data) {
				var imageResults = jQuery('#image-results');
				imageResults.empty();

				data.results.forEach(function(photo) {
					var imageElement = '<img src="' + photo.urls.thumb + '" alt="' + photo.alt_description + '" data-url="' + photo.urls.regular + '" class="thumbnail-image">';
					imageResults.append(imageElement);
				});

				imageResults.find('img').on('click', function() {
                    var imageUrl = jQuery(this).data('url');
                    jQuery('#ai_post_default_featured_image').val(imageUrl);
                });
			},
			error: function() {
				alert('Error al buscar imágenes en Unsplash.');
			}
		});
	}
	

	defaultImageField.addEventListener('change', function() {
		var imageUrl = defaultImageField.value;
		defaultImagePreview.src = imageUrl;
	});

	uploadButton.addEventListener('click', function() {
		// Check if defaultImageField exists and is empty or not set
		if (!defaultImageField || defaultImageField.value === '') {
			if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
				wp.media.editor.send.attachment = function(props, attachment) {
					updateImage(attachment.url);
				};
			}
			wp.media.editor.open(uploadButton);
			}
			else {
				// If the field is already set, allow the user to update the image
				var imageUrl = defaultImageField.value;
				wp.media.editor.send.attachment = function(props, attachment) {
				updateImage(attachment.url);
			};
			wp.media.editor.open(uploadButton, imageUrl);
		}
		return false;
	});
	
	unsplashSearchButton.addEventListener('click', function () {
		var query = prompt('Enter your search term for Unsplash images:');
		if (query && query.trim() !== '') {
			searchUnsplash(query);
		}
		});
});

jQuery(document).ready(function() {
	jQuery('#royalty').change(function() {
		if (jQuery(this).is(':checked')) {
            jQuery('.default_image').css('display', 'table-cell');
            jQuery('.result_image').css('display', 'table-cell');
        } else {
            jQuery('.default_image').css('display', 'none');
            jQuery('.result_image').css('display', 'none');
        }
	});
});