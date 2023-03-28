<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use App\Models\MessageResultFullData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OverAllExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    public function __construct($report)
    {
        $this->report = $report;
    }

    public function headings(): array
    {
        return [
            "Message ID", 
            "Message Detail", 
            "Account Name",
            "Post Date",
            "Post Time",
            "Day",
            "Message Type",
            "Device",
            "Channel",
            "Source Name",
            "Link Message",
            "Parent",
            "Sentiment",
            "Bully Type",
            "Bully Level",
        ];
    }
    
    public function collection()
    {
        $items = $this->report->get();

        $parents = [];
        foreach ($items as $item) {
            if ($item->reference_message_id) {
                if (array_search($item->reference_message_id, $parents) === false) {
                    $parents[] = $item->reference_message_id;
                }
            }
        }


        foreach ($items as $ke => $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->message_id);
            $parent = null;

            if (array_search($item->message_id, $parents) !== false) {
                $parent = $item->message_id;
            }
            
            // $i = 0;

            $data_push = [
                // 'no' => $i++,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "message_type" => $item->message_type,
                "device" => $item->device,
                "channel" => $item->source_name,
                "source_name" => $item->source_name,
                "link_message" => $item->link_message,
                "parent" => $parent
            ];


            // loop for get classification name
            foreach ($types as $type) {
                if ($type->classification_type_id == 1) {
                    $data_push['sentiment'] = $type->classification_name;
                }

                if ($type->classification_type_id == 2) {
                    $data_push['bully_type'] = $type->classification_name;
                }

                if ($type->classification_type_id == 3) {
                    $data_push['bully_level'] = $type->classification_name;
                }
            }

            $data[] = $data_push;
        }


        if (isset($data['message'])) {
            $data = array_values($data);
        }

        return collect($data);
    }

    private function getClassificationName($message_id)
    {
        return MessageResultFullData::where('message_id', $message_id)
            ->limit(3)->get(['classification_type_id', 'classification_name']);
    }
}
