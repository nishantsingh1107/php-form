<?php
    function api_error(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            "success" => false,
            "message" => $message
        ]);
        exit;
    }
    
    
    function api_success(array $data = [], int $code = 200): void {
        http_response_code($code);
        echo json_encode(array_merge([
            "success" => true
        ], $data));
        exit;
    }
?>