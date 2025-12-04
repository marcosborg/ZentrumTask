<?php

use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'index']);
Route::post('/contact', [WebsiteController::class, 'storeContact'])->name('contact.submit');
Route::get('/cms/{page}/{slug?}', [WebsiteController::class, 'showCms'])->name('cms.show');
