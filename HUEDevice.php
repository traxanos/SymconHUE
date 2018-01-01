<?php

abstract class HUEDevice extends IPSModule {

  public function __construct($InstanceID) {
    parent::__construct($InstanceID);
  }

  public function Create() {
    if (!IPS_VariableProfileExists('ColorModeSelect.Hue')) IPS_CreateVariableProfile('ColorModeSelect.Hue', 1);
    IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 0, 'Farbe', '', 0x000000);
    IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 1, 'Farbtemperatur', '', 0x000000);

    if (!IPS_VariableProfileExists('ColorTemperatureSelect.Hue')) IPS_CreateVariableProfile('ColorTemperatureSelect.Hue', 1);
    IPS_SetVariableProfileDigits('ColorTemperatureSelect.Hue', 0);
    IPS_SetVariableProfileIcon('ColorTemperatureSelect.Hue', 'Intensity');
    IPS_SetVariableProfileText('ColorTemperatureSelect.Hue', '', ' Mired');
    IPS_SetVariableProfileValues('ColorTemperatureSelect.Hue', 153, 500, 1);

    parent::Create();
  }

  protected function GetBridge() {
    $instance = IPS_GetInstance($this->InstanceID);
    return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
  }

  abstract protected function BasePath();

  public function ApplyChanges() {
    parent::ApplyChanges();
    $this->ConnectParent("{9C6FB2C8-0155-4A59-97A7-2F6D62608908}");
  }

  public function ApplyData($data) {
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
      } elseif(isset($values['hue']) || in_array(@$values['colormode'], array('hs', 'xy'))) {
        // HUE Lamp
        $lightFeature = 1;
      } elseif(isset($values['ct'])) {
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

    if ($dirty) IPS_ApplyChanges($this->InstanceID);

    /*
     * Variables
     */

    if (get_class($this) == 'HUELight' || get_class($this) == 'HUEGroup'){
      if (!$valuesId = @$this->GetIDForIdent("STATE")) {
        $valuesId = $this->RegisterVariableBoolean("STATE", "Zustand", "~Switch", 1);
        $this->EnableAction("STATE");
        //IPS_SetPosition($valuesId, 1);
      }

      if (!$cmId = @$this->GetIDForIdent("COLOR_MODE")) {
        $cmId = $this->RegisterVariableInteger("COLOR_MODE", "Modus", "ColorModeSelect.Hue", 2);
        $this->EnableAction("COLOR_MODE");
        //IPS_SetPosition($cmId, 2);
        IPS_SetIcon($cmId, 'ArrowRight');
      }

      if ($lightFeature != 4) {
        if (!$briId = @$this->GetIDForIdent("BRIGHTNESS")) {
          $briId = $this->RegisterVariableInteger("BRIGHTNESS", "Helligkeit", "~Intensity.255", 5);
          $this->EnableAction("BRIGHTNESS");
          IPS_SetIcon($briId, 'Sun');
          //IPS_SetPosition($briId, 5);
        }
      } else {
        $delete = @IPS_GetObjectIDByIdent("BRIGHTNESS", $this->InstanceID);
        if ($delete !== false) IPS_DeleteVariable($delete);
      }

      if ($lightFeature == 0 || $lightFeature == 1) {
        if (!$hueId = @$this->GetIDForIdent("HUE")) {
          $hueId = $this->RegisterVariableInteger("HUE", "Hue");
          IPS_SetHidden($hueId, true);
        }
      } else {
        $delete = @IPS_GetObjectIDByIdent("HUE", $this->InstanceID);
        if ($delete !== false) IPS_DeleteVariable($delete);
      }

      if ($lightFeature == 0) {
        IPS_SetVariableCustomProfile($cmId, 'ColorModeSelect.Hue');
        IPS_SetHidden($cmId, false);
      } else {
        IPS_SetHidden($cmId, true);
      }

      if ($lightFeature == 0 || $lightFeature == 2) {
        if (!$ctId = @$this->GetIDForIdent("COLOR_TEMPERATURE")) {
          $ctId = $this->RegisterVariableInteger("COLOR_TEMPERATURE", "Farbtemperatur", "ColorTemperatureSelect.Hue", 4);
          $this->EnableAction("COLOR_TEMPERATURE");
          IPS_SetIcon($ctId, 'Bulb');
          //IPS_SetPosition($ctId, 4);
        }
      } else {
        $delete = @IPS_GetObjectIDByIdent("COLOR_TEMPERATURE", $this->InstanceID);
        if ($delete !== false) IPS_DeleteVariable($delete);
      }

      if ($lightFeature == 0 || $lightFeature == 1) {
        if (!$colorId = @$this->GetIDForIdent("COLOR")) {
          $colorId = $this->RegisterVariableInteger("COLOR", "Farbe", "~HexColor", 3);
          $this->EnableAction("COLOR");
          //IPS_SetPosition($colorId, 3);
          IPS_SetIcon($colorId, 'Bulb');
        }

        if (!$satId = @$this->GetIDForIdent("SATURATION")) {
          $satId = $this->RegisterVariableInteger("SATURATION", utf8_decode("Sättigung"), "~Intensity.255", 6);
          $this->EnableAction("SATURATION");
          IPS_SetIcon($satId, 'Intensity');
          //IPS_SetPosition($satId, 6);
        }
      } else {
        $delete = @IPS_GetObjectIDByIdent("COLOR", $this->InstanceID);
        if ($delete !== false) IPS_DeleteVariable($delete);
        $delete = @IPS_GetObjectIDByIdent("SATURATION", $this->InstanceID);
        if ($delete !== false) IPS_DeleteVariable($delete);
      }

    } elseif (get_class($this) == 'HUESensor') {
      if (!$presenceId = @$this->GetIDForIdent("PRESENCE")) {
        $presenceId = $this->RegisterVariableBoolean("PRESENCE", "Anwesenheit", "~Presence", 1);
        //$this->EnableAction("PRESENCE");
        IPS_SetIcon($presenceId, 'Motion');
        //IPS_SetPosition($valuesId, 1);
      }

      if (!$temperatureId = @$this->GetIDForIdent("TEMPERATURE")) {
        $temperatureId = $this->RegisterVariableFloat("TEMPERATURE", "Temperatur", "~Temperature", 2);
        //$this->EnableAction("TEMPERATURE");
        IPS_SetIcon($temperatureId, 'Temperature');
        //IPS_SetPosition($valuesId, 1);
      }

      if (!$illuminationId = @$this->GetIDForIdent("ILLUMINATION")) {
        $illuminationId = $this->RegisterVariableFloat("ILLUMINATION", "Erleuchtung", "~Illumination.F", 3);
        //$this->EnableAction("ILLUMINATION");
        IPS_SetIcon($illuminationId, 'Sun');
        //IPS_SetPosition($valuesId, 1);
      }

      if (!$batteryId = @$this->GetIDForIdent("BATTERY")) {
        $batteryId = $this->RegisterVariableInteger("BATTERY", "Batterie", "~Battery.100", 4);
        //$this->EnableAction("BATTERY");
        IPS_SetIcon($batteryId, 'Battery');
        //IPS_SetPosition($valuesId, 1);
      }
    }

    if (get_class($this) == 'HUELight' || get_class($this) == 'HUEGroup'){
      if (get_class($this) == 'HUELight' && !$values['reachable']) {
        SetValueBoolean($valuesId, false);
      } else {
        SetValueBoolean($valuesId, $values['on']);
      }

      if (@$briId) SetValueInteger($briId, (int)@$values['bri']);
      if (@$satId) SetValueInteger($satId, (int)@$values['sat']);
      if (@$hueId) SetValueInteger($hueId, (int)@$values['hue']);
      if (@$ctId) SetValueInteger($ctId, (int)@$values['ct']);

      switch (@$values['colormode']) {
        case 'xy':
        case 'hs':
          $hex = $this->HSV2HEX($values['hue'], $values['sat'], $values['bri']);
          SetValueInteger($colorId, hexdec($hex));
          IPS_SetHidden($colorId, false);
          IPS_SetHidden($satId, false);
          if (@$ctId) IPS_SetHidden($ctId, true);
          if (@$cmId) SetValueInteger($cmId, 0);
          break;
        case 'ct':
          if(@$colorId) IPS_SetHidden($colorId, true);
          if(@$satId) IPS_SetHidden($satId, true);
          IPS_SetHidden($ctId, false);
          SetValueInteger($cmId, 1);
          break;
      }

    } elseif (get_class($this) == 'HUESensor') {
      if (@$presenceId && isset($values_state['presence'])) {
        SetValueBoolean($presenceId, $values_state['presence']);
        if (@$batteryId) SetValueInteger($batteryId, $values['battery']); // only update battery from presence
      }
      if (@$illuminationId && isset($values_state['lightlevel'])) {
        SetValueFloat($illuminationId, $values_state['lightlevel']);
      }
      if (@$temperatureId && isset($values_state['temperature'])) {
        SetValueFloat($temperatureId, ($values_state['temperature']/100));
      }
    }
  }

  /*
   * HUE_RequestData($id)
   * Abgleich des Status einer Lampe oder Gruppe (HUE_SyncStates sollte bevorzugewerden,
   * da direkt alle Lampen abgeglichen werden mit nur 1 Request zur HUE Bridge)
   */
  public function RequestData() {
    $data = HUE_Request($this->GetBridge(), $this->BasePath(), null);
    if(is_array($data) && @$data[0]->error) {
      $error = @$data[0]->error->description;
      $this->SetStatus(202);
      IPS_LogMessage("SymconHUE", "Es ist ein Fehler aufgetreten: $error");
    } else {
      $this->ApplyData($data);
    }
  }

  public function RequestAction($key, $value) {
    switch ($key) {
      case 'STATE':
         $value = $value == 1;
         break;
      case 'COLOR_TEMPERATURE':
        $value = $value;
        break;
      case 'SATURATION':
      case 'BRIGHTNESS':
         $value = $value;
         break;
      case 'PRESENCE':
      	$value = $value == 1;
      	break;
      case 'TEMPERATURE':
      case 'ILLUMINATION':
      case 'BATTERY':
      	$value = $value;
      	break;
    }
    $this->SetValue($key, $value);
  }

  /*
   * HUE_GetValue($id, $key)
   * Liefert einen Lampenparameter (siehe HUE_SetValue)
   */
  public function GetValue(string $key) {
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
  public function SetValue($key, $value) {
    $list = array($key => $value);
    if (in_array($key,array('COLOR', 'BRIGHTNESS', 'SATURATION'))) $list['STATE'] = true;
    return $this->SetValues($list);
  }

  /*
   * HUE_SetState(int $id, bool $value)
   */
  public function SetState(bool $value) {
    return $this->SetValues(array('STATE' => $value));
  }

  /*
   * HUE_GetState(int $id)
   */
  public function GetState() {
    return $this->GetValue('STATE');
  }

  /*
   * HUE_SetColor(int $id, int $value)
   */
  public function SetColor(int $value) {
    return $this->SetValues(array('STATE' => true, 'COLOR_MODE' => 0, 'COLOR' => $value));
  }

  /*
   * HUE_GetColor(int $id)
   */
  public function GetColor() {
    return $this->GetValue('COLOR');
  }

  /*
   * HUE_SetBrightness(int $id, int $value)
   */
  public function SetBrightness(int $value) {
    return $this->SetValues(array('STATE' => true, 'BRIGHTNESS' => $value));
  }

  /*
   * HUE_GetBrightness(int $id)
   */
  public function GetBrightness() {
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
   * SATURATION -> Sättigung (0 bis 255)
   * BRIGHTNESS -> Helligkeit in (0 bis 255)
   * COLOR -> Farbe als integer
   * ALERT -> Wird durchgereicht
   * EFFECT -> Wird durchgereicht
   * TRANSITIONTIME -> Wird durchgereicht
   *
   */
  public function SetValues(array $list) {
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
        case 'COLOR':
          $colorNewValue = $value;
          $hex = str_pad(dechex($value), 6, 0, STR_PAD_LEFT);
          $hsv = $this->HEX2HSV($hex);
          SetValueInteger($colorId, $value);
          $hueNewValue = $hsv['h'];
          $briNewValue = $hsv['v'];
          $satNewValue = $hsv['s'];
          $cmNewValue = 0;
          break;
        case 'BRIGHTNESS':
          $briNewValue = $value;
          if (IPS_GetProperty($this->InstanceID, 'LightFeatures') != 3) {
            if ($cmValue == '0') {
              $newHex = $this->HSV2HEX($hueValue, $satValue, $briNewValue);
              SetValueInteger($colorId, hexdec($newHex));
              $hueNewValue = $hueValue;
              $satNewValue = $satValue;
            } else {
              $ctNewValue = $ctValue;
            }
          }
          break;
        case 'SATURATION':
          $cmNewValue = 0;
          $satNewValue = $value;
          $newHex = $this->HSV2HEX($hueValue, $satNewValue, $briValue);
          SetValueInteger($colorId, hexdec($newHex));
          $hueNewValue = $hueValue;
          $briNewValue = $briValue;
          break;
        case 'COLOR_TEMPERATURE':
          $cmNewValue = 1;
          $ctNewValue = $value;
          $briNewValue = $briValue;
          break;
        case 'COLOR_MODE':
          $cmNewValue = $value;
          $stateNewValue = true;
          if ($cmNewValue == 1) {
            $ctNewValue = $ctValue;
            IPS_SetHidden($colorId, true);
            IPS_SetHidden($ctId, false);
            IPS_SetHidden($satId, true);
          } else {
            $hueNewValue = $hueValue;
            $satNewValue = $satValue;
            $briNewValue = $briValue;
            $newHex = $this->HSV2HEX($hueValue, $satValue, $briValue);
            SetValueInteger($colorId, hexdec($newHex));
            IPS_SetHidden($colorId, false);
            IPS_SetHidden($ctId, true);
            IPS_SetHidden($satId, false);
          }
          break;
      }
    }

    $changes = array();
    if(isset($effect)) {
      $changes['effect'] = $effect;
    }
    if(isset($alert)) {
      $changes['alert'] = $alert;
    }
    if(isset($transitiontime)) {
      $changes['transitiontime'] = $transitiontime;
    }
    if (isset($stateNewValue)) {
      SetValueBoolean($stateId, $stateNewValue);
      $changes['on'] = $stateNewValue;
    }
    if (isset($hueNewValue)) {
      SetValueInteger($hueId, $hueNewValue);
      $changes['hue'] = $hueNewValue;
    }
    if (isset($satNewValue)) {
      SetValueInteger($satId, $satNewValue);
      $changes['sat'] = $satNewValue;
    }
    if (isset($briNewValue)) {
      SetValueInteger($briId, $briNewValue);
      $changes['bri'] = $briNewValue;
    }
    if (isset($ctNewValue)) {
      SetValueInteger($ctId, $ctNewValue);
      $changes['ct'] = $ctNewValue;
    }
    if (isset($cmNewValue)) {
      SetValueInteger($cmId, $cmNewValue);
      //$changes['colormode'] = $cmNewValue == 1 ? 'ct' : 'hs';
    }

    if (get_class($this) == 'HUEGroup') {
      $path = $this->BasePath() . "/action";
    } else if (get_class($this) == 'HUELight') {
      $path = $this->BasePath() . "/state";
    }

//    print_r($changes);
    return HUE_Request($this->GetBridge(), $path, $changes);
  }

  protected function HEX2HSV($h) {
    $r = substr($h, 0, 2);
    $g = substr($h, 2, 2);
    $b = substr($h, 4, 2);
    return $this->RGB2HSV(hexdec($r), hexdec($g), hexdec($b));
  }

  protected function HSV2HEX($h, $s, $v) {
    $rgb = $this->HSV2RGB($h, $s, $v);
    $r = str_pad(dechex($rgb['r']), 2, 0, STR_PAD_LEFT);
    $g = str_pad(dechex($rgb['g']), 2, 0, STR_PAD_LEFT);
    $b = str_pad(dechex($rgb['b']), 2, 0, STR_PAD_LEFT);
    return $r.$g.$b;
  }

  protected function RGB2HSV($r, $g, $b) {
    if (!($r >= 0 && $r <= 255)) throw new Exception("h property must be between 0 and 255, but is: ${r}");
    if (!($g >= 0 && $g <= 255)) throw new Exception("s property must be between 0 and 255, but is: ${g}");
    if (!($b >= 0 && $b <= 255)) throw new Exception("v property must be between 0 and 255, but is: ${b}");
    $r = ($r / 255);
    $g = ($g / 255);
    $b = ($b / 255);
    $maxRGB = max($r, $g, $b);
    $minRGB = min($r, $g, $b);
    $chroma = $maxRGB - $minRGB;
    $v = $maxRGB * 255;
    if ($chroma == 0) return array('h' => 0, 's' => 0, 'v' => $v);
    $s = ($chroma / $maxRGB) * 255;
    if ($r == $minRGB) {
      $h = 3 - (($g - $b) / $chroma);
    } elseif ($b == $minRGB) {
      $h = 1 - (($r - $g) / $chroma);
    } else {// $g == $minRGB
      $h = 5 - (($b - $r) / $chroma);
    }
    $h = $h / 6 * 65535;
    return array('h' => round($h), 's' => round($s), 'v' => round($v));
  }

  protected function HSV2RGB($h, $s, $v) {
    if (!($h >= 0 && $h <= (21845*3))) throw new Exception("h property must be between 0 and 65535, but is: ${h}");
    if (!($s >= 0 && $s <= 255)) throw new Exception("s property must be between 0 and 254, but is: ${s}");
    if (!($v >= 0 && $v <= 255)) throw new Exception("v property must be between 0 and 254, but is: ${v}");
    $h = $h * 6 / (21845*3);
    $s = $s / 255;
    $v = $v / 255;
    $i = floor($h);
    $f = $h - $i;
    $m = $v * (1 - $s);
    $n = $v * (1 - $s * $f);
    $k = $v * (1 - $s * (1 - $f));
    switch ($i) {
      case 0:
        list($r, $g, $b) = array($v, $k, $m);
        break;
      case 1:
        list($r, $g, $b) = array($n, $v, $m);
        break;
      case 2:
        list($r, $g, $b) = array($m, $v, $k);
        break;
      case 3:
        list($r, $g, $b) = array($m, $n, $v);
        break;
      case 4:
        list($r, $g, $b) = array($k, $m, $v);
        break;
      case 5:
      case 6:
        list($r, $g, $b) = array($v, $m, $n);
        break;
    }
    $r = round($r * 255);
    $g = round($g * 255);
    $b = round($b * 255);
    return array('r' => $r, 'g' => $g, 'b' => $b);
  }
}
