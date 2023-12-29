<?php

use App\Http\Controllers\HouseController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('api.auth')->group(
    function () {
        Route::group(
            ['prefix' => 'auth'],
            function () {
                Route::post('/otp', [UserController::class, 'sendOTP']);
                Route::post('/login', [UserController::class, 'login']);
                Route::post('/register', [UserController::class, 'register']);
            }
        );

        Route::group(
            ['prefix' => 'user/update'],
            function () {
                Route::put('/profile', [UserController::class, 'updateUser']);
                Route::put('/location', [UserController::class, 'updateUserLocation']);
                Route::post('/profile-image', [UserController::class, 'updateUserProfileImage']);
                Route::put('/preference', [UserController::class, 'updateUserPreferences']);
            }
        );

        Route::group(
            ['prefix' => 'house'],
            function () {
                Route::get('/all', [HouseController::class, 'getRandomHouses']);
                Route::get('/my', [HouseController::class, 'getHouses']);
                Route::get('/find-by/location', [HouseController::class, 'searchByLocation']);
                Route::post('/view/favorite', [HouseController::class, 'getFavoriteHouses']);

                Route::post('/save/view', [HouseController::class, 'updateHouseViews']);
                Route::post('/add/favorite', [HouseController::class, 'addFavoriteHouse']);
                Route::post('/save/review', [HouseController::class, 'recordReview']);
                Route::post('/find-by/keyword', [HouseController::class, 'searchByKeyword']);

                Route::delete('/delete/favorite/{id}', [HouseController::class, 'deleteFavorite']);
            }
        );

        Route::group(
            ['prefix' => 'manager'],
            function () {
                Route::get('/all', [ManagerController::class, 'getPaginatedManagers']);
                Route::get('/rank', [ManagerController::class, 'getRankedManagers']);

                Route::post('/send/enquiry', [ManagerController::class, 'storeEnquiry']);
                Route::post('/send/complaint', [ManagerController::class, 'storeComplaint']);
            }
        );
    }
);