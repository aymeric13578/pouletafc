<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubCategoriesController extends Controller
{
    public function index()
    {
        $subCategories = SubCategory::with('category')->get();
        return Inertia::render('Admin/Page/SubCategories/ListSubCategories',['subCategories'=>$subCategories]);
    }
}
