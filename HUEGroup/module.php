<?php

require_once __DIR__ . "/../libs/HUEDevice.php";

class HUEGroup extends HUEDevice
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('GroupId', 0);
        $this->RegisterPropertyInteger('LightFeatures', 0); // 0=HUE+CT, 1=HUE, 2=CT, 3=BRI, 4=Empty
    }

    protected function BasePath()
    {
        $id = $this->ReadPropertyInteger('GroupId');
        if ($id == -1) {
            $id = 0;
        } // special for group zero
        return "/groups/$id";
    }
}
