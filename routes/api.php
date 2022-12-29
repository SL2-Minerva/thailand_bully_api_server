<?php

use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\main\CampaignController;
use App\Http\Controllers\main\DomainController;
use App\Http\Controllers\main\KeywordController;
use App\Http\Controllers\main\SourceController;
use App\Http\Controllers\permission\PermissionController;
use App\Http\Controllers\permission\RoleController;
use App\Http\Controllers\report\BullyDashboardController;
use App\Http\Controllers\report\DashboardController;
use App\Http\Controllers\user\OrganizationContentController;
use App\Http\Controllers\user\OrganizationController;
use App\Http\Controllers\user\OrganizationGroupController;
use App\Http\Controllers\user\OrganizationTypeController;
use App\Http\Controllers\user\UserController;
use App\Http\Controllers\report\VoiceDashboardController;
use App\Http\Controllers\report\ChannelDashboardController;
use App\Http\Controllers\report\EngagementDashboardController;
use App\Http\Controllers\report\SentimentDashboardController;
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
            Route::get('/list-active', [UserController::class, 'list_active']);
            Route::post('/create', [UserController::class, 'create']);
            Route::put('/update/{user}', [UserController::class, 'update']);
            Route::delete('/delete/{user}', [UserController::class, 'delete']);
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
        Route::group(['prefix' => 'organization-group'], function () {
            Route::post('/', [OrganizationGroupController::class, 'show']);
            Route::get('/list', [OrganizationGroupController::class, 'data']);
            Route::post('/create', [OrganizationGroupController::class, 'store']);
            Route::put('/update', [OrganizationGroupController::class, 'update']);
            Route::put('/delete', [OrganizationGroupController::class, 'delete']);
        });

        // organization
        Route::group(['prefix' => 'organization'], function () {
            Route::get('/', [OrganizationController::class, 'show']);
            Route::get('/list', [OrganizationController::class, 'data']);
            Route::post('/create', [OrganizationController::class, 'store']);
            Route::put('/update', [OrganizationController::class, 'update']);
            Route::put('/delete', [OrganizationController::class, 'destroy']);
        });

        Route::group(['prefix' => 'source'], function () {
            Route::get('/', [SourceController::class, 'show']);
            Route::get('/list', [SourceController::class, 'data']);
            Route::post('/create', [SourceController::class, 'store']);
            Route::put('/update', [SourceController::class, 'update']);
            Route::put('/delete', [SourceController::class, 'destroy']);
        });

        Route::group(['prefix' => 'permission'], function () {
            Route::get('/', [PermissionController::class, 'show']);
//            Route::get('/list', [PermissionController::class, 'list']);
            Route::post('/create', [PermissionController::class, 'store']);
            Route::put('/update', [PermissionController::class, 'update']);
            Route::put('/delete', [PermissionController::class, 'destroy']);
            Route::get('/report-chart-list', [PermissionController::class, 'report_chart_list']);
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

    Route::group(['prefix' => 'dashboard-overall'], function () {
        Route::get('/', [DashboardController::class, 'overAll']);
        Route::get('/daily-message/level-three/', [DashboardController::class, 'dailyMessageLevelThree']);
        Route::get('/daily-message/level-four/', [DashboardController::class, 'dailyMessageLevelFour']);
        Route::get('/key-stats/', [DashboardController::class, 'keyStats']);
        Route::get('/keyword-summary/', [DashboardController::class, 'keywordSummary']);
        Route::get('/keyword-summary-top/', [DashboardController::class, 'keywordSummaryTop']);
        Route::get('/sentiment-score/', [DashboardController::class, 'sentimentScore']);
        Route::get('/sentiment-type/', [DashboardController::class, 'sentimentType']);
        Route::get('/share-of-voice/', [DashboardController::class, 'shareOfVoice']);
        Route::get('/share-of-voice-number/', [DashboardController::class, 'shareOfVoiceNumber']);
        Route::get('/sentiment-level/', [DashboardController::class, 'sentimentLevel']);
        Route::get('/word-clouds/', [DashboardController::class, 'wordClouds']);
        Route::get('/word-clouds-platform/', [DashboardController::class, 'wordCloudsPlateform']);
        Route::get('/word-clouds-position/', [DashboardController::class, 'wordCloudsPosition']);
    });

    Route::group(['prefix' => 'dashboard-voice'], function () {
        Route::get('/percentage-of-message', [VoiceDashboardController::class, 'PercentageOfMessage']);
        Route::get('/daily-message', [VoiceDashboardController::class, 'DailyMessage']);
        Route::get('/message-by-day', [VoiceDashboardController::class, 'MessageByDay']);
        Route::get('/message-by-time', [VoiceDashboardController::class, 'MessageByTime']);
        Route::get('/message-by-device', [VoiceDashboardController::class, 'MessageByDevice']);
        Route::get('/message-by-account', [VoiceDashboardController::class, 'MessageByAccount']);
        Route::get('/message-by-channel', [VoiceDashboardController::class, 'MessageByChannel']);
        Route::get('/message-by-sentiment', [VoiceDashboardController::class, 'MessageBySentiment']);
        Route::get('/message-by-level', [VoiceDashboardController::class, 'MessageByLevel']);
        Route::get('/message-by-type', [VoiceDashboardController::class, 'MessageByType']);
        Route::get('/number-of-account', [VoiceDashboardController::class, 'NumberOfAccount']);
        Route::get('/period-over-period', [VoiceDashboardController::class, 'PeriodOverPeriod']);
        Route::get('/day-time-comparison', [VoiceDashboardController::class, 'DayTimeComparison']);
        Route::get('/day-time-sentiment', [VoiceDashboardController::class, 'DayTimeSentiment']);
        Route::get('/day-time-level', [VoiceDashboardController::class, 'DayTimeLevel']);
        Route::get('/day-time-type', [VoiceDashboardController::class, 'DayTimeType']);
        Route::get('/channel-platform', [VoiceDashboardController::class, 'ChannelPlatform']);
        Route::get('/device', [VoiceDashboardController::class, 'Device']);
        Route::get('/channel-device', [VoiceDashboardController::class, 'ChannelDevice']);

        Route::get('/keyword-channel', [VoiceDashboardController::class, 'KeywordChannel']);
        Route::get('/keyword-sentiment', [VoiceDashboardController::class, 'KeywordSentiment']);
        Route::get('/keyword-bully-level', [VoiceDashboardController::class, 'KeywordBullyLevel']);
        Route::get('/keyword-bully-type', [VoiceDashboardController::class, 'KeywordBullyType']);

    });

    Route::group(['prefix' => 'dashboard-channel'], function () {
        Route::get('/percentage-of-channel', [ChannelDashboardController::class, 'PercentageOfChannel']);
        Route::get('/daily-channel', [ChannelDashboardController::class, 'DailyChannel']);
        Route::get('/channel-day', [ChannelDashboardController::class, 'ChannelByDay']);
        Route::get('/channel-time', [ChannelDashboardController::class, 'ChannelByTime']);
        Route::get('/channel-device', [ChannelDashboardController::class, 'ChannelByDevice']);
        Route::get('/channel-account', [ChannelDashboardController::class, 'ChannelByAccount']);
        Route::get('/channel-sentiment', [ChannelDashboardController::class, 'ChannelBySentiment']);
        Route::get('/channel-bully-level', [ChannelDashboardController::class, 'ChannelBullyLevel']);
        Route::get('/channel-bully-type', [ChannelDashboardController::class, 'ChannelBullyType']);
        Route::get('/period-over-period', [ChannelDashboardController::class, 'PeriodOverPeriod']);
        Route::get('/engagement-rate', [ChannelDashboardController::class, 'EngagementRate']);
        Route::get('/sentiment-score', [ChannelDashboardController::class, 'SentimentScore']);
        Route::get('/channel-by-sentiment', [ChannelDashboardController::class, 'ChannelBySentiment2']);
        Route::get('/sentiment-level', [ChannelDashboardController::class, 'SentimentLevel']);
    });

    Route::group(['prefix' => 'dashboard-engagement'], function () {
        Route::get('/engagement-trnsaction', [EngagementDashboardController::class, 'EngagementTrans']);
        Route::get('/engagement-day', [EngagementDashboardController::class, 'EngagementByDay']);
        Route::get('/engagement-time', [EngagementDashboardController::class, 'EngagementByTime']);
        Route::get('/engagement-device', [EngagementDashboardController::class, 'EngagementByDevice']);
        Route::get('/engagement-account', [EngagementDashboardController::class, 'EngagementByAccount']);
        Route::get('/engagement-channel', [EngagementDashboardController::class, 'EngagementChannel']);
        Route::get('/engagement-type', [EngagementDashboardController::class, 'EngagementType']);
        Route::get('/engagement-type-by-day', [EngagementDashboardController::class, 'EngagementByDayKey']);
        Route::get('/engagement-type-by-time', [EngagementDashboardController::class, 'EngagementByTimeKey']);
        Route::get('/engagement-type-by-device', [EngagementDashboardController::class, 'EngagementByDeviceKey']);
        Route::get('/engagement-type-by-account', [EngagementDashboardController::class, 'EngagementByAccountKey']);
        Route::get('/engagement-type-by-channel', [EngagementDashboardController::class, 'EngagementChannelKey']);
        Route::get('/engagement-comparison', [EngagementDashboardController::class, 'EngagementComparison']);
        Route::get('/engagement-period-platform', [EngagementDashboardController::class, 'EngagementPeriodPlarform']);
        Route::get('/engagement-period-sentiment', [EngagementDashboardController::class, 'EngagementPeriodSentiment']);
        Route::get('/engagement-type-comparison', [EngagementDashboardController::class, 'EngagementTypeComparison']);
        Route::get('/engagement-action-comparison', [EngagementDashboardController::class, 'EngagementActionComparison']);
        Route::get('/engagement-infulencer', [EngagementDashboardController::class, 'EngagementByInfulencer']);
        
    });

    Route::group(['prefix' => 'dashboard-sentiment'], function () {
        Route::get('/sentiment-daily', [SentimentDashboardController::class, 'DailySeniment']);
        Route::get('/sentiment-day', [SentimentDashboardController::class, 'SentimentByDay']);
        Route::get('/sentiment-time', [SentimentDashboardController::class, 'SentimentByTime']);
        Route::get('/sentiment-device', [SentimentDashboardController::class, 'SentimentByDevice']);
        Route::get('/sentiment-account', [SentimentDashboardController::class, 'SentimentByAccount']);
        Route::get('/sentiment-channel', [SentimentDashboardController::class, 'SentimentByChannel']);
        Route::get('/sentiment-bully-level', [SentimentDashboardController::class, 'SentimentBullyLevel']);
        Route::get('/sentiment-bully-type', [SentimentDashboardController::class, 'SentimentBullyType']);

        Route::get('/period-over-period', [SentimentDashboardController::class, 'PeriodOverPeriod']);
        Route::get('/comparison-channel', [SentimentDashboardController::class, 'ComparisonByChannel']);
        Route::get('/comparison-engagement-type', [SentimentDashboardController::class, 'ComparisonByEngagementType']);
        Route::get('/sentiment-score', [SentimentDashboardController::class, 'SentimentScore']);
        Route::get('/sentiment-comparison', [SentimentDashboardController::class, 'SentimentComparison']);
        Route::get('/summary-score-account', [SentimentDashboardController::class, 'SummaryScoreAccount']);
        Route::get('/summary-score-channel', [SentimentDashboardController::class, 'SummaryScoreChannel']);
        Route::get('/summary-keyword', [SentimentDashboardController::class, 'SummaryKeyword']);
    });

    Route::group(['prefix' => 'dashboard-bully'], function () {
        Route::get('/bully-daily', [BullyDashboardController::class, 'DailyBully']);
        Route::get('/bully-day', [BullyDashboardController::class, 'BullyByDay']);
        Route::get('/bully-time', [BullyDashboardController::class, 'BullyByTime']);
        Route::get('/bully-device', [BullyDashboardController::class, 'BullyByDevice']);
        Route::get('/bully-account', [BullyDashboardController::class, 'BullyByAccount']);
        Route::get('/bully-channel', [BullyDashboardController::class, 'BullyByChannel']);
        Route::get('/bully-sentiment', [BullyDashboardController::class, 'BullyBySentiment']);
        Route::get('/bully-percentage-daily', [BullyDashboardController::class, 'BullyPercentageDaily']);
        Route::get('/bully-type-day', [BullyDashboardController::class, 'BullyTypeByDay']);
        Route::get('/bully-type-time', [BullyDashboardController::class, 'BullyTypeByTime']);
        Route::get('/bully-type-device', [BullyDashboardController::class, 'BullyTypeByDevice']);
        Route::get('/bully-type-account', [BullyDashboardController::class, 'BullyTypeByAccount']);
        Route::get('/bully-type-channel', [BullyDashboardController::class, 'BullyTypeByChannel']);
        Route::get('/bully-type-sentiment', [BullyDashboardController::class, 'BullyTypeBySentiment']);
        Route::get('/bully-chart-level', [BullyDashboardController::class, 'BullyChartLevel']);
        Route::get('/bully-table-level', [BullyDashboardController::class, 'BullyLevelLevel']);
        Route::get('/bully-chart-type', [BullyDashboardController::class, 'BullyChartType']);
        Route::get('/bully-table-type', [BullyDashboardController::class, 'BullyTableType']);
    });


    Route::group(['prefix' => 'organization-content'], function () {
        Route::get('/', [OrganizationContentController::class, 'index']);
        Route::post('/', [OrganizationContentController::class, 'store']);
        Route::post('/update', [OrganizationContentController::class, 'update']);
    });
});








