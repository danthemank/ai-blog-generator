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
	var dalleSearchButton = document.querySelector('#dalle_search_button');

	// Function to update the image URL and preview
	function updateImage(url) {
		defaultImageField.value = url;
		defaultImagePreview.src = url;
	}

	function searchUnsplash(query) {
		var searchTerm = query;
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
						alert('Error when searching for images on Unsplash.');
					}
				});
			}
		});

	}
	

	function generateDalle(query) {
		var searchTerm = query;
		jQuery.ajax({
			url: ajaxurl, 
			type: 'POST',
			data: {
				action: 'key_send' 
			},
			success: function(response) {
				var openApiKey = response.open_api_key;
				var data = {
					prompt: searchTerm,
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
						var imageResults = jQuery('#dalle_image_results');
						imageResults.empty();
						data.data.forEach(function(item) {
							var imageElement = '<img src="'+item.url+'" data-url="' + item.url + '" class="thumbnail-image">';
							imageResults.append(imageElement);
						});
		
						imageResults.find('img').on('click', function() {
							var imageUrl = jQuery(this).data('url');
							jQuery('#ai_post_dalle2_image').val(imageUrl);
						});
					},
					error: function() {
						alert('Error when generating images in DALL-E 2.');
					}
				});
			}
		});
	}

	if (defaultImageField) {
		defaultImageField.addEventListener('change', function() {
			var imageUrl = defaultImageField.value;
				defaultImagePreview.src = imageUrl;
		});
	}

	if (uploadButton) {
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
	}

	if (unsplashSearchButton) {
		unsplashSearchButton.addEventListener('click', function () {
			var query = prompt('Enter your search term for Unsplash images:');
			if (query && query.trim() !== '') {
				searchUnsplash(query);
			}
			});
	}

	if (dalleSearchButton) {
		dalleSearchButton.addEventListener('click', function () {
			var query = prompt('Enter your generation term for DALL-E 2 images:');
			if (query && query.trim() !== '') {
				generateDalle(query);
			}
		});
	}


	
	jQuery("#scheduled-post-topics-table tbody tr.row").each(function(index) {
		var adjustedIndex = index + 1;
		jQuery("#dall-e" + adjustedIndex).change(function() {
			if (jQuery(this).is(':checked')) {
				jQuery('.dalle_input'+adjustedIndex).css('display', 'table-row');
					jQuery('#dalle_search_button'+adjustedIndex).on('click', function () {
						var query = jQuery("#ai_post_dalle"+adjustedIndex).val()
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
												jQuery('#dalle_url'+adjustedIndex).val(imageUrl);
											});
										},
										error: function() {
											alert('Error when generating images in DALL-E 2.');
										}
									});
								}
							});
						}
					});
			} else {
				jQuery('#dalle_input').css('display', 'none');
			}
		});
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
	jQuery('#dall-e').change(function() {
		if (jQuery(this).is(':checked')) {
            jQuery('.dalle_image').css('display', 'table-cell');
            jQuery('.dalle_result').css('display', 'table-cell');
        } else {
            jQuery('.dalle_image').css('display', 'none');
            jQuery('.dalle_result').css('display', 'none');
        }
	});
});