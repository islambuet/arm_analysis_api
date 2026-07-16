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
                        if($options['dealer_id']>0){
                            $query->where('dealers.id','=',$options['dealer_id']);
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
}

