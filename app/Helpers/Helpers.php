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

if (!function_exists('product_image_url')) {
    function product_image_url(?string $path): string
    {
        if (empty($path)) {
            return asset('images/logo.png');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }
}
