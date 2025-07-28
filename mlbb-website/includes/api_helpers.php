<?php
// This file should be included by any script that needs to call the Smile One API.

function generateSign($params, $key) {
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) {
        $str .= $k . '=' . $v . '&';
    }
    $str .= $key;
    return md5(md5($str));
}

function callApi($endpoint, $params) {
    $url = API_BASE_URL . $endpoint;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("cURL Error for $url: " . curl_error($ch));
        curl_close($ch);
        return null; // Indicate failure
    }
    curl_close($ch);
    
    $decoded_response = json_decode($response_body, true);
    
    // If JSON decoding fails, it might be an HTML error page from the provider.
    // Log the raw response for debugging.
    if ($decoded_response === null) {
        error_log("Failed to decode JSON from Smile One API. Endpoint: $endpoint, HTTP Code: $http_code, Response Body: " . $response_body);
        return null; // Indicate failure
    }
    
    return $decoded_response;
}