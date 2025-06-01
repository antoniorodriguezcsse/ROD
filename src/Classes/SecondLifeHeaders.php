<?php
namespace Fallen\SecondLife\Classes;


class SecondLifeHeaders {
    private $ownerKey;
    private $ownerName;
    private $regionName;
    private $regionPosition;
    private $parcelID; // New property for the parcel ID

    public function __construct() {
        $this->ownerKey = $this->getHeader('HTTP_X_SECONDLIFE_OWNER_KEY');
        $this->ownerName = $this->getHeader('HTTP_X_SECONDLIFE_OWNER_NAME');
        $this->parcelID = $this->getHeader('HTTP_X_SECONDLIFE_PARCEL_ID'); // Retrieve the parcel ID
        $this->processRegionData($this->getHeader('HTTP_X_SECONDLIFE_REGION'));
    }

    private function getHeader($headerName) {
        return isset($_SERVER[$headerName]) ? $_SERVER[$headerName] : 'unknown';
    }

    private function processRegionData($regionData) {
        if ($regionData !== 'unknown') {
            $regionTmp = explode('(', $regionData);
            $this->regionName = trim($regionTmp[0]);

            if (isset($regionTmp[1])) {
                $this->regionPosition = explode(')', $regionTmp[1])[0];
            } else {
                $this->regionPosition = 'unknown';
            }
        } else {
            $this->regionName = 'unknown';
            $this->regionPosition = 'unknown';
        }
    }

    public function getOwnerKey() {
        return $this->ownerKey;
    }

    public function getOwnerName() {
        return $this->ownerName;
    }

    public function getRegionName() {
        return $this->regionName;
    }

    public function getRegionPosition() {
        return $this->regionPosition;
    }

    // New method to get the parcel ID
    public function getParcelID() {
        return $this->parcelID;
    }
}
?>
