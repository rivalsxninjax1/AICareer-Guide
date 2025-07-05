<?php



require_once 'config.php';

// Set your API key and endpoint URL:



// Enable error reporting for debugging (adjust in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && strpos($contentType, "application/json") !== false) {
    header("Content-Type: application/json");
    if (ob_get_length()) { ob_clean(); }
    
    $input = file_get_contents("php://input");
    $payload = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["response" => "Invalid JSON payload: " . json_last_error_msg()]);
        exit;
    }
    
    $data = json_encode($payload);
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $error_msg = curl_error($ch);
    }
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Grow API raw response: " . $response);
    
    if (isset($error_msg)) {
        echo json_encode(["response" => "cURL error: " . $error_msg]);
        exit;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["response" => "Error decoding API response: " . json_last_error_msg()]);
        exit;
    }
    
    if ($http_status !== 200 || !isset($result["choices"][0]["message"]["content"])) {
        echo json_encode(["response" => "API Error: HTTP $http_status. Response: " . $response]);
        exit;
    }
    
    echo json_encode(["response" => $result["choices"][0]["message"]["content"]]);
    exit;
}

/**
 * Function for form-based calls.
 * This function sends a request to the Grow API using the provided credentials
 * and returns the AI response.
 */
function getCareerGuidance($linkedin, $github, $career_path) {
    global $apiUrl, $apiKey;
    $payload = [
        "model" => "llama3-70b-8192",
        "messages" => [
            [
                "role" => "system",
                "content" => "Provide comprehensive career guidance based on the GitHub and LinkedIn profiles for the selected career path, including detailed improvements, project suggestions (with links), skill gaps(with links), interview prep(with link), and learning resources (with proper links). Also, provide a study plan to fill the skill gap in the following format:\n\nStudy Plan:\n  Week 1:\n    - Task description A\n    - Task description B\n  Week 2:\n    - Task description C\n  ...\n  Week Last:\n    - Final task description\n\nwith proper time management."
            ],
            [
                "role" => "user",
                "content" => "GitHub Link: $github\nLinkedIn Link: $linkedin\nJob Selected: $career_path"
            ]
        ],
        "temperature" => 1,
        "max_tokens" => 1024
    ];
    
    $data = json_encode($payload);
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $error_msg = curl_error($ch);
    }
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (isset($error_msg)) {
        return "cURL error: " . $error_msg;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Error decoding API response: " . json_last_error_msg();
    }
    
    if ($http_status !== 200 || !isset($result["choices"][0]["message"]["content"])) {
        return "Unable to retrieve career guidance. HTTP Status: $http_status. Response: " . $response;
    }
    
    return $result["choices"][0]["message"]["content"];
}
?>
