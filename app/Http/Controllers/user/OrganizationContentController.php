<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\OrganizationContent;
use Illuminate\Http\Request;

class OrganizationContentController extends Controller
{

    public function __construct(Request $request)
    {
//        $token = request()->bearerToken();

//        dd($token);
//        parent::__construct($request);
    }


    public function index(Request $request)
    {
        $organization_content = OrganizationContent::where('organization_id', $this->organization->id)->get();
        if (!$organization_content) {
            return parent::handleNotFound('Organization content not found');
        }

        return parent::handleRespond($organization_content);
    }

    public function store(Request $request)
    {
        $rules = [
            'title' => 'required',
            'content' => 'nullable',
            'content_id' => 'nullable',
            'status' => 'nullable',
            'picture' => 'nullable|image|mimes:png,jpg,jpeg|max:2048'
        ];

        $request_data = parent::validate($request, $rules);

        if (isset($request_data['invalid'])) {
            return parent::handleRespond($request_data, [], 500, 'Error');
        }

        $data = [
            'title' => $request->title,
            'content_text' => $request->content_text,
            'content_id' => $request->content_id ?? 3,
            'status' => $request->status ?? 1,
            'organization_id' => $this->organization->id,
            'date' => $request->date ?? date('Y-m-d'),
            BaseModel::CREATED_BY => auth('api')->id() ?? 1, // todo check auth
            BaseModel::UPDATED_BY => auth('api')->id() ?? 1, // todo check auth
        ];


        if ($request->picture) {
            $data['picture'] = parent::uploadImage($request->picture);
        }

        $organization_content = OrganizationContent::create($data);
        return parent::handleRespond($organization_content);
    }

    public function update(Request $request)
    {

        $id = $request->id;


        if (!$id) {
            return parent::handleNotFound('content id not found');
        }

        $rules = [
            'title' => 'required',
            'content' => 'nullable',
            'content_id' => 'nullable',
            'status' => 'nullable',
            'picture' => 'nullable|image|mimes:png,jpg,jpeg|max:2048'
        ];

        $request_data = parent::validate($request, $rules);


        $data = [];
        if (isset($request_data['invalid'])) {
            return parent::handleRespond($request_data, [], 500, 'Error');
        }

        if ($request->picture) {
            $data['picture'] = parent::uploadImage($request->picture);
        }

        if ($request->title) {
            $data['title'] = $request->title;
        }

        if ($request->content_text) {
            $data['content_text'] = $request->content_text;
        }

        $data['status'] = 1;

        if ($request->status || $request->status == 0) {
            $data['status'] = $request->status ?? 0;
        }

        if ($request->date) {
            $data['date'] = $request->date;
        }


        $organization_content = OrganizationContent::where('organization_id', $this->organization_->id)->where('id' , $id)->first();
        if ($organization_content) {
            $organization_content->update($data);
            return parent::handleRespond($organization_content);
        }

        return  parent::handleNotFound($request->all());

    }


}
