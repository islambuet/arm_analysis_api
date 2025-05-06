<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
class CommonHelper
{
    public static function generate_rnd_code($variety,$year): string
    {
        $code=$variety->crop_code.'-'.$variety->crop_type_code;
        if($variety->rnd_ordering>0){
            $code.='-'.str_pad($variety->rnd_ordering,2, '0',STR_PAD_LEFT);

            if($variety->whose=='ARM'){
                $code.='-ARM';
            }
            else if($variety->whose=='Principal'){
                $code.='-P'.$variety->principal_info->code;
            }
            else if($variety->whose=='Competitor'){
                $code.='-C'.$variety->competitor_info->code;
            }
            else{
                $code.='-XXX';
            }
            $code.='-'.substr($year,-2);

        }
        else{
            $code='';
        }
        return $code;
    }
    public static function get_display_rnd_code($code,$permissions): string{
        if ($permissions->action_7 == 1) {
            return $code;
        }
        else{
            $pos3=-1;
            for($i=0;$i<3;$i++){
                $pos3=strpos($code,'-',$pos3+1);
            }
            return  substr($code,0,$pos3).substr($code,strpos($code,'-',$pos3+1));
        }

    }
}
