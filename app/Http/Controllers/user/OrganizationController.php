<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BaseModel;
use App\Models\Organization;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class OrganizationController extends Controller
{
    public function index()
    {

    }

    /**
     * this function for create Organization
     *
     * @param Request $request
     * @param name
     * @return \Illuminate\Http\JsonResponse
     */
    public function stroge(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [
            BaseModel::NAME => $request->name,
            BaseModel::STATUS => true,
            Organization::GROUP_ID => $request->group_id,
            Organization::TYPE_ID => $request->type_id,
            BaseModel::CREATED_BY => auth()->id() ?? null, // todo check auth
            BaseModel::UPDATED_BY => auth()->id() ?? null, // todo check auth
        ];

        try {
            $organization = Organization::create($data);
            parent::audi_log($request, BaseModel::CREATE_TEXT, $organization->id, auth()->id(), null, $request->all());

            return parent::handleRespond($organization, [], 200, BaseModel::SUCCESS_TEXT);

        } catch (Exception $exception) {

            return parent::handleRespond(
                $exception->getMessage(),
                null,
                $exception->getCode(),
                $exception->getMessage());
        }
    }


    public function destory(Request $request)
    {
       return $this->update($request);
    }

    public function update(Request $request)
    {
        $organization = Organization::find('id', $request->id);

        if ($organization) {
            try {
                //todo check update;
                $organization->update($request->all());
            } catch (Exception $exception) {
                return parent::handleRespond(
                    $exception->getMessage(),
                    null,
                    $exception->getCode(),
                    $exception->getMessage());
            }
        }
    }

}
