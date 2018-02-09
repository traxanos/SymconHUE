<?php

require_once __DIR__ . "/../libs/HUEDevice.php";

class HUELight extends HUEDevice
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('LightId', 0);
        $this->RegisterPropertyString('Type', '');
        $this->RegisterPropertyInteger('LightFeatures', 0); // 0=HUE+CT, 1=HUE, 2=CT, 3=BRI
        $this->RegisterPropertyString('ModelId', '');
        $this->RegisterPropertyString('UniqueId', '');
    }

    protected function BasePath()
    {
        $id = $this->ReadPropertyInteger('LightId');
        return "/lights/$id";
    }
}
