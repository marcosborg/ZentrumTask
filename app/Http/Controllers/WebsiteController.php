<?php

namespace App\Http\Controllers;

use App\Models\Hero;

class WebsiteController extends Controller
{
    public function index()
    {
        return view('website.index');
    }
}
