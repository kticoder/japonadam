<?php

function envato_modify_http_request_args($args, $url) {
    $theme_directory = get_template_directory_uri();
    
    // Eğer istek tema dizininden geliyorsa veya belirli tema kaynaklarına yönelikse
    if (strpos($url, $theme_directory) === 0 || strpos($url, 'muffingroup') !== false) {
		$args_string = json_encode($args);

		// Replace all URLs (both http and https) with http://bethemetest.local within $args
		$args_string = preg_replace('/(https?:\/\/[^\s"\']+)/', 'http://thetest.local', $args_string);

		// Update user-agent
		$args_string = preg_replace('/"user-agent":"[^"]*"/', '"user-agent":"WordPress/5.8.3; https://thetest.local"', $args_string);

		// Convert back to an array
		$args = json_decode($args_string, true);
    }

    if (strpos($url, $theme_directory) === 0 || strpos($url, 'the7') !== false) {
		$args['user-agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . "http://ozlemcolak.local";
    }
    return $args;
}
add_filter('http_request_args', 'envato_modify_http_request_args', 10, 2);


