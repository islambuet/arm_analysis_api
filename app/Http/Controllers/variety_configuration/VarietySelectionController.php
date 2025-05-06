<?php
namespace App\Http\Controllers\variety_configuration;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\CommonHelper;
use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class VarietySelectionController extends RootController
{
    public $api_url = 'variety-configuration/selection';
    public $permissions;
    public $cropInfo;

    public function __construct()
    {
        parent::__construct();
        $cropId = \Route::current()->parameter('cropId', 0);
        $this->cropInfo = DB::table(TABLE_CROPS)->find($cropId);
        if ($this->cropInfo) {
            $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
        } else {
            $this->permissions = TaskHelper::getAllPermissions(false);
        }
    }

    public function initialize(Request $request, $cropId, $year): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $crop_types = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->where('crop_id', $cropId)
                ->get();
            $seasons=DB::table(TABLE_SEASONS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();


            return response()->json(
                ['error' => '', 'permissions' => $this->permissions,
                    'hidden_columns' => TaskHelper::getHiddenColumns($this->api_url, $this->user,),
                    'cropInfo' => $this->cropInfo,
                    'crop_types' => $crop_types,
                    'seasons'=>$seasons
                ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request, $cropId, $year): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {

            $varieties=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->addSelect('crop_types.name as crop_type_name','crop_types.code as crop_type_code')
                ->leftJoin(TABLE_SELECTED_VARIETIES.' as selected_varieties',function($join) use($year){
                     $join->on('selected_varieties.variety_id', '=', 'varieties.id')
                         ->on('selected_varieties.year',DB::raw($year));
                })
                ->addSelect('selected_varieties.rnd_ordering','selected_varieties.rnd_code','selected_varieties.season_ids','selected_varieties.created_at','selected_varieties.updated_at')
                ->orderBy('selected_varieties.rnd_ordering', 'DESC')
                ->orderBy('varieties.id', 'DESC')
                ->where('varieties.status', SYSTEM_STATUS_ACTIVE)
                ->where('crop_types.crop_id', $cropId)
                ->get();
            return response()->json(['error'=>'','items'=> ['data'=>$varieties]]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function saveItem(Request $request, $cropId, $year): JsonResponse
    {
        $itemId=0;
        $varietyId = $request->input('variety_id', 0);
        if ($this->permissions->action_2 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }
        $variety=DB::table(TABLE_VARIETIES.' as varieties')
            ->select('varieties.*')
            ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
            ->addSelect('crop_types.name as crop_type_name','crop_types.code as crop_type_code')
            ->where('varieties.id', $varietyId)
            ->first();
        if(!$variety){
            return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid varietyId ' . $varietyId)]);
        }
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['season_ids'] = ['nullable'];
        $itemNew = $request->input('item');
        if(isset($itemNew['season_ids'])){
            $itemNew['season_ids']=','.implode(',',$itemNew['season_ids']).',';
        }
        else{
            $itemNew['season_ids']=',';
        }
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        $result = DB::table(TABLE_SELECTED_VARIETIES)
            ->select('id','season_ids','rnd_ordering')
            ->where('year',$year)
            ->where('variety_id',$varietyId)
            ->first();
        if ($result) {
            $itemId=$result->id;
            $itemOld = (array)$result;
            $itemNew['rnd_ordering']=$itemOld['rnd_ordering'];
            if($itemNew['rnd_ordering']>0){
                if($itemNew['season_ids']==$itemOld['season_ids']){
                    return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Nothing was Changed']);
                }
            }
        }
        else{
            $itemNew['year']=$year;
            $itemNew['variety_id']=$varietyId;
            $itemNew['rnd_ordering']=0;
        }
        if($itemNew['rnd_ordering']==0){
            $max_result=DB::table(TABLE_SELECTED_VARIETIES.' as selected_varieties')
                ->select(DB::raw('MAX(selected_varieties.rnd_ordering) as max_ordering'))
                ->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'selected_varieties.variety_id')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->where('crop_types.crop_id',$cropId)
                ->where('selected_varieties.year',$year)
                ->first();
            if($max_result->max_ordering>0){
                $itemNew['rnd_ordering']=$max_result->max_ordering+1;
            }
            else{
                $itemNew['rnd_ordering']=1;
            }
        }
        $variety->crop_code=$this->cropInfo->code;
        $variety->rnd_ordering=$itemNew['rnd_ordering'];
        if($variety->whose=='Principal')
        {
            $variety->principal_info=DB::table(TABLE_PRINCIPALS)->where('id',$variety->principal_id)->first();
        }
        if($variety->whose=='Competitor')
        {
            $variety->competitor_info=DB::table(TABLE_COMPETITORS)->where('id',$variety->competitor_id)->first();
        }
        $itemNew['rnd_code']=CommonHelper::generate_rnd_code($variety,$year);
        //TODO validate crop_id
        //Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_VARIETIES;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_SELECTED_VARIETIES)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            }
            else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_SELECTED_VARIETIES)->insertGetId($itemNew);
                $dataHistory['table_id'] = $newId;
                $dataHistory['action'] = DB_ACTION_ADD;
            }
            unset($itemNew['updated_by'],$itemNew['created_by'],$itemNew['created_at'],$itemNew['updated_at']);

            $dataHistory['data_old'] = json_encode($itemOld);
            $dataHistory['data_new'] = json_encode($itemNew);
            $dataHistory['created_at'] = $time;
            $dataHistory['created_by'] = $this->user->id;

            $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '', 'messages' => 'Data (' . $newId . ')' . ($itemId > 0 ? 'Updated' : 'Created') . ')  Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
}



