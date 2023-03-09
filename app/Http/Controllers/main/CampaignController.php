<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Organization;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {

        $campaigns = Campaign::all();

        if ($request->organization_id) {
            $campaigns = $campaigns->where('organization_id', $request->organization_id);
        }

        if ($request->status) {
            $campaigns = $campaigns->where('status', $request->status);
        }

        if ($request->name) {
            $campaigns = $campaigns->where('name', $request->name);
        }


        $data = [];
        foreach ($campaigns as $campaign) {
            $campaign->keyword = Keyword::where('campaign_id', $campaign->id)->get();

            if ($campaign->keyword) {
                foreach ($campaign->keyword as $item) {
                    $item->keyword_or = explode(",", $item->keyword_or);
                    $item->keyword_and = explode(",", $item->keyword_and);
                    $item->keyword_exclude = explode(",", $item->keyword_exclude);
                }
//                $item->keyword_or = explode(",",$item->keyword_or)();
//                $item->keyword_and = json_decode($item->keyword_and);
//                $item->keyword_exclude = json_decode($item->keyword_exclude);
            }
            $campaign->organization = Organization::find($campaign->organization_id)->name;
            $data[] = $campaign;

        }
        return parent::handleRespond($data);
    }

    public function show(Request $request)
    {
        $id = $request->id;

        $res = $this->find($id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function store(Request $request)
    {
        $data_submit = [
            BaseModel::NAME => $request->name ?? '',
            Campaign::DESCRIPTION => $request->description,
            BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
            Campaign::DOMAIN_ID => $request->domain_id ?? 1,
            BaseModel::STATUS => $request->status ?? 1,
            Campaign::EXCLUDE_CAMPAIGN => collect($request->exclude_campaign)->implode(','),
            Campaign::START_AT => $request->start_at,
            Campaign::END_AT => $request->end_at,
            Campaign::FREQUENCY => (int)$request->frequency ?? 120,
        ];

        $campaign = Campaign::create($data_submit);

        if ($request->keywords) {

            foreach ($request->keywords as $index => $keyword) {

                $keyword_and = collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(',');
                $name = $keyword[BaseModel::NAME];

                if ($keyword_and) {
                    $name .= "," . $keyword_and;
                }

                $data_submit_keyword = [
                    Keyword::CAMPAIGN_ID => $campaign->id,
                    BaseModel::NAME => $name,
                    Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                    Keyword::KEYWORD_AND => $keyword_and,
                    Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                    BaseModel::STATUS => 1,
                    BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                    BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                    "color" => $keyword["keyword_and_color"][$index] ?? "#000000",
                    "label" => $keyword["name"]
                ];

                $parent_keyword = Keyword::create($data_submit_keyword);

                $this->extra_keyword(
                    $campaign->id,
                    $keyword['name'],
                    $parent_keyword->id,
                    collect($keyword[Keyword::KEYWORD_OR] ?? []),
                    collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(','),
                    collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                    $keyword
                );
            }
        }

        return parent::handleRespond($campaign);
    }

    private function extra_keyword($campaign_id, $parent_name, $parent_id, $keyword_ors, $keyword_and, $keyword_exclude, $keyword, $is_update = false)
    {
        $name = $parent_name;
        if ($keyword_and) {
            $name = $name . "," . $keyword_and;
        }
        // loop for keyword or
        foreach ($keyword_ors as $index => $keyword_or) {

            $data_submit_keyword = [
                Keyword::CAMPAIGN_ID => $campaign_id,
                Keyword::PARENT_ID => $parent_id,
                BaseModel::NAME =>  $name. "," . $keyword_or,
                Keyword::KEYWORD_OR => collect($keyword_or ?? [])->implode(','),
                Keyword::KEYWORD_AND => collect($keyword_and ?? [])->implode(','),
                Keyword::KEYWORD_EXCLUDE => collect($keyword_exclude ?? [])->implode(','),
                BaseModel::STATUS => 1,
                BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                "color" => $keyword["keyword_or_color"][$index] ?? "#000000",
            ];


            if ($is_update) {
                /// todo
                if (isset($keyword["delete_keyword_or"]) && count($keyword["delete_keyword_or"]) > 0) {
                    foreach ($keyword["delete_keyword_or"] as $delete_keyword_or) {
                        Keyword::where("keyword_or" ,$delete_keyword_or)->delete();
                    }
                }

                $items = Keyword::where('parent_id', $parent_id)->get();


                foreach ($items as $k_index => $item) {

                   if ($k_index === $index) {
                       $item->update($data_submit_keyword);
                   } else {
                       Keyword::create($data_submit_keyword);
                   }
                }

            } else {
                Keyword::create($data_submit_keyword);
            }
        }


    }

    public function update(Request $request)
    {
        $id = $request->id;

        try {
            $campaign = $this->find($id);

            if ($campaign[BaseModel::STATUS] !== 200) {


//                return
//                if ($action === BaseModel::UPDATE_TEXT) {
//                    $data->update($request->all());
//                    return parent::handleRespond($data);
//                }


                return parent::handleErrorRespond($request->all());
            }

            $data = $campaign[BaseModel::DATA_TEXT];

            $data_submit = [
//                BaseModel::NAME => $request->name ?? '',
//                Campaign::DESCRIPTION => $request->description,
//                BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
//                Campaign::DOMAIN_ID => $request->domain_id ?? 1,
//                BaseModel::STATUS => $request->status ?? 1,
//                Campaign::EXCLUDE_CAMPAIGN => collect($request->exclude_campaign)->implode(','),
//                Campaign::START_AT => $request->start_at,
//                Campaign::END_AT => $request->end_at,
            ];

            if ($request->name) {
                $data_submit[BaseModel::NAME] = $request->name;
            }

            if ($request->description) {
                $data_submit[Campaign::DESCRIPTION] = $request->description;
            }

            if ($request->organization_id) {
                $data_submit[BaseModel::ORGANIZATION_ID] = $request->organization_id;
            }

            if ($request->domain_id) {
                $data_submit[Campaign::DOMAIN_ID] = $request->domain_id;
            }

            if ($request->status || !$request->status) {
                $data_submit[BaseModel::STATUS] = $request->status;
            }

            if ($request->exclude_campaign) {
                $data_submit[Campaign::EXCLUDE_CAMPAIGN] = collect($request->exclude_campaign)->implode(',');
            }

            if ($request->start_at) {
                $data_submit[Campaign::START_AT] = $request->start_at;
            }

            if ($request->end_at) {
                $data_submit[Campaign::END_AT] = $request->end_at;
            }

            if ($request->frequency) {
                $data_submit[Campaign::FREQUENCY] = $request->frequency;
            }

            $data->update($data_submit);

            if ($request->keywords) {
                foreach ($request->keywords as $keyword) {

                    $keyword_and = collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(',');
                    $name = $keyword[BaseModel::NAME];

                    if ($keyword_and) {
                        $name .= "," . $keyword_and;
                    }


                    $data_submit_keyword = [
                        Keyword::CAMPAIGN_ID => $data->id,
                        BaseModel::NAME => $name,
                        Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                        Keyword::KEYWORD_AND => $keyword_and,
                        Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                        BaseModel::STATUS => 1,
                        BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                        BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                    ];


                    if (isset($keyword['keyword_and_color'])) {
                        $data_submit_keyword["color"] = $keyword['keyword_and_color'][0];
                    }

                    $updated = Keyword::updateOrCreate(['id' => $keyword['id'] ?? null], $data_submit_keyword);

                    $this->extra_keyword(
                        $data->id,
                        $keyword['name'],
                        $updated->id,
                        collect($keyword[Keyword::KEYWORD_OR] ?? []),
                        collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(','),
                        collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                        $keyword,
                        true
                    );
                }
            }


            return parent::handleRespond($this->find($id));

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request)
    {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];
        $campaign = Campaign::find($id);
        if ($id && $campaign) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $campaign;
            return $res;
        }
        return $res;
    }

    public function search(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;

        $campaigns = Campaign::query()->offset($start)->limit($limit);

        if ($request->name) {
            $campaigns = $campaigns->where('name', 'like', "%$request->name%");
        }

        if ($request->status || $request->status === '0') {
            $campaigns = $campaigns->where('status', $request->status);
        }


        if ($request->organization_id) {
            $campaigns = $campaigns->where('organization_id', $request->organization_id);
        }

        if ($request->start_at) {
            $campaigns = $campaigns->where('start_at', '<=', $request->start_at);
        }

        if ($request->end_at) {
            $campaigns = $campaigns->where('end_at', '>=', $request->end_at);
        }

        $data = [];

        foreach ($campaigns->get() as $campaign) {
            $campaign->keyword = Keyword::where('campaign_id', $campaign->id)->whereNull('parent_id')->get();

            if ($campaign->keyword) {

                foreach ($campaign->keyword as $item) {

                    $item->name = $item->label ?? $item->name;
                    $keyword_colors = Keyword::where('campaign_id', $campaign->id)->where('parent_id', $item->id)->get('color');

                    if ($keyword_colors) {
                        $item->keyword_or_color = explode(',', $keyword_colors->implode('color', ','));
                        $item->keyword_and_color = $item->color;

                    } else {
                        $item->keyword_and_color = $item->color;
                    }

                    $item->keyword_or = explode(",", $item->keyword_or);
                    $item->keyword_and = explode(",", $item->keyword_and);
                    $item->keyword_exclude = explode(",", $item->keyword_exclude);
                }
            }

            $campaign->organization = Organization::find($campaign->organization_id)->name;
            $data['list'][] = $campaign;
            $data['keyword_limit'] = $this->organization_group->total_keyword;



        }

        return parent::handleRespond($data);
    }
}
