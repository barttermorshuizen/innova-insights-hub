<?php


function getChatGPTResponse($message) {


	$innova_insights_hub_plugin_options_options = get_option( 'innova_insights_hub_plugin_options_option_name' ); // Array of All Options
	$api_key = $innova_insights_hub_plugin_options_options['api_key_0']; // API Key
	$api_url = $innova_insights_hub_plugin_options_options['api_endpoint_url_1']; // API endpoint URL

	//$api_url = 'https://api.openai.com/v1/chat/completions';
	//$api_key = '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';

	// Create an array with the message
	$data = [
		'messages' => [
			[
				'role' => 'system',
				'content' => 'You are a helpful assistant.'
			],
			[
				'role' => 'user',
				'content' => $message
			]
		],
		'model' => 'gpt-4-0613',
		'temperature' => 0.2
	];

	// Encode the data as JSON
	$payload = json_encode($data);

	// Set up cURL options
	$ch = curl_init($api_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Authorization: Bearer ' . $api_key
	]);

	// Execute the cURL request
	$response = curl_exec($ch);

	// Check for cURL errors
	if (curl_errno($ch)) {
		echo 'Error: ' . curl_error($ch);
	}

	// Close the cURL session
	curl_close($ch);

	// Decode and return the API response
	return json_decode($response, true);
}

function getTermsList($taxonomy){
	$terms=get_terms($taxonomy,array( "hide_empty" => 0 ));
	return wp_list_pluck($terms, 'slug');
}


// question is 'Can you suggest the most important topics to this post? A topic is one of this list exclusively:'
// termlist is a comma separated list of term names like 'sustainability, plant-based'
// example response is an example like '{"topics": ["sustainability", "plant-based"]}'

function getTermsByChatGPT($post,$question,$termlist,$example_response){
	$message = $question;
	$message .= $termlist;
	$message .= '. /n Give me the response as json and nothing else. An example response: ';
	$message .= $example_response;
	$message .= '/n Article is: ';
	$message .= $post->post_content;

	$max_retries = 5; // Define a max number of retries
	$retry_count = 0;
	$delay = 2; // Start with a delay of 2 seconds

	try {
		do {
			$response = getChatGPTResponse($message);

			// If rate limit error
			if (isset($response['error']['code']) && $response['error']['code'] == 'rate_limit_exceeded') {
				// Wait for the delay time
				sleep($delay);

				// Double the delay time for the next iteration
				$delay *= 2;

				$retry_count++;
			} else {
				break; // Break out of the loop if we get a successful response or a different error
			}
		} while ($retry_count < $max_retries);

		// Check if the response structure is as expected
		if (isset($response['choices'][0]['message']['content'])) {
			return $response['choices'][0]['message']['content'];
		} else {
			// Handle unexpected response format
			throw new Exception('Unexpected response format from the API.');
		}
	} catch (Exception $e) {
		// Handle API request errors or unexpected response
		error_log('Error while fetching data from ChatGPT API: ' . $e->getMessage());
		$retVal = json_encode(new stdClass());
		return $retVal; // Return empty json
	}

}

function getTopicsTermsByChatGPT($post)  {
	$q = 'Can you suggest the most important topics for this article? A topic is one of this list exclusively: ';
	$topics = implode(', ', getTermsList('topics'));
	$er = '{"topics": ["sustainability", "plant-based"]}';
	return getTermsByChatGPT($post, $q, $topics, $er);
}

function getFBCategoryTermsByChatGPT($post)  {
	$q = 'Can you suggest the most important Food & Beverage (F&B) categories for this article? A F&B category is defined as one of this following list and nothing else: ';
	$topics = implode(', ', getTermsList('f-b-category'));
	$er = '{"f-b-category": ["dairy","beverages"]}';
	return getTermsByChatGPT($post, $q, $topics, $er);
}

function getInsights360TermsByChatGPT($post)  {
	$q = 'Can you identify what insights perspective this article is about? An insights perspective is defined as one of this following list and nothing else: ';
	$topics = implode(', ', getTermsList('insights360'));
	$er = '{"insights360": ["consumer","packaging","flavors"]}';
	return getTermsByChatGPT($post, $q, $topics, $er);
}


// function that adds meta data automatically
function post_auto_term( $post ) {

		$validPostTypes = array(
			'trends',
			'webinars',
			'press-releases',
			'reports'
		);
		if ( in_array( $post->post_type, $validPostTypes ) ) {
			setPostTopicsTerms( $post);
			setPostFBCategoryTerms($post );
			setPostInsights360Terms($post);
			return true;
		} else {
			// returning false if there's nothing to autoterm
			return false;
		}

}

function setPostTerms( $post_id,$taxonomy,$jsonData){
	// Decode the JSON string into an array.
	$data = json_decode($jsonData, true);

	// Check if the taxonomy data exists and is an array.
	if (isset($data[$taxonomy]) && is_array($data[$taxonomy])) {
		$topicsString = implode(', ', $data[$taxonomy]);

		$term_taxonomy_ids = wp_set_post_terms( $post_id, $topicsString, $taxonomy , false);
		if ( is_wp_error( $term_taxonomy_ids ) ) {
			// There was an error somewhere and the terms couldn't be set.
			error_log($term_taxonomy_ids->get_error_message());
			return false;
		} else {
			// Success! The post's categories were set.
			return true;
		}
	}
	else {
		error_log('taxonomy data is not present or taxonomy data is not an array');
		return false;
	}
}

function setPostTopicsTerms( $post){
	$jsonData = getTopicsTermsByChatGPT($post);
	setPostTerms($post->ID,'topics',$jsonData);
}

function setPostFBCategoryTerms( $post){
	$jsonData = getFBCategoryTermsByChatGPT($post);
	setPostTerms($post->ID,'f-b-category',$jsonData);
}

function setPostInsights360Terms( $post){
	$jsonData = getInsights360TermsByChatGPT($post);
	setPostTerms($post->ID,'insights360',$jsonData);
}