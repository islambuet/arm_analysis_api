<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;

$url='columns-control';
$controllerClass=Controllers\columns_control\ColumnsControlController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function () use ($url, $controllerClass) {
    Route::post($url . '/save-item', [$controllerClass, 'saveItem']);
});
