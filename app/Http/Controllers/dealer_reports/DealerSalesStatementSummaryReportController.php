<?php
namespace App\Http\Controllers\dealer_reports;

use App\Helpers\ConfigurationHelper;
use App\Helpers\TaskHelper;
use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class DealerSalesStatementSummaryReportController extends RootController
{
    public $api_url = 'dealer_reports/dealer_sales_statement_summary';
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
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES.' as crop_types')
                ->select('crop_types.*')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('ordering', 'ASC')
                ->get();
            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->addSelect('crop_types.name as crop_type_name')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name','crop_types.crop_id')
                ->where('varieties.whose', '=', 'ARM')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crops.id', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('crop_types.id', 'ASC')
                ->orderBy('varieties.ordering', 'ASC')
                ->orderBy('varieties.id', 'ASC')
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
            $options = $request->input('options');
            //$start_date=($options['fiscal_year']).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            //$end_date=($options['fiscal_year']+1).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';

            $start_date=$options['sales_from'];
            $end_date=$options['sales_to'];
            //initialize items start
            $items=[];
            $dealers_ids=[];
            $dealers_data=[];
            $query=DB::table(TABLE_DEALERS.' as dealers');
            $query->select('dealers.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
            $query->addSelect('d.name as distributor_name');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name','territories.id as territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name','areas.id as area_id');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name','parts.id as part_id');

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
            if($options['status']!='All'){
                $query->where('dealers.status','=',$options['status']);
            }
            $query->orderBy('parts.name', 'ASC');
            $query->orderBy('areas.name', 'ASC');
            $query->orderBy('territories.name', 'ASC');
            $query->orderBy('d.name', 'ASC');
            $query->orderBy('dealers.name', 'ASC');
            $results=$query->get();
            foreach ($results as $result){
                $item=[];
                $item['part_name']=$result->part_name;
                $item['area_name']=$result->area_name;
                $item['territory_name']=$result->territory_name;
                $item['distributor_name']=$result->distributor_name;
                $item['dealer_name']=$result->name;
                $item['credit_limit']=$result->credit_limit;
                //$item['opening_balance']=$result->opening_balance;
                $item['opening_balance']=0;
                $item['sales_gross']=0;
                $item['sales_canceled']=0;
                $item['payment_total']=0;
                $item['last_invoice_date']='';
                $item['last_invoice_duration']='';
                $item['last_payment_date']='';
                $item['last_payment_duration']='';
                $dealers_data[$result->id]=$item;
                $dealers_ids[]=$result->id;
                //$items[]=$item;
            }
            //initialize items end

            //varieties unit price calculation
            $results = DB::table(TABLE_PACK_SIZES)
                ->select('variety_id')
                ->addSelect(DB::raw('AVG(unit_price_per_kg) as unit_price_per_kg'))
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->groupBy('variety_id')
                ->get();
            $varieties_unit_price_per_kg=[];
            foreach ($results as $result){
                $varieties_unit_price_per_kg[$result->variety_id]=round($result->unit_price_per_kg,2);
            }

            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->select(DB::raw('SUM(quantity) as quantity'),DB::raw('SUM(amount) as amount'));
            $query->addSelect('ps.variety_id as variety_id');
            $query->where('sd.sales_at','>=',$start_date);
            $query->where('sd.sales_at','<',$end_date);
            $query->groupBy('ps.variety_id');
            $results=$query->get();
            foreach ($results as $result){
                if($result->quantity>0){//always true
                    $varieties_unit_price_per_kg[$result->variety_id]=round($result->amount/$result->quantity,3);
                }

            }
            //varieties unit price calculation end


            $results = DB::table(TABLE_PACK_SIZES)->get();
            $pack_sizes=[];
            foreach($results as $result){
                $pack_sizes[$result->id]=$result;
            }

            //sales start
            $query=DB::table(TABLE_DEALERS_SALES.' as ds');
            $query->select('ds.*');

            //$query->where('ds.sales_at','>=',$start_date);
            $query->where('ds.sales_at','<',$end_date);
            $query->where('ds.status', '=', 'Approved');

            $query->whereIn('ds.dealer_id',$dealers_ids);//if array is empty it produces where 0=1
            $results=$query->get();

            foreach ($results as $result){
                if($result){
                    if($result->pack_sizes){
                        $Sales_pack_sizes=json_decode($result->pack_sizes);
                        foreach ($Sales_pack_sizes as $pack_size_id=>$quantity){//in pkt
                            if(is_numeric($quantity)){
                                if(isset($pack_sizes[$pack_size_id]))
                                {
                                    $variety_id=$pack_sizes[$pack_size_id]->variety_id;
                                    $pack_size_value=$pack_sizes[$pack_size_id]->value;
                                    if(isset($varieties_unit_price_per_kg[$variety_id])){
                                        if($result->sales_at>$start_date)
                                        {
                                            $dealers_data[$result->dealer_id]['sales_gross']+=(($quantity*$pack_size_value/1000)*$varieties_unit_price_per_kg[$variety_id]);
                                        }
                                        else{
                                            $dealers_data[$result->dealer_id]['opening_balance']+=(($quantity*$pack_size_value/1000)*$varieties_unit_price_per_kg[$variety_id]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            //sales end

            //sales cancel start
            $query=DB::table(TABLE_DEALERS_SALES_RETURN.' as ds');
            $query->select('ds.*');

           // $query->where('ds.sales_return_at','>=',$start_date);
            $query->where('ds.sales_return_at','<',$end_date);
            $query->where('ds.status', '=', 'Approved');

            $query->whereIn('ds.dealer_id',$dealers_ids);//if array is empty it produces where 0=1
            $results=$query->get();

            foreach ($results as $result){
                if($result){
                    if($result->pack_sizes){
                        $Sales_pack_sizes=json_decode($result->pack_sizes);
                        foreach ($Sales_pack_sizes as $pack_size_id=>$quantity){//in pkt
                            if(is_numeric($quantity)){
                                if(isset($pack_sizes[$pack_size_id])) {
                                    $variety_id=$pack_sizes[$pack_size_id]->variety_id;
                                    $pack_size_value=$pack_sizes[$pack_size_id]->value;
                                    if(isset($varieties_unit_price_per_kg[$variety_id])){
                                        if($result->sales_at>$start_date)
                                        {
                                            $dealers_data[$result->dealer_id]['sales_canceled']+=(($quantity*$pack_size_value/1000)*$varieties_unit_price_per_kg[$variety_id]);
                                        }
                                        else{
                                            $dealers_data[$result->dealer_id]['opening_balance']-=(($quantity*$pack_size_value/1000)*$varieties_unit_price_per_kg[$variety_id]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            //sales cancel end

            //payment start
            $query=DB::table(TABLE_DEALERS_PAYMENT.' as ds');
            $query->select('ds.*');

            // $query->where('ds.sales_return_at','>=',$start_date);
            $query->where('ds.payment_at','<',$end_date);
            $query->where('ds.status', '=', 'Approved');

            $query->whereIn('ds.dealer_id',$dealers_ids);//if array is empty it produces where 0=1
            $results=$query->get();

            foreach ($results as $result){
                if($result->payment_at>$start_date)
                {
                    $dealers_data[$result->dealer_id]['payment_total']+=($result->amount);
                }
                else{
                    $dealers_data[$result->dealer_id]['opening_balance']-=($result->amount);
                }
            }
            //payment end

            //last sales date

            $query=DB::table(TABLE_DEALERS_SALES.' as ds');
            $query->select(DB::raw('MAX(sales_at) as last_invoice_date'));
            $query->addSelect('ds.dealer_id');

            $query->where('ds.status', '=', 'Approved');

            $query->whereIn('ds.dealer_id',$dealers_ids);//if array is empty it produces where 0=1
            $query->groupBy('ds.dealer_id');
            $results=$query->get();
            foreach ($results as $result){
                $item['last_invoice_date']='';
                $item['last_invoice_duration']=-1;
                $last_invoice_date=Carbon::parse($result->last_invoice_date);
                $dealers_data[$result->dealer_id]['last_invoice_date']=$last_invoice_date->format('Y-m-d');
                $dealers_data[$result->dealer_id]['last_invoice_duration']=floor(Carbon::now()->diffInDays($last_invoice_date,true));
            }
            //last sales date end

            //last payment date
            $query=DB::table(TABLE_DEALERS_PAYMENT.' as ds');
            $query->select(DB::raw('MAX(payment_at) as last_payment_date'));
            $query->addSelect('ds.dealer_id');

            $query->where('ds.status', '=', 'Approved');

            $query->whereIn('ds.dealer_id',$dealers_ids);//if array is empty it produces where 0=1
            $query->groupBy('ds.dealer_id');
            $results=$query->get();
            foreach ($results as $result){
                $last_payment_date=Carbon::parse($result->last_payment_date);
                $dealers_data[$result->dealer_id]['last_payment_date']=$last_payment_date->format('Y-m-d');
                $dealers_data[$result->dealer_id]['last_payment_duration']=floor(Carbon::now()->diffInDays($last_payment_date,true));
            }
            //last payment date end


            if(sizeof($dealers_ids)>0)
            {
                foreach ($dealers_ids as $dealers_id){
                    $items[]=$dealers_data[$dealers_id];
                }
            }
            $response['items']=$items;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

