<?php

require_once __DIR__ . "/../libs/HUEDevice.php";

class HUESensor extends HUEDevice
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('SensorId', 0);
        $this->RegisterPropertyString('Type', '');
        $this->RegisterPropertyString('ModelId', '');
        $this->RegisterPropertyString('UniqueId', '');
    }

    protected function BasePath()
    {
        $id = $this->ReadPropertyInteger('SensorId');
        return "/sensors/$id";
    }
}
