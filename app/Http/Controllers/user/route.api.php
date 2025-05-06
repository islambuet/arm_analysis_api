<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers as Controllers;

$url='user';
$controllerClass=Controllers\user\UserController::class;

Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
Route::post($url.'/login', [$controllerClass, 'login']);


Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/logout', [$controllerClass, 'logout']);

    Route::post($url.'/profile-picture', [$controllerClass, 'profilePicture']);
    Route::post($url.'/change-password', [$controllerClass, 'ChangePassword']);
});

