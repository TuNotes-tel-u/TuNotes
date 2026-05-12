<?php
/**
 * Helper untuk format response JSON yang konsisten di semua endpoint API.
 *
 * Format sukses:
 *   { "success": true,  "message": "...", "data": ..., "meta": ... }
 * Format error:
 *   { "success": false, "message": "...", "error_code": "...", "details": ... }
 *
 * Semua fungsi memanggil exit setelah mengirim response — supaya caller di endpoint
 * tidak perlu return manual setelah panggil helper ini.
 */

if (!function_exists('json_response')) {
    function json_response(array $payload, int $http_code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($http_code);
            header('Content-Type: application/json; charset=utf-8');
            // Cegah cache pada response API (penting untuk endpoint yang return data user).
            header('Cache-Control: no-store');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('json_success')) {
    function json_success(string $message = 'OK', $data = null, $meta = null, int $http_code = 200): void
    {
        $payload = ['success' => true, 'message' => $message];
        if ($data !== null) $payload['data'] = $data;
        if ($meta !== null) $payload['meta'] = $meta;
        json_response($payload, $http_code);
    }
}

if (!function_exists('json_error')) {
    function json_error(string $message, string $error_code = 'ERROR', int $http_code = 400, $details = null): void
    {
        $payload = [
            'success'    => false,
            'message'    => $message,
            'error_code' => $error_code,
        ];
        if ($details !== null) $payload['details'] = $details;
        json_response($payload, $http_code);
    }
}

if (!function_exists('read_json_input')) {
    /**
     * Baca body request sebagai JSON, kembalikan sebagai array asosiatif.
     * Kalau body kosong → return [] (caller tinggal validasi field required).
     * Kalau JSON tidak valid → langsung balas 400 dan stop.
     */
    function read_json_input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            json_error('Body JSON tidak valid: ' . json_last_error_msg(), 'INVALID_JSON', 400);
        }
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('only_methods')) {
    /**
     * Gate awal endpoint: tolak method yang tidak diizinkan.
     * Contoh pakai: only_methods('GET', 'POST');
     */
    function only_methods(string ...$allowed): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, $allowed, true)) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', $allowed));
            }
            json_error("Method $method tidak diizinkan di endpoint ini.", 'METHOD_NOT_ALLOWED', 405);
        }
    }
}
