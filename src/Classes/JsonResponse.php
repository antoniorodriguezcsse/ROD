<?php
namespace Fallen\SecondLife\Classes;

class JsonResponse {
    private int $status;
    private string $message;
    private $args;

    public function __construct(int $status, string $message, $args = null) {
        $this->status = $status;
        $this->message = $message;
        $this->args = $args;
    }

    public function getStatus() {
        return $this->status;
    }
    
    public function getMessage() {
        return $this->message;
    }

    public function getData() {
        return $this->args;
    }

    public function sendHeaders() {
        header('Content-Type: application/json; charset=utf-8');
    }

    public function __toString() {
        $this->sendHeaders(); // Set the appropriate headers

        try {
            $json = json_encode([
                'status' => $this->status,
                'message' => $this->message,
                'extra' => $this->args
            ], JSON_UNESCAPED_UNICODE); // Use JSON_UNESCAPED_UNICODE flag

            if ($json === false) {
                throw new \Exception('JSON encoding failed');
            }

            return $json;
        } catch (\Exception $e) {
            return json_encode([
                'status' => 500,
                'message' => 'Internal Server Error',
                'extra' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}