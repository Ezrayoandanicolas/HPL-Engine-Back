<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BaseApiController extends Controller
{
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function formatBalance($balance): string
    {
        if ($balance == 0) {
            return '0,00';
        }

        $formattedBalance = number_format($balance, 2, ',', '.');
        if ($balance < 1000 && $balance > 0) {
            return '0' . '.' . substr_replace($formattedBalance, '', -4);
        }

        return substr_replace($formattedBalance, '', -4);
    }

    protected function getAuthenticatedUser()
    {
        if (request()->bearerToken()) {
            return Auth::guard('sanctum')->user();
        }
        return Auth::user();
    }
}
