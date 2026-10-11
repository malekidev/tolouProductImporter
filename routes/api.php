<?php

use App\Models\ImportItem;
use App\Services\AiProductGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post("/store",[\App\Http\Controllers\ImportController::class,'store']);
Route::get('/test', function () {
    // اولین آیتمی که پیدا می‌کنه رو تست می‌کنه؛
    // اگه می‌خوای یه رکورد خاص رو تست کنی، find(شماره_آیدی) بذار
    $item = ImportItem::first();

    if (! $item) {
        return response()->json(['error' => 'هیچ ImportItem ای توی دیتابیس نیست'], 404);
    }

    $ai = new AiProductGeneratorService;
    $result = $ai->generate($item);

    return response()->json($result);
});
