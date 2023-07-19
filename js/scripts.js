function add_fields() {
	document.getElementById("scheduled-post-topics-table").insertRow(-1).innerHTML = '<tr><td><textarea placeholder="New Blog Topic" th:field="${topicTermsSet.topic}" name="ai_blog_generator_prompt_seo_terms[][prompt]" style="resize: none; width: 100%;"></textarea></td><td><textarea placeholder="SEO Terms" th:field="${topicTermsSet.terms}" name="ai_blog_generator_prompt_seo_terms[][term]" style="resize: none; width: 100%;"></textarea></td></tr>';
}

document.addEventListener('DOMContentLoaded', function() {
  var updateBtn = document.getElementById('update-prompt-terms-btn');
  var select = document.getElementById('saved-prompts-select');
  var prompts = customData.prompts;
  var terms = customData.terms;
  
  console.log(prompts);

  updateBtn.addEventListener('click', function() {
    var selectedIndex = select.value;
    var selectedPrompt = prompts[selectedIndex]['prompt'];
    var selectedTerms = terms[selectedIndex]['term'];

    document.getElementById('ai-prompt').value = selectedPrompt;
    document.getElementById('ai-seo-terms').value = selectedTerms;
  });
});