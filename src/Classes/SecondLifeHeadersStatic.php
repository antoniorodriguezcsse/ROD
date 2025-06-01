<?php
namespace Fallen\SecondLife\Classes;

class SecondLifeHeadersStatic {

    public static function getHeader($headerName) {
        return isset($_SERVER[$headerName]) ? $_SERVER[$headerName] : 'unknown';
    }

    public static function getRegionName() {
        $regionData = self::getHeader('HTTP_X_SECONDLIFE_REGION');
        if ($regionData !== 'unknown') {
            $regionTmp = explode('(', $regionData);
            return trim($regionTmp[0]);
        } else {
            return 'unknown';
        }
    }

    public static function getRegionPosition() {
        $regionData = self::getHeader('HTTP_X_SECONDLIFE_REGION');
        if ($regionData !== 'unknown') {
            $regionTmp = explode('(', $regionData);
            if (isset($regionTmp[1])) {
                return explode(')', $regionTmp[1])[0];
            }
        }
        return 'unknown';
    }

    public static function getOwnerKey() {
        return self::getHeader('HTTP_X_SECONDLIFE_OWNER_KEY');
    }

    public static function getOwnerName() {
        return self::getHeader('HTTP_X_SECONDLIFE_OWNER_NAME');
    }

    public static function getParcelID() {
        return self::getHeader('HTTP_X_SECONDLIFE_PARCEL_ID');
    }

    // Add any additional static methods for other headers as needed
}
?>