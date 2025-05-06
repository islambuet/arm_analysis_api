<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;
$url='variety-configuration/selection';
$controllerClass= Controllers\variety_configuration\VarietySelectionController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/{cropId}/{year}/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/{cropId}/{year}/get-items', [$controllerClass, 'getItems']);
//    Route::match(['GET','POST'],$url.'/{cropId}/{year}/get-item/{varietyId}', [$controllerClass, 'getItem']);
    Route::post($url.'/{cropId}/{year}/save-item', [$controllerClass, 'saveItem']);
});

$url='variety-configuration/delivery';
$controllerClass= Controllers\variety_configuration\VarietyDeliveryController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/{trialStationId}/{year}/{seasonId}/get-items', [$controllerClass, 'getItems']);

    Route::post($url.'/{trialStationId}/{year}/{seasonId}/save-pending', [$controllerClass, 'savePending']);
    Route::post($url.'/{trialStationId}/{year}/{seasonId}/save-delivered', [$controllerClass, 'saveDelivered']);
});

$url='variety-configuration/sowing';
$controllerClass= Controllers\variety_configuration\VarietySowingController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/{trialStationId}/{year}/{seasonId}/get-items', [$controllerClass, 'getItems']);
    Route::post($url.'/{trialStationId}/{year}/{seasonId}/save-pending', [$controllerClass, 'savePending']);
    Route::post($url.'/{trialStationId}/{year}/{seasonId}/save-sowed', [$controllerClass, 'saveSowed']);
});
