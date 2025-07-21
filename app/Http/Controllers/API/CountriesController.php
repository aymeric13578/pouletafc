<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\country;
use response;

class CountriesController extends Controller
{

    public function getAllCountry()
    {
        $country = Country::all();
        return response()->json([
            "response"=>200,
            "data"=>$country,

        ]);
    }
}

