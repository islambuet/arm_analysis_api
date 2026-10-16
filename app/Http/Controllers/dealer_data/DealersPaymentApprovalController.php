<?php
namespace App\Http\Controllers\dealer_data;

use App\Helpers\TaskHelper;
use App\Http\Controllers\RootController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class DealersPaymentApprovalController extends RootController
{
    public $api_url = 'dealer_data/dealers_payment_approval';
    public $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response = [];
            $response['error'] ='';
            $response['permissions']=$this->permissions;
            $response['hidden_columns']=TaskHelper::getHiddenColumns($this->api_url,$this->user);
            $response['location_parts'] = DB::table(TABLE_LOCATION_PARTS)
                ->select('id', 'name', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['location_areas'] = DB::table(TABLE_LOCATION_AREAS)
                ->select('id', 'name','part_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['location_territories'] = DB::table(TABLE_LOCATION_TERRITORIES)
                ->select('id', 'name','area_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();

            $response['distributors'] = DB::table(TABLE_DISTRIBUTORS)
                ->select('id', 'name','territory_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['dealers'] = DB::table(TABLE_DEALERS)
                ->select('id', 'name','distributor_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['user_locations']=['part_id'=>$this->user->part_id,'area_id'=>$this->user->area_id,'territory_id'=>$this->user->territory_id];
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response=[];
            $response['error'] = '';
            $perPage=$request->input('perPage',50);
            $options = $request->input('options');

            //$query=DB::table(TABLE_CROP_TYPES);
            $query=DB::table(TABLE_DEALERS_PAYMENT.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'ds.dealer_id');
            $query->addSelect('dealers.name as dealer_name');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
            $query->addSelect('d.name as distributor_name');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');

            if($options['part_id']>0){
                $query->where('parts.id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                            if($options['dealer_id']>0){
                                $query->where('dealers.id','=',$options['dealer_id']);
                            }
                        }
                    }
                }
            }
            if($options['payment_from']){
                $query->whereDate('ds.payment_at','>=',$options['payment_from']);
            }
            if($options['payment_to']){
                $query->whereDate('ds.payment_at','<=',$options['payment_to']);
            }

            $query->orderBy('ds.id', 'DESC');
            $query->where('ds.status', '!=', SYSTEM_STATUS_DELETE);
            if ($perPage == -1) {
                $perPage = $query->count();
                if($perPage<1){
                    $perPage=50;
                }
            }
            $results = $query->paginate($perPage)->toArray();
            $response['items'] = $results;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $query=DB::table(TABLE_DEALERS_PAYMENT.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'ds.dealer_id');
            $query->addSelect('dealers.name as dealer_name');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
            $query->addSelect('d.name as distributor_name','d.id as distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name','territories.id as territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name','areas.id as area_id');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name','parts.id as part_id');
            $query->where('ds.status', '!=', SYSTEM_STATUS_DELETE);
            $query->where('ds.id','=',$itemId);
            $result = $query->first();
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            return response()->json(['error'=>'','item'=>$result]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
        }
    }
    public function saveItem(Request $request): JsonResponse
    {
        $itemId = $request->input('id', 0);//must not be 0
        if ($this->permissions->action_2 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['payment_at'] = ['required'];
        $validation_rule['dealer_id'] = ['required','numeric'];
        $validation_rule['amount'] = ['required','numeric'];

        $itemNew = $request->input('item');
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        //edit change checking

            $result = DB::table(TABLE_DEALERS_PAYMENT)->select(array_merge(array_keys($validation_rule),['status']))->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            if ($result->status !='Pending') {
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Payment already '.$result->status]);
            }
            $itemOld = (array)$result;
            foreach ($itemOld as $key => $oldValue) {
                if (array_key_exists($key, $itemNew)) {

                    if ($oldValue == $itemNew[$key]) {
                        //unchanged so remove from both
                        unset($itemNew[$key]);
                        unset($itemOld[$key]);
                        unset($validation_rule[$key]);
                    }
                } else {
                    //will not happen if it comes form vue. removing rule and key for not change
                    unset($validation_rule[$key]);
                    unset($itemOld[$key]);
                }
            }

        //if itemNew Empty
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Nothing was Changed']);
        }
        $this->validateInputValues($itemNew, $validation_rule);
        //TODO validate crop_id
        //Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_DEALERS_PAYMENT;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_DEALERS_PAYMENT)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_DEALERS_PAYMENT)->insertGetId($itemNew);
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
    public function deleteItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_3 == 1) {
            $response = [];
            $response['error'] = '';
            $result = DB::table(TABLE_DEALERS_PAYMENT)->where('status', '!=', SYSTEM_STATUS_DELETE)->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }

            $time = Carbon::now();
            $itemNew['status']=SYSTEM_STATUS_DELETE;
            $itemNew['deleted_by'] = $this->user->id;
            $itemNew['deleted_at'] = $time;

            DB::beginTransaction();
            try {
                DB::table(TABLE_DEALERS_PAYMENT)->where('id', $itemId)->update($itemNew);
                DB::commit();
                return response()->json($response);
            }
            catch (\Exception $ex) {
                DB::rollback();
                return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
            }

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function approveItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_2 == 1) {
            $response = [];
            $response['error'] = '';
            $result = DB::table(TABLE_DEALERS_PAYMENT)->where('status', '!=', SYSTEM_STATUS_DELETE)->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            if($result->status!='Pending')
            {
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => __('Already ' . $result->status)]);
            }

            $time = Carbon::now();
            $itemNew['status']='Approved';
            $itemNew['approved_by'] = $this->user->id;
            $itemNew['approved_at'] = $time;

            DB::beginTransaction();
            try {
                DB::table(TABLE_DEALERS_PAYMENT)->where('id', $itemId)->update($itemNew);
                DB::commit();
                return response()->json($response);
            }
            catch (\Exception $ex) {
                DB::rollback();
                return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
            }

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

