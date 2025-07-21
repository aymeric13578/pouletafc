<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Http\Request;

class ParametersController extends Controller
{
    /**
     * Get the parameter with 'Success' status.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSuccessParameter()
    {
        $parameter = Parameter::where('status', 'Success')->first();

        if ($parameter) {
            return response()->json([
                'success' => true,
                'data' => $parameter,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No parameter with Success status found.',
        ], 404);
    }
}