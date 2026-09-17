<?php

use App\Http\Controllers\Api\SapoDongBoController;
use Illuminate\Support\Facades\Route;

// Dong bo hoa don tu Sapo FnB. Khong qua phien dang nhap, khoa bang ma bi mat.
Route::post('sapo/hoa-don', [SapoDongBoController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('api.sapo.hoa-don');
