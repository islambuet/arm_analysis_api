<?php
namespace App\Http\Controllers\dealer_reports;

use App\Helpers\ConfigurationHelper;
use App\Helpers\TaskHelper;
use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class DealerSalesDealerBonusReportController extends RootController
{
    public $api_url = 'dealer_reports/dealer_sales_dealer_bonus';
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
            $response['pack_sizes'] = DB::table(TABLE_PACK_SIZES.' as ps')
                ->select('ps.id', 'ps.name','ps.variety_id', 'ps.status','ps.value')
                ->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id')
                ->addSelect('varieties.name as variety_name')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->addSelect('crop_types.name as type_name')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('varieties.ordering', 'ASC')
                ->orderBy('ps.ordering', 'ASC')
                ->get();
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
            $response = [];
            $response['error'] ='';
            $items=[];
            $options = $request->input('options');
            $options['dealer_id']=19;
            if(!($options['dealer_id']>0)){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Select Dealer']);
            }

            //$start_date=($options['fiscal_year']).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            //$end_date=($options['fiscal_year']+1).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';

            $start_date=$options['sales_from'];
            $end_date=$options['sales_to'];

            //bonus settings
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
                $item=[];
                $item['quantity_target']=0;
                $item['quantity_sales_gross']=0;
                $item['quantity_sales_cancel']=0;
                $item['quantity_bonus_adjusted']=0;
                $items[$result->id]=$item;
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
            //bonus settings


            //dealer sales start
            $results = DB::table(TABLE_PACK_SIZES)->get();
            $pack_sizes=[];
            foreach($results as $result){
                $pack_sizes[$result->id]=$result;
            }

            $query=DB::table(TABLE_DEALERS_SALES.' as ds');
            $query->select('ds.*');


            if($options['sales_from']){
                //$query->where('sd.sales_at','>=',$options['sales_from'].' 00:00:00');
                $query->whereDate('ds.sales_at','>=',$options['sales_from']);
            }
            if($options['sales_to']){
                //$query->where('sd.sales_at','<=',$options['sales_to'].' 23:59:59');
                $query->whereDate('ds.sales_at','<=',$options['sales_to']);
            }
            $query->where('ds.status', '=', 'Approved');
            $query->where('ds.dealer_id','=',$options['dealer_id']);
            $results=$query->get();

            foreach ($results as $result){
                if($result){
                    if($result->pack_sizes){
                        $Sales_pack_sizes=json_decode($result->pack_sizes);
                        foreach ($Sales_pack_sizes as $pack_size_id=>$quantity) {//in pkt
                            if (is_numeric($quantity)) {
                                if (isset($pack_sizes[$pack_size_id])) {
                                    $variety_id = $pack_sizes[$pack_size_id]->variety_id;
                                    $pack_size_value = $pack_sizes[$pack_size_id]->value;
                                    $bonus_id = 0;
                                    if (isset($varieties_bonus_id[$variety_id])) {
                                        $bonus_id=$varieties_bonus_id[$variety_id];
                                    }
                                    if(isset($items[$bonus_id])){
                                        $items[$bonus_id]['quantity_sales_gross']+=($quantity*$pack_size_value/1000);
                                    }
                                }
                            }
                        }

                    }
                }
            }
            //dealer sales ends
            //dealer sales cancel start

            $query=DB::table(TABLE_DEALERS_SALES_RETURN.' as ds');
            $query->select('ds.*');

            if($options['sales_from']){
                //$query->where('sd.sales_at','>=',$options['sales_from'].' 00:00:00');
                $query->whereDate('ds.sales_return_at','>=',$options['sales_from']);
            }
            if($options['sales_to']){
                //$query->where('sd.sales_at','<=',$options['sales_to'].' 23:59:59');
                $query->whereDate('ds.sales_return_at','<=',$options['sales_to']);
            }
            $query->where('ds.status', '=', 'Approved');
            $query->where('ds.dealer_id','=',$options['dealer_id']);

            $results=$query->get();
            foreach ($results as $result){
                if($result){
                    if($result->pack_sizes){
                        $Sales_pack_sizes=json_decode($result->pack_sizes);
                        foreach ($Sales_pack_sizes as $pack_size_id=>$quantity) {//in pkt
                            if (is_numeric($quantity)) {
                                if (isset($pack_sizes[$pack_size_id])) {
                                    $variety_id = $pack_sizes[$pack_size_id]->variety_id;
                                    $pack_size_value = $pack_sizes[$pack_size_id]->value;
                                    $bonus_id = 0;
                                    if (isset($varieties_bonus_id[$variety_id])) {
                                        $bonus_id=$varieties_bonus_id[$variety_id];
                                    }
                                    if(isset($items[$bonus_id])){
                                        $items[$bonus_id]['quantity_sales_cancel']+=($quantity*$pack_size_value/1000);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            //dealer sales cancel ends

            $response['items']=$items;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

