<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Campaign;

use App\Models\DailyMessage;
use App\Models\Keyword;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overAll(Request $request)
    {
        $campaign_id = $request->campaign_id;

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $data = null;
        $data['daily_message_current'] = $this->dailyMessage($campaign_id);
        $data['daily_message_previous'] = $this->dailyMessage($campaign_id, '2022-11-30', '2022-11-30','daily');

        return parent::handleRespond($data);
    }

    private function dailyMessage($campaign_id, $start_date, $end_date, $period = 'current') {
        $daily_messages = DailyMessage::where('campaign_id', $campaign_id)->get();
//        foreach ($daily_messages as $daily_message) {
////
//            $daily_message->keyword = Keyword::where('campaign_id', $campaign_id)->get();
//        }
//            $daily_message->campaign = Campaign::find($daily_message->campaign_id);

        return  $daily_messages;
    }

    private function percentageOfMessages() {

    }

    private function totalMessages() {

    }

    private function totalEngagement() {

    }

    private function totalAccounts() {

    }

    private function KeyWords() {

    }

    private function mainKeyWords() {

    }

    private function SubKeyword() {

    }

    private function topSites() {

    }

    private function topHashtag() {

    }

    private function sentimentScore() {

    }

    private function commentSentiment() {

    }

    private function shareOfVoice() {

    }








}
