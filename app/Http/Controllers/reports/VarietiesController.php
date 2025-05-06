<?php
namespace App\Http\Controllers\reports;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class VarietiesController extends RootController
{
    public $api_url = 'reports/varieties';
    public $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $crops = DB::table(TABLE_CROPS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $crop_types = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $crop_features = DB::table(TABLE_CROP_FEATURES)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $principals = DB::table(TABLE_PRINCIPALS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $competitors = DB::table(TABLE_COMPETITORS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            return response()->json([
                'error'=>'','permissions'=>$this->permissions,
                'hidden_columns'=>TaskHelper::getHiddenColumns($this->api_url,$this->user),
                'crops'=>$crops,
                'crop_types'=>$crop_types,
                'crop_features'=>$crop_features,
                'principals'=>$principals,
                'competitors'=>$competitors,

            ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $options = $request->input('options');
            $perPage = $request->input('perPage', -1);
            //$query=DB::table(TABLE_CROP_TYPES);
            $query=DB::table(TABLE_VARIETIES.' as varieties');
            $query->select('varieties.*');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name');
            if($options['crop_type_id']>0){
                $query->where('crop_types.id', $options['crop_type_id']);
            }
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name');
            if($options['crop_id']>0){
                $query->where('crops.id', $options['crop_id']);
            }
            $query->leftJoin(TABLE_PRINCIPALS.' as principals', 'principals.id', '=', 'varieties.principal_id');
            $query->addSelect('principals.name as principal_name');
            if($options['principal_id']>0){
                $query->where('principals.id', $options['principal_id']);
            }
            $query->leftJoin(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id');
            $query->addSelect('competitors.name as competitor_name');
            if($options['competitor_id']>0){
                $query->where('competitors.id', $options['competitor_id']);
            }
            $query->orderBy('crops.ordering', 'ASC');
            $query->orderBy('crop_types.ordering', 'ASC');
            $query->orderBy('varieties.ordering', 'ASC');
            $query->orderBy('varieties.id', 'DESC');
            $query->where('varieties.status', '!=', SYSTEM_STATUS_DELETE);//
            if ($perPage == -1) {
                $perPage = $query->count();
                if($perPage<1){
                    $perPage=50;
                }
            }
            $results = $query->paginate($perPage)->toArray();
            return response()->json(['error'=>'','items'=>$results,'inputs'=>$request->all()]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

