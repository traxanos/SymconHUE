<?php

require_once(__DIR__ . "/../HUEDevice.php");

class HUEGroup extends HUEDevice {

  public function Create() {
    parent::Create();
    $this->RegisterPropertyInteger("GroupId", 0);
    $this->RegisterPropertyInteger("LightFeatures", 0); // 0=HUE+CT, 1=HUE, 2=CT, 3=BRI, 4=Empty

    if (!IPS_VariableProfileExists('ColorModeSelect.Hue')) IPS_CreateVariableProfile('ColorModeSelect.Hue', 1);
    IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 0, 'Farbe', '', 0x000000);
    IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 1, 'Farbtemperatur', '', 0x000000);
  }

  protected function BasePath() {
    $id = $this->ReadPropertyInteger("GroupId");
    return "/groups/$id";
  }

}
