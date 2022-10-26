<?php

use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\main\CampaignController;
use App\Http\Controllers\main\DomainController;
use App\Http\Controllers\main\KeywordController;
use App\Http\Controllers\main\SourceController;
use App\Http\Controllers\permission\PermissionController;
use App\Http\Controllers\permission\RoleController;
use App\Http\Controllers\user\OrganizationController;
use App\Http\Controllers\user\OrganizationGroupController;
use App\Http\Controllers\user\OrganizationTypeController;
use App\Http\Controllers\user\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::group(['middleware' => ['api']], function () {
    // group only auth
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    Route::group(['middleware' => ['api', 'auth:api']], function () {
        Route::group(['prefix' => 'user'], function () {
            Route::get('/info', [UserController::class, 'info']);
            Route::get('/list', [UserController::class, 'data']);
            Route::post('/update', [UserController::class, 'update']);
        });
        // organization-type
        Route::group(['prefix' => 'organization-type'], function () {
            Route::post('/', [OrganizationTypeController::class, 'show']);
            Route::get('/list', [OrganizationTypeController::class, 'data']);
            Route::post('/create', [OrganizationTypeController::class, 'store']);
            Route::put('/update', [OrganizationTypeController::class, 'update']);
            Route::put('/delete', [OrganizationTypeController::class, 'delete']);
        });

        // organization-group
        Route::group(['prefix' => 'organization-type'], function () {
            Route::post('/', [OrganizationGroupController::class, 'show']);
            Route::get('/list', [OrganizationGroupController::class, 'list']);
            Route::post('/create', [OrganizationGroupController::class, 'store']);
            Route::put('/update', [OrganizationGroupController::class, 'update']);
            Route::put('/delete', [OrganizationGroupController::class, 'delete']);
        });

        // organization
        Route::group(['prefix' => 'organization'], function () {
            Route::get('/', [OrganizationController::class, 'show']);
            Route::get('/list', [OrganizationController::class, 'list']);
            Route::post('/create', [OrganizationController::class, 'store']);
            Route::put('/update', [OrganizationController::class, 'update']);
            Route::put('/delete', [OrganizationController::class, 'destroy']);
        });

        Route::group(['prefix' => 'permission'], function () {
            Route::get('/', [PermissionController::class, 'show']);
//            Route::get('/list', [PermissionController::class, 'list']);
            Route::post('/create', [PermissionController::class, 'store']);
            Route::put('/update', [PermissionController::class, 'update']);
            Route::put('/delete', [PermissionController::class, 'destroy']);
        });


        Route::group(['prefix' => 'role'], function () {
            Route::get('/', [RoleController::class, 'show']);
            Route::get('/list', [RoleController::class, 'index']);
            Route::post('/create', [RoleController::class, 'store']);
            Route::put('/update', [RoleController::class, 'update']);
            Route::put('/delete', [RoleController::class, 'destroy']);
        });


        Route::group(['prefix' => 'campaign'], function () {
            Route::get('/', [CampaignController::class, 'show']);
            Route::get('/list', [CampaignController::class, 'index']);
            Route::post('/create', [CampaignController::class, 'store']);
            Route::put('/update', [CampaignController::class, 'update']);
            Route::put('/delete', [CampaignController::class, 'destroy']);
        });


        Route::group(['prefix' => 'domain'], function () {
            Route::get('/', [DomainController::class, 'show']);
            Route::get('/list', [DomainController::class, 'index']);
            Route::post('/create', [DomainController::class, 'store']);
            Route::put('/update', [DomainController::class, 'update']);
            Route::put('/delete', [DomainController::class, 'destroy']);
        });


        Route::group(['prefix' => 'keyword'], function () {
            Route::get('/', [KeywordController::class, 'show']);
            Route::get('/list', [KeywordController::class, 'index']);
            Route::post('/create', [KeywordController::class, 'store']);
            Route::put('/update', [KeywordController::class, 'update']);
            Route::put('/delete', [KeywordController::class, 'destroy']);
        });


        Route::group(['prefix' => 'source'], function () {
            Route::get('/list', [SourceController::class, 'index']);

        });

    });
});








