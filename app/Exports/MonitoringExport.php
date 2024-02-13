<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use App\Models\MessageResultFullData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MonitoringExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */

    private $report;
    private $report_type;
    private $headingSentiment = [
        "No.",
        "Account Name",
        "Channel",
        "Total Post",
        "Total Engagement",
        "Positive",
        "Normal",
        "Negative"
    ];
    private $headingEngagement = [
        "No.",
        "Keyword",
        "Account Name",
        "Message Detail",
        "Post Time",
        "Channel",
        "Engagement",
        "Sentiment",
        "Bully Level",
        "Bully Type",
    ];


    public function __construct($report, $report_type)
    {
        $this->report = $report;
        $this->report_type = $report_type;
    }

    public function headings(): array
    {
        if ($this->report_type === 'sentiment') {
            return $this->headingSentiment;
        } else {
            return $this->headingEngagement;
        }
    }

    public function collection()
    {
        $excel = [];
        if ($this->report_type === 'dailyMessage') {
            foreach ($this->report as $position => $item) {
                $date_d = Carbon::parse($item->date_m)->format('D');

                $row = array();
                $row["no"] = $position + 1;
                $row["message_id"] = $item->message_id;
                $row["keyword_name"] = $item->keyword_name;
                $row["message_detail"] = $item->full_message;
                $row["account_name"] = $item->author;
                $row["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
                $row["post_time"] = Carbon::parse($item->date_m)->format('H:i');
                $row["day"] = $date_d;
                $row["message_type"] = $item->message_type;
                $row["device"] = $item->device;
                $row["channel"] = $item->source_name;
                $row["source_name"] = $item->source_name;
                $row["link_message"] = $item->link_message;
                $row[$item->classification_type_id] = $item->classification_name;
            }

        } else if ($this->report_type === 'sentiment') {
            foreach ($this->report as $position => $item) {

                $row = array();
                $row["no"] = $position + 1;
                $row["account_name"] = $item["account_name"];
                $row["source_name"] = $item["source_name"];
                $row["total_post"] = $item["total_post"];
                $row["total_engagement"] = $item["total_engagement"];
                $row["negative"] = $item["negative"];
                $row["neutral"] = $item["neutral"];
                $row["positive"] = $item["positive"];
                $excel[$position][] = $row;
            }
        } else {
            foreach ($this->report as $position => $item) {
                $row = array();
                $row["no"] = $position + 1;
                $row["keyword_name"] = $item["keyword_name"];
                $row["account_name"] = $item["account_name"];
                $row["message_detail"] = $item["full_message"];
                $row["post_time"] = Carbon::parse($item["date_m"])->format('Y/m/d H:i');
                $row["source_name"] = $item["source_name"];
                $row["total_engagement"] = $item["total_engagement"];
                $row["sentiment"] = $item["sentiment"];
                $row["bully_level"] = $item["bully_level"];
                $row["bully_type"] = $item["bully_type"];
                $excel[$position][] = $row;
            }
        }
        return collect($excel);
    }

}
