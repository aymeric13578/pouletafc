<?php
if (!function_exists('api_response')) {
    function api_response(int $code, string $message = null, $data = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        return response([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }
}
