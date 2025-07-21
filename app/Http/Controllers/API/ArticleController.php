<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;

class ArticleController extends Controller
{
    public function getArticles()
    {
        $articles = Article::where('status', "Success")->orderBy('id','desc')->get();
        if($articles) return response()->json(['response' => 200, 'data'=> $articles ]);
        else return response()->json(['response' => 404]);
    }
}
