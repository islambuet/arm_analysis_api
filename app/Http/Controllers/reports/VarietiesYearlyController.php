<?php
namespace App\Http\Controllers\reports;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class VarietiesYearlyController extends RootController
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
            $trial_stations=DB::table(TABLE_TRIAL_STATIONS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $seasons=DB::table(TABLE_SEASONS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
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
                'trial_stations' => $trial_stations,
                'seasons'=>$seasons,
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

            $query=DB::table(TABLE_TRIAL_VARIETIES.' as trial_varieties');
            $query->select('trial_varieties.variety_id','trial_varieties.delivery_status','trial_varieties.delivered_date','trial_varieties.sowing_status','trial_varieties.sowing_date');
            $query->where('trial_varieties.trial_station_id',$options['trial_station_id']);
            $query->where('trial_varieties.year',$options['year']);
            $query->where('trial_varieties.season_id', $options['season_id']);
            $results = $query->get();
            $itemsTrial=[];
            foreach ($results as $result){
                $itemsTrial[$result->variety_id]=$result;
            }
            //
            $query=DB::table(TABLE_SELECTED_VARIETIES.' as selected_varieties');
            $query->select('selected_varieties.rnd_code');
            $query->where('selected_varieties.year',$options['year']);
            $query->where('selected_varieties.season_ids', 'like', '%,'.$options['season_id'].',%');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'selected_varieties.variety_id');
            $query->addSelect('varieties.*');

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
            $results = $query->get();
            $items=[];
            foreach ($results as $result){
                $result->delivered_date='';
                $result->sowing_date='';
                if(isset($itemsTrial[$result->id])){
                    if($itemsTrial[$result->id]->delivery_status==SYSTEM_STATUS_YES){
                        $result->delivered_date=$itemsTrial[$result->id]->delivered_date;
                    }
                    if($itemsTrial[$result->id]->sowing_status==SYSTEM_STATUS_YES){
                        $result->sowing_date=$itemsTrial[$result->id]->sowing_date;
                    }
                }
                $items[]=$result;

            }
            return response()->json(['error'=>'','items'=> ['data'=>$items],'inputs'=>$request->all()]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

