<?php
namespace App\Http\Controllers\product_bonus_dealer;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class GenerateEligibleListController extends RootController
{
    public $api_url = 'product_bonus_dealer/generate_eligible_list';
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
            $response['dealers'] = DB::table(TABLE_DEALERS.' as ds')
                ->select('ds.id', 'ds.name','ds.distributor_id', 'ds.status')
                ->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'ds.distributor_id')
                ->addSelect('d.name as distributor_name','d.id as distributor_id','d.territory_id')
                ->orderBy('ds.name', 'ASC')
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
            $perPage = $request->input('perPage', 50);
            $query=DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE);
            $query->orderBy('id', 'DESC');
            $query->where('status', '!=', SYSTEM_STATUS_DELETE);
            if ($perPage == -1) {
                $perPage = $query->count();
                if($perPage<1){
                    $perPage=50;
                }
            }
            $results = $query->paginate($perPage)->toArray();
            return response()->json(['error'=>'','items'=>$results]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response= [];
            $response['error'] = '';
            $result = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE)->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            $response['item']=$result;

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

            $query->where('pbgb.generated_date_id', $itemId);


            if($options['part_id']>0){
                $query->where('parts.id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                        if($options['dealer_id']>0){
                            $query->where('dealers.id','=',$options['dealer_id']);
                        }
                    }
                }
            }


            $results = $query->get();
            $response['dealer_data'] = $results;

            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
        }
    }
    public function saveItem(Request $request): JsonResponse
    {
        $itemId = $request->input('id', 0);
        //permission checking start
        if ($this->permissions->action_1 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['generated_at'] = ['required'];
        $itemNew = $request->input('item');
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));



        //if itemNew Empty
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Nothing was Changed']);
        }
        $this->validateInputValues($itemNew, $validation_rule);
        $lastGeneratedDate = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE)->orderBy('id', 'DESC')->first();
        if($itemNew['generated_at']<=$lastGeneratedDate->generated_at){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'New Date must Be greater than old generated date']);
        }
        //Input validation ends

         $results= DB::table(TABLE_DEALER_PRODUCT_BONUS_SETUP)
            ->where('status', SYSTEM_STATUS_ACTIVE)
            ->orderBy('id', 'ASC')
            ->get();
        $type_bonus_id=[];
        $product_bonus_setups=[];
        foreach ($results as $result){
            $product_bonus_setups[$result->id]=$result;
            $types=explode(',',$result->crop_type_ids);
            for($i=1;$i<sizeof($types)-1;$i++){
               $type_bonus_id[$types[$i]]=$result->id;
            }
        }
        $results=DB::table(TABLE_VARIETIES.' as varieties')
            ->where('varieties.whose', '=', 'ARM')
            ->where('status', SYSTEM_STATUS_ACTIVE)
            ->get();
        $varieties_bonus_id=[];
        foreach ($results as $result){
            if(isset($type_bonus_id[$result->crop_type_id])){
                $varieties_bonus_id[$result->id]=$type_bonus_id[$result->crop_type_id];
            }
        }
        $dealers = DB::table(TABLE_DEALERS)
            ->select('id')
            ->where('status', SYSTEM_STATUS_ACTIVE)
            ->orderBy('id', 'ASC')
            ->get();
        $dealers_bonus_data=[];
        foreach ($dealers as $dealer){
            $dbd=[];
            foreach ($product_bonus_setups as $pbs_id=>$pbs){
                $dbd[$pbs_id]['quantity_eligible']=$pbs->quantity;
                $dbd[$pbs_id]['quantity_balance_old']=0;
                $dbd[$pbs_id]['quantity_sales']=0;
                $dbd[$pbs_id]['quantity_balance_new']=0;
                $dbd[$pbs_id]['quantity_delivered']=0;
                $dbd[$pbs_id]['quantity_num_delivered']=0;
            }
            $dealers_bonus_data[$dealer->id]=$dbd;
        }
        //get previous balance and update
        $results= DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)
            ->where('status', SYSTEM_STATUS_ACTIVE)
            ->where('generated_date_id', $lastGeneratedDate->id)
            ->get();
        foreach ($results as $result){
            if(isset($dealers_bonus_data[$result->dealer_id]))
            {
                $bonus_data=json_decode($result->bonus_data,true);
                foreach ($bonus_data as $pbs_id=>$pbs){
                    if(isset($product_bonus_setups[$pbs_id])){
                        $dealers_bonus_data[$result->dealer_id][$pbs_id]['quantity_balance_old']=$pbs['quantity_balance_new'];
                        $dealers_bonus_data[$result->dealer_id][$pbs_id]['quantity_balance_new']=$pbs['quantity_balance_new'];
                    }

                }
            }

        }

        //sales start
        $query=DB::table(TABLE_DEALERS_SALES.' as ds');
        $query->select('ds.*');
        $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'ds.dealer_id');

        //if($itemNew['generated_at']<=$lastGeneratedDate->generated_at){
        $query->whereDate('ds.sales_at','>',$lastGeneratedDate->generated_at);
        $query->whereDate('ds.sales_at','<=',$itemNew['generated_at']);
        $query->where('ds.status', '=', SYSTEM_STATUS_ACTIVE);
        $results=$query->get();
        foreach ($results as $result){
            if($result){
                if($result->varieties) {
                    $varieties = json_decode($result->varieties);
                    foreach ($varieties as $variety_id => $quantity) {
                        if (is_numeric($quantity)) {
                            if(isset($varieties_bonus_id[$variety_id])){
                                if(isset($dealers_bonus_data[$result->dealer_id])){//always true. also variety bonus exits
                                    $dealers_bonus_data[$result->dealer_id][$varieties_bonus_id[$variety_id]]['quantity_sales']+=$quantity;
                                    $dealers_bonus_data[$result->dealer_id][$varieties_bonus_id[$variety_id]]['quantity_balance_new']+=$quantity;
                                }
                            }

                        }
                    }
                }
            }
        }
        //sales end
//echo '<pre>';
//print_r($dealers_bonus_data[137]);
//echo '</pre>';
//die();
        DB::beginTransaction();
        try {
            $time = Carbon::now();

            $itemNew['created_by'] = $this->user->id;
            $itemNew['created_at'] = $time;
            $newId = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE)->insertGetId($itemNew);
            foreach ($dealers_bonus_data as $dealer_id=>$dbd){
                $row=[];
                $row['dealer_id']=$dealer_id;
                $row['generated_date_id']=$newId;
                $row['bonus_data']=json_encode($dbd);
                $row['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_BONUS)->insert($row);
            }
            unset($itemNew['updated_by'],$itemNew['created_by'],$itemNew['created_at'],$itemNew['updated_at']);

            $this->updateSaveToken();
            DB::commit();
            $newGeneratedDate = DB::table(TABLE_DEALER_PRODUCT_BONUS_GENERATE_DATE)->orderBy('id', 'DESC')->first();
            return response()->json(['error' => '', 'messages' => 'Data (' . $newId . ')' . ($itemId > 0 ? 'Updated' : 'Created') . ')  Successfully','newGeneratedDate'=>$newGeneratedDate]);
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }

}

