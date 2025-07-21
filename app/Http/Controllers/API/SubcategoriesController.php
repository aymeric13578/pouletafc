<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\subcategory;
use response;

class SubcategoriesController extends Controller
{
    public function getAllSubCategory()
    {
        $subcategory = SubCategory::all();
        return response()->json([
            "response"=>200,
            "data"=>$subcategory->where('status','Success'),

        ]);
    }
}

