<?php
namespace App\Http\Controllers\product_bonus_dealer;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class EligibleListController extends RootController
{
    public $api_url = 'product_bonus_dealer/eligible_list';
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

            $response['crops'] = DB::table(TABLE_CROPS)
                ->select('id', 'name', 'status')
                ->orderBy('ordering', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES.' as crop_types')
                ->select('crop_types.*')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crops.id', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('crop_types.id', 'ASC')
                ->get();
            $response['lastGeneratedDate'] = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE)
                ->orderBy('id', 'DESC')
                ->first();
            $response['bonus_setup'] = DB::table(TABLE_DEALER_PRODUCT_BONUS_SETUP)
                ->select('id', 'crop_id', 'crop_type_ids', 'quantity')
                ->orderBy('ordering', 'ASC')
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
            $lastGeneratedDate = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE)->orderBy('id', 'DESC')->first();

            $response=[];
            $response['error'] = '';
            $perPage=$request->input('perPage',2);
            $options = $request->input('options');
            $query=DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS.' as pbgb');
            $query->select('pbgb.*');
            $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'pbgb.dealer_id');
            $query->addSelect('dealers.name as dealer_name');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
            $query->addSelect('d.name as distributor_name','d.id as distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name','territories.id as territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name','areas.id as area_id');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name','parts.id as part_id');
            $query->orderBy('parts.name', 'ASC');
            $query->orderBy('areas.name', 'ASC');
            $query->orderBy('territories.name', 'ASC');
            $query->orderBy('dealers.name', 'ASC');

            $query->where('pbgb.generated_date_id', $lastGeneratedDate->id);


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


            $results = $query->get();
            $response['items'] = $results;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request, $combineIds): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response= [];
            $response['error'] = '';
            $temp= explode('_', $combineIds);
            $itemId=0;
            $bonus_id=0;
            if(isset($temp[0])){
                $itemId=$temp[0];
            }
            if(isset($temp[1])){
                $bonus_id=$temp[1];
            }
            $result = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            $bonus_array=json_decode($result->bonus_data,true);
            if(isset($bonus_array[$bonus_id]))
            {
                $result->bonus_data=$bonus_array[$bonus_id];
            }
            else{
                $result->bonus_data=(object)[];
            }
            $delivery_array=json_decode($result->delivery_data,true);
            if(isset($delivery_array[$bonus_id]))
            {
                $result->delivery_data=$delivery_array[$bonus_id];
            }
            else{
                $result->delivery_data=(object)[];
            }
            $response['item']=$result;
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
        }
    }
    public function saveItem(Request $request): JsonResponse
    {
        $combineIds = $request->input('id', 0);
        $combineIds = $request->input('id', 0);
        $temp= explode('_', $combineIds);
        $itemId=0;
        $bonus_id=0;
        if(isset($temp[0])){
            $itemId=$temp[0];
        }
        if(isset($temp[1])){
            $bonus_id=$temp[1];
        }
        $result = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)->find($itemId);
        if (!$result) {
            return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
        }
        $bonus_array=json_decode($result->bonus_data,true);
        $delivery_array=json_decode($result->delivery_data,true);
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['quantity'] = ['required'];
        $validation_rule['remarks'] = ['nullable'];

        $itemNew = $request->input('item');


        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        $this->validateInputValues($itemNew, $validation_rule);
        $time = Carbon::now();
        if(isset($bonus_array[$bonus_id]))
        {
            $bonus_array[$bonus_id]['quantity_delivered']+=$itemNew['quantity'];
            $bonus_array[$bonus_id]['quantity_num_delivered']+=1;
            $bonus_array[$bonus_id]['quantity_balance_new']-=$itemNew['quantity'];

            $delivery_array[$bonus_id][]=[
                'quantity'=>$itemNew['quantity'],
                'remarks'=>$itemNew['remarks'],
                'created_by' => $this->user->id,
                'created_at' => $time
            ];
            //Input validation ends
            DB::beginTransaction();
            try {
                DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)->where('id', $itemId)->update(
                    [
                        'delivery_data'=>json_encode($delivery_array),
                        'bonus_data'=>json_encode($bonus_array)
                    ]
                );
                $this->updateSaveToken();
                DB::commit();

                return response()->json(['error' => '', 'messages' => 'Data Updated Successfully']);
            } catch (\Exception $ex) {
                DB::rollback();
                return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
            }
        }
        else{
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Bonus Id not found']);
        }
    }
    public function deleteItem(Request $request, $combineIds): JsonResponse
    {
        $temp= explode('_', $combineIds);
        $itemId=0;
        $bonus_id=0;
        $delete_index=-1;
        if(isset($temp[0])){
            $itemId=$temp[0];
        }
        if(isset($temp[1])){
            $bonus_id=$temp[1];
        }
        if(isset($temp[2])){
            $delete_index=$temp[2];
        }

        $result = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)->find($itemId);
        if (!$result) {
            return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
        }
        $bonus_array=json_decode($result->bonus_data,true);
        $delivery_array=json_decode($result->delivery_data,true);

        if(isset($delivery_array[$bonus_id]))
        {
            if(isset($delivery_array[$bonus_id][$delete_index]))
            {
                $delivery_datum=$delivery_array[$bonus_id][$delete_index];
                if(isset($bonus_array[$bonus_id]))
                {
                    $bonus_array[$bonus_id]['quantity_delivered']-=$delivery_datum['quantity'];
                    $bonus_array[$bonus_id]['quantity_num_delivered']-=1;
                    $bonus_array[$bonus_id]['quantity_balance_new']+=$delivery_datum['quantity'];

                    unset($delivery_array[$bonus_id][$delete_index]);
                    //Input validation ends
                    DB::beginTransaction();
                    try {
                        DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)->where('id', $itemId)->update(
                            [
                                'delivery_data'=>json_encode($delivery_array),
                                'bonus_data'=>json_encode($bonus_array)
                            ]
                        );
                        $this->updateSaveToken();
                        DB::commit();

                        return response()->json(['error' => '', 'messages' => 'Data Updated Successfully']);
                    } catch (\Exception $ex) {
                        DB::rollback();
                        return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
                    }
                }
                else{
                    return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Bonus Id not found']);
                }

            }
            else{
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Delivery not found']);
            }

        }
        else{
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Delivery Data empty not found']);
        }

    }
}

