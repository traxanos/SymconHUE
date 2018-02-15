<?php

require_once __DIR__ . "/HUEMisc.php";

abstract class HUEDevice extends IPSModule
{
    protected $timerUpdateAfter = 60;

    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);
    }

    public function Create()
    {
        if (!IPS_VariableProfileExists('ColorModeSelect.Hue')) {
            IPS_CreateVariableProfile('ColorModeSelect.Hue', 1);
        }
        IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 0, $this->Translate('Color'), '', 0x000000);
        IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 1, $this->Translate('Color temperature'), '', 0x000000);
        IPS_SetVariableProfileIcon('ColorModeSelect.Hue', 'ArrowRight');

        if (!IPS_VariableProfileExists('ColorTemperatureSelect.Hue')) {
            IPS_CreateVariableProfile('ColorTemperatureSelect.Hue', 1);
        }
        IPS_SetVariableProfileDigits('ColorTemperatureSelect.Hue', 0);
        IPS_SetVariableProfileIcon('ColorTemperatureSelect.Hue', 'Bulb');
        IPS_SetVariableProfileText('ColorTemperatureSelect.Hue', '', ' Mired');
        IPS_SetVariableProfileValues('ColorTemperatureSelect.Hue', 153, 500, 1);

        if (!IPS_VariableProfileExists('Intensity.Hue')) {
            IPS_CreateVariableProfile('Intensity.Hue', 1);
        }
        IPS_SetVariableProfileDigits('Intensity.Hue', 0);
        IPS_SetVariableProfileIcon('Intensity.Hue', 'Intensity');
        IPS_SetVariableProfileText('Intensity.Hue', '', '%');
        IPS_SetVariableProfileValues('Intensity.Hue', 0, 254, 1);

        parent::Create();
    }

    protected function GetBridge()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    abstract protected function BasePath();

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->ConnectParent("{9C6FB2C8-0155-4A59-97A7-2F6D62608908}");
    }

    public function ApplyData($data)
    {
        $data = (array)$data;
        if (get_class($this) == 'HUEGroup') {
            $values = (array)@$data['action'];
        } elseif (get_class($this) == 'HUESensor') {
            $values = (array)@$data['config'];
            $values_state = (array)@$data['state'];
        } else {
            $values = (array)@$data['state'];
        }

        // Status
        if (get_class($this) == 'HUELight' && $this->ReadPropertyString("UniqueId") == '') {
            $this->SetStatus(104);
            return false;
        } elseif (get_class($this) == 'HUESensor' && $this->ReadPropertyString("UniqueId") == '') {
            $this->SetStatus(104);
            return false;
        } elseif (get_class($this) == 'HUEGroup' || !isset($values['reachable']) || $values['reachable']) {
            $this->SetStatus(102);
        } else {
            $this->SetStatus(201);
        }

        $dirty = false;

        /*
         * Properties
         */

        $name = utf8_decode((string)$data['name']);
        // update name if not the special group all with id -1
        if (IPS_GetName($this->InstanceID) != $name && !(get_class($this) == 'HUEGroup' && $this->ReadPropertyInteger("GroupId") == 0) && !(get_class($this) == 'HUESensor')) {
            IPS_SetName($this->InstanceID, $name);
            $dirty = true;
        }

        if (get_class($this) == 'HUELight') {
            $modelid = utf8_decode((string)$data['modelid']);
            if (IPS_GetProperty($this->InstanceID, 'ModelId') != $modelid) {
                IPS_SetProperty($this->InstanceID, 'ModelId', $modelid);
                $dirty = true;
            }
            $type = utf8_decode((string)$data['type']);
            if (IPS_GetProperty($this->InstanceID, 'Type') != $type) {
                IPS_SetProperty($this->InstanceID, 'Type', $type);
                $dirty = true;
            }
        }

        if (get_class($this) == 'HUESensor') {
            $name = utf8_decode((string)$data['name']);
            $type = utf8_decode((string)$data['type']);
            $modelid = utf8_decode((string)$data['modelid']);
            // We will receive three types of sensors, but only ZLLPresence will contains the correct name, model and type
            if (IPS_GetName($this->InstanceID) != $name && $type == "ZLLPresence") {
                IPS_SetName($this->InstanceID, $name);
                $dirty = true;
            }
            if (IPS_GetProperty($this->InstanceID, 'ModelId') != $modelid && $type == "ZLLPresence") {
                IPS_SetProperty($this->InstanceID, 'ModelId', $modelid);
                $dirty = true;
            }
            if (IPS_GetProperty($this->InstanceID, 'Type') != $type && $type == "ZLLPresence") {
                IPS_SetProperty($this->InstanceID, 'Type', $type);
                $dirty = true;
            }
        }

        if (get_class($this) == 'HUELight' || get_class($this) == 'HUEGroup') {
            // Setze den Modus
            if (!isset($values['bri'])) {
                // Keine Helligkeit, somit keine Licht-Funktionen
                $lightFeature = 4;
            } elseif (isset($values['ct']) && (isset($values['hue']) || in_array(@$values['colormode'], array('hs', 'xy')))) {
                // HUE+CT Lamp
                $lightFeature = 0;
            } elseif (isset($values['hue']) || in_array(@$values['colormode'], array('hs', 'xy'))) {
                // HUE Lamp
                $lightFeature = 1;
            } elseif (isset($values['ct'])) {
                // CT Lamp
                $lightFeature = 2;
            } else {
                // Lux Lamp
                $lightFeature = 3;
            }

            if (IPS_GetProperty($this->InstanceID, 'LightFeatures') != $lightFeature) {
                IPS_SetProperty($this->InstanceID, 'LightFeatures', $lightFeature);
                $dirty = true;
            }
        }

        if ($dirty) {
            IPS_ApplyChanges($this->InstanceID);
        }

        /*
         * Variables
         */

        if (get_class($this) == 'HUELight' || get_class($this) == 'HUEGroup') {
            $this->MaintainVariable("STATE", $this->Translate('State'), 0, "~Switch", 1, true);
            $this->EnableAction("STATE");
            $valuesId = $this->GetIDForIdent("STATE");

            $this->MaintainVariable("COLOR_MODE", $this->Translate('Mode'), 1, "ColorModeSelect.Hue", 2, true);
            $this->EnableAction("COLOR_MODE");
            $cmId = $this->GetIDForIdent("COLOR_MODE");
            IPS_SetHidden($cmId, $lightFeature != 0);

            if ($lightFeature != 4) {
                $this->MaintainVariable("BRIGHTNESS", $this->Translate('Brightness'), 1, "Intensity.Hue", 5, true);
                $this->EnableAction("BRIGHTNESS");
                $briId = $this->GetIDForIdent("BRIGHTNESS");
            } else {
                $this->UnregisterVariable("BRIGHTNESS");
            }

            if ($lightFeature == 0 || $lightFeature == 1) {
                $this->MaintainVariable("HUE", $this->Translate('Hue'), 1, "", 1000, true);
                $hueId = $this->GetIDForIdent("HUE");
                IPS_SetHidden($hueId, true);
            } else {
                $this->UnregisterVariable("HUE");
            }

            if ($lightFeature == 0 || $lightFeature == 2) {
                $this->MaintainVariable("COLOR_TEMPERATURE", $this->Translate('Color temperature'), 1, "ColorTemperatureSelect.Hue", 4, true);
                $this->EnableAction("COLOR_TEMPERATURE");
                $ctId = $this->GetIDForIdent("COLOR_TEMPERATURE");
            } else {
                $this->UnregisterVariable("COLOR_TEMPERATURE");
            }

            if ($lightFeature == 0 || $lightFeature == 1) {
                $this->MaintainVariable("COLOR", $this->Translate('Color'), 1, "~HexColor", 3, true);
                $this->EnableAction("COLOR");
                $colorId = $this->GetIDForIdent("COLOR");
                IPS_SetHidden($colorId, true);

                $this->MaintainVariable("SATURATION", $this->Translate('Saturation'), 1, "Intensity.Hue", 6, true);
                $this->EnableAction("SATURATION");
                $satId = $this->GetIDForIdent("SATURATION");
            } else {
                $this->UnregisterVariable("COLOR");
                $this->UnregisterVariable("SATURATION");
            }
        } elseif (get_class($this) == 'HUESensor') {
            $this->MaintainVariable("PRESENCE", $this->Translate('Presence'), 0, "~Presence", 1, true);
            $presenceId = $this->GetIDForIdent("PRESENCE");

            $this->MaintainVariable("TEMPERATURE", $this->Translate('Temperature'), 2, "~Temperature", 2, true);
            $temperatureId = $this->GetIDForIdent("TEMPERATURE");

            $this->MaintainVariable("ILLUMINATION", $this->Translate('Illumination'), 2, "~Illumination.F", 3, true);
            $illuminationId = $this->GetIDForIdent("ILLUMINATION");

            $this->MaintainVariable("BATTERY", $this->Translate('Battery'), 1, "~Battery.100", 4, true);
            $batteryId = $this->GetIDForIdent("BATTERY");
        }

        if (get_class($this) == 'HUELight' || get_class($this) == 'HUEGroup') {
            if (get_class($this) == 'HUELight' && !$values['reachable']) {
                $this->SetValueBoolean($valuesId, false);
            } else {
                $this->SetValueBoolean($valuesId, $values['on']);
            }

            if (@$briId) {
                $this->SetValueInteger($briId, (int)@$values['bri']);
            }
            if (@$satId && in_array($lightFeature, [0, 1])) {
                $this->SetValueInteger($satId, (int)@$values['sat']);
            }
            if (@$hueId && in_array($lightFeature, [0, 1])) {
                $this->SetValueInteger($hueId, (int)@$values['hue']);
            }
            if (@$ctId && in_array($lightFeature, [0, 2])) {
                $this->SetValueInteger($ctId, (int)@$values['ct']);
            }

            // Fix colormode for non philips hue lamps
            $colormode = @$values['colormode'];
            if (in_array($colormode, ['hs', 'xy']) && in_array($lightFeature, [0, 1])) {
                $colormode = 'hs';
            } elseif ($colormode == 'ct' && in_array($lightFeature, [0, 2])) {
                $colormode = 'ct';
            } else {
                $colormode = '';
            }

            if ($colormode == 'hs' && isset($values['hue']) && isset($values['sat']) && isset($values['bri'])) {
                $hex = HUEMisc::HSV2HEX($values['hue'], $values['sat'], $values['bri']);
                $this->SetValueInteger($colorId, hexdec($hex));
                IPS_SetHidden($colorId, false);
                IPS_SetHidden($satId, false);
                if (@$ctId) {
                    IPS_SetHidden($ctId, true);
                }
                if (@$cmId) {
                    $this->SetValueInteger($cmId, 0);
                }
            } elseif ($colormode == 'ct') {
                if (@$colorId) {
                    IPS_SetHidden($colorId, true);
                }
                if (@$satId) {
                    IPS_SetHidden($satId, true);
                }
                IPS_SetHidden($ctId, false);
                $this->SetValueInteger($cmId, 1);
            }
        } elseif (get_class($this) == 'HUESensor') {
            if (@$presenceId && isset($values_state['presence'])) {
                $this->SetValueBoolean($presenceId, $values_state['presence']);
                if (@$batteryId) {
                    $this->SetValueInteger($batteryId, $values['battery']);
                } // only update battery from presence
            }
            if (@$illuminationId && isset($values_state['lightlevel'])) {
                $this->SetValueFloat($illuminationId, $values_state['lightlevel']);
            }
            if (@$temperatureId && isset($values_state['temperature'])) {
                $this->SetValueFloat($temperatureId, ($values_state['temperature']/100));
            }
        }
    }

    /*
     * HUE_RequestData(int $id)
     * Abgleich des Status einer Lampe oder Gruppe (HUE_SyncStates sollte bevorzugewerden,
     * da direkt alle Lampen abgeglichen werden mit nur 1 Request zur HUE Bridge)
     */
    public function RequestData()
    {
        $data = HUE_Request($this->GetBridge(), $this->BasePath(), null);
        if (is_array($data) && @$data[0]->error) {
            $error = @$data[0]->error->description;
            $this->SetStatus(202);
            IPS_LogMessage("SymconHUE", "Es ist ein Fehler aufgetreten: $error");
        } else {
            $this->ApplyData($data);
        }
    }

    public function RequestAction($key, $value)
    {
        switch ($key) {
        case 'STATE':
        case 'PRESENCE':
            $value = $value == 1;
            break;
        case 'HUE':
        case 'COLOR_TEMPERATURE':
        case 'SATURATION':
        case 'BRIGHTNESS':
        case 'TEMPERATURE':
        case 'ILLUMINATION':
        case 'BATTERY':
            $value = $value;
            break;
        }
        $this->SetValue($key, $value);
    }

    /*
     * HUE_GetValue(int $id, string $key)
     * Liefert einen Lampenparameter (siehe HUE_SetValue)
     */
    public function GetValue(string $key)
    {
        switch ($key) {
        default:
            $value = GetValue(@IPS_GetObjectIDByIdent($key, $this->InstanceID));
            break;
        }
        return $value;
    }

    /*
     * HUE_SetValue(int $id, string $key, $value)
     * Anpassung eines Lampenparameter siehe SetValues
     */
    public function SetValue($key, $value)
    {
        return $this->SetValues(array($key => $value));
    }

    /*
     * HUE_SetState(int $id, bool $value)
     */
    public function SetState(bool $value)
    {
        return $this->SetValue('STATE', $value);
    }

    /*
     * HUE_GetState(int $id)
     */
    public function GetState()
    {
        return $this->GetValue('STATE');
    }

    /*
     * HUE_SetColor(int $id, int $value)
     */
    public function SetColor(int $value)
    {
        return $this->SetValue('COLOR', $value);
    }

    /*
     * HUE_GetColor(int $id)
     */
    public function GetColor()
    {
        return $this->GetValue('COLOR');
    }

    /*
     * HUE_SetBrightness(int $id, int $value)
     */
    public function SetBrightness(int $value)
    {
        return $this->SetValue('BRIGHTNESS', $value);
    }

    /*
     * HUE_GetBrightness(int $id)
     */
    public function GetBrightness()
    {
        return $this->GetValue('BRIGHTNESS');
    }

    /*
     * HUE_SetValues($lightId, $list)
     * Anpassung mehrere Lampenparameter.
     * array('KEY1' => 'VALUE1', 'KEY2' => 'VALUE2'...)
     *
     * Mögliche Keys:
     *
     * STATE -> true oder false für an/aus
     * COLOR_TEMPERATURE -> Farbtemperatur in mirek (153 bis 500)
     * SATURATION -> Sättigung (0 bis 254)
     * BRIGHTNESS -> Helligkeit in (0 bis 254)
     * HUE -> HUE Farbe in (0 bis 65535)
     * COLOR -> Farbe als integer
     * ALERT -> Wird durchgereicht
     * EFFECT -> Wird durchgereicht
     * TRANSITIONTIME -> Wird durchgereicht
     *
     */
    public function SetValues(array $list)
    {
        $stateId = IPS_GetObjectIDByIdent('STATE', $this->InstanceID);
        $cmId = IPS_GetObjectIDByIdent('COLOR_MODE', $this->InstanceID);
        $ctId = @IPS_GetObjectIDByIdent('COLOR_TEMPERATURE', $this->InstanceID);
        $briId = @IPS_GetObjectIDByIdent('BRIGHTNESS', $this->InstanceID);
        $satId = @IPS_GetObjectIDByIdent('SATURATION', $this->InstanceID);
        $hueId = @IPS_GetObjectIDByIdent('HUE', $this->InstanceID);
        $colorId = @IPS_GetObjectIDByIdent('COLOR', $this->InstanceID);
        $stateValue = GetValueBoolean($stateId);
        $cmValue = $cmId ? GetValueInteger($cmId) : 0;
        $ctValue = $ctId ? GetValueInteger($ctId) : 0;
        $briValue = $briId ? GetValueInteger($briId) : 0;
        $satValue = $satId ? GetValueInteger($satId) : 0;
        $hueValue = $hueId ? GetValueInteger($hueId) : 0;
        $colorValue = $colorId ? GetValueInteger($colorId) : 0;

        foreach ($list as $key => $value) {
            switch ($key) {
            case 'STATE':
                $stateNewValue = $value;
                break;
            case 'EFFECT':
                $effect = $value;
                break;
            case 'TRANSITIONTIME':
                $transitiontime = $value;
                break;
            case 'ALERT':
                $alert = $value;
                break;
            case 'HUE':
                $stateNewValue = true;
                $hueNewValue = $value;
                $newHex = HUEMisc::HSV2HEX($hueNewValue, $satValue, $briValue);
                if (isset($colorId)) { $this->SetValueInteger($colorId, hexdec($newHex));
                }
                break;
            case 'COLOR':
                $stateNewValue = true;
                $colorNewValue = $value;
                $hex = str_pad(dechex($value), 6, 0, STR_PAD_LEFT);
                $hsv = HUEMisc::HEX2HSV($hex);
                if (isset($colorId)) { $this->SetValueInteger($colorId, hexdec($value));
                }
                $hueNewValue = $hsv['h'];
                $briNewValue = $hsv['v'];
                $satNewValue = $hsv['s'];
                $cmNewValue = 0;
                break;
            case 'BRIGHTNESS':
                $briNewValue = $value;
                if (IPS_GetProperty($this->InstanceID, 'LightFeatures') != 3) {
                    if ($cmValue == '0') {
                        $newHex = HUEMisc::HSV2HEX($hueValue, $satValue, $briNewValue);
                        if (isset($colorId)) { $this->SetValueInteger($colorId, hexdec($newHex));
                        }
                        $hueNewValue = $hueValue;
                        $satNewValue = $satValue;
                    } else {
                        $ctNewValue = $ctValue;
                    }
                }
                $stateNewValue = ($briNewValue > 0);
                break;
            case 'SATURATION':
                $stateNewValue = true;
                $cmNewValue = 0;
                $satNewValue = $value;
                $newHex = HUEMisc::HSV2HEX($hueValue, $satNewValue, $briValue);
                if (isset($colorId)) { $this->SetValueInteger($colorId, hexdec($newHex));
                }
                $hueNewValue = $hueValue;
                $briNewValue = $briValue;
                break;
            case 'COLOR_TEMPERATURE':
                $stateNewValue = true;
                $cmNewValue = 1;
                $ctNewValue = $value;
                $briNewValue = $briValue;
                break;
            case 'COLOR_MODE':
                $stateNewValue = true;
                $cmNewValue = $value;
                if ($cmNewValue == 1) {
                    $ctNewValue = $ctValue;
                    IPS_SetHidden($colorId, true);
                    IPS_SetHidden($ctId, false);
                    IPS_SetHidden($satId, true);
                } else {
                    $hueNewValue = $hueValue;
                    $satNewValue = $satValue;
                    $briNewValue = $briValue;
                    $newHex = HUEMisc::HSV2HEX($hueValue, $satValue, $briValue);
                    $this->SetValueInteger($colorId, hexdec($newHex));
                    IPS_SetHidden($colorId, false);
                    IPS_SetHidden($ctId, true);
                    IPS_SetHidden($satId, false);
                }
                break;
            }
        }

        $changes = array();
        if (isset($effect)) {
            $changes['effect'] = $effect;
        }
        if (isset($alert)) {
            $changes['alert'] = $alert;
        }
        if (isset($transitiontime)) {
            $changes['transitiontime'] = $transitiontime;
        }
        if (isset($stateNewValue)) {
            $this->SetValueBoolean($stateId, $stateNewValue);
            $changes['on'] = $stateNewValue;
        }
        if (isset($hueNewValue)) {
            $this->SetValueInteger($hueId, $hueNewValue);
            $changes['hue'] = $hueNewValue;
        }
        if (isset($satNewValue)) {
            $this->SetValueInteger($satId, $satNewValue);
            $changes['sat'] = $satNewValue;
        }
        if (isset($briNewValue)) {
            $this->SetValueInteger($briId, $briNewValue);
            $changes['bri'] = $briNewValue;
        }
        if (isset($ctNewValue)) {
            $this->SetValueInteger($ctId, $ctNewValue);
            $changes['ct'] = $ctNewValue;
        }
        if (isset($cmNewValue)) {
            $this->SetValueInteger($cmId, $cmNewValue);
            //$changes['colormode'] = $cmNewValue == 1 ? 'ct' : 'hs';
        }

        if (get_class($this) == 'HUEGroup') {
            $path = $this->BasePath() . "/action";
        } elseif (get_class($this) == 'HUELight') {
            $path = $this->BasePath() . "/state";
        }

        //    print_r($changes);
        return HUE_Request($this->GetBridge(), $path, $changes);
    }

    protected function SetValueBoolean($InstanceId, $value)
    {
        $info = IPS_GetVariable($InstanceId);
        $updated = $info['VariableUpdated'];
        $last = GetValueBoolean($InstanceId);
        if ($last != $value || (time() - $this->timerUpdateAfter) >= $updated) {
            return SetValueBoolean($InstanceId, $value);
        }
    }

    protected function SetValueInteger($InstanceId, $value)
    {
        $info = IPS_GetVariable($InstanceId);
        $updated = $info['VariableUpdated'];
        $last = GetValueInteger($InstanceId);
        if ($last != $value || (time() - $this->timerUpdateAfter) >= $updated) {
            return SetValueInteger($InstanceId, $value);
        }
    }

    protected function SetValueFloat($InstanceId, $value)
    {
        $info = IPS_GetVariable($InstanceId);
        $updated = $info['VariableUpdated'];
        $last = GetValueFloat($InstanceId);
        if ($last != $value || (time() - $this->timerUpdateAfter) >= $updated) {
            return SetValueFloat($InstanceId, $value);
        }
    }

}
