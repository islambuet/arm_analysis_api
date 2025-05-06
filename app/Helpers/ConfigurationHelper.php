<?php
    namespace App\Helpers;
    use Illuminate\Support\Facades\DB;
    class ConfigurationHelper
    {
        public static $config = array();
        public static function load_config()
        {
            $results = DB::table(TABLE_CONFIGURATIONS)->where('status', SYSTEM_STATUS_ACTIVE)->get();
            foreach($results as $result){
                self::$config[$result->purpose]=$result->config_value;
            }
        }
        public static function isApiOffline(): bool
        {
            return isset(self::$config[SYSTEM_CONFIGURATIONS_SITE_OFF_LINE])&&(self::$config[SYSTEM_CONFIGURATIONS_SITE_OFF_LINE]==1);
        }
        public static function isLoginMobileVerificationOn(): bool
        {
            return isset(self::$config[SYSTEM_CONFIGURATIONS_LOGIN_MOBILE_VERIFICATION])&&(self::$config[SYSTEM_CONFIGURATIONS_LOGIN_MOBILE_VERIFICATION]==1);
        }
        public static function getLoginSessionExpireHours():float
        {
            return isset(self::$config[SYSTEM_CONFIGURATIONS_LOGIN_SESSION_EXPIRE_HOURS])?(float)self::$config[SYSTEM_CONFIGURATIONS_LOGIN_SESSION_EXPIRE_HOURS]:1;
        }
        public static function getUploadedImageBaseurl():string
        {
            return isset(self::$config[SYSTEM_CONFIGURATIONS_UPLOADED_IMAGE_BASEURL])?(self::$config[SYSTEM_CONFIGURATIONS_UPLOADED_IMAGE_BASEURL]):'';
        }
        public static function getOtpExpireDuration():float
        {
            return isset(self::$config[SYSTEM_CONFIGURATIONS_OTP_EXPIRE_DURATION])?(float)self::$config[SYSTEM_CONFIGURATIONS_OTP_EXPIRE_DURATION]:300;
        }
        public static function getMobileSmsApiToken():string
        {
            return self::$config[SYSTEM_CONFIGURATIONS_MOBILE_SMS_API_TOKEN] ?? '';
        }
        public static function getCurrentFiscalYearStartingMonth():float
        {
            return isset(self::$config[SYSTEM_CONFIGURATIONS_FISCAL_YEAR_STARTING_MONTH])?(float)self::$config[SYSTEM_CONFIGURATIONS_FISCAL_YEAR_STARTING_MONTH]:6;
        }

    }
