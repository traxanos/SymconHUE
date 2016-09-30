<?php

class HUELight extends IPSModule {

  public function Create() {
    parent::Create();
    $this->RegisterPropertyInteger("LightId", 0);
    $this->RegisterPropertyString("Type", "");
    $this->RegisterPropertyInteger("LightFeatures", 0); // 0=HUE+CT, 1=HUE, 2=CT, 3=BRI
    $this->RegisterPropertyString("ModelId", "");
    $this->RegisterPropertyString("UniqueId", "");

    if (!IPS_VariableProfileExists('ColorModeSelect.Hue')) IPS_CreateVariableProfile('ColorModeSelect.Hue', 1);
    IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 0, 'Farbe', '', 0x000000);
    IPS_SetVariableProfileAssociation('ColorModeSelect.Hue', 1, 'Farbtemperatur', '', 0x000000);
  }

  public function ApplyChanges() {
    parent::ApplyChanges();
    $this->ConnectParent("{9C6FB2C8-0155-4A59-97A7-2F6D62608908}");
  }

  protected function GetBridge() {
    $instance = IPS_GetInstance($this->InstanceID);
    return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
  }

  /*
   * HUE_RequestData($lightId)
   * Abgleich des Status einer Lampe (HUE_SyncStates sollte bevorzugewerden,
   * da direkt alle Lampen abgeglichen werden mit nur 1 Request zur HUE Bridge)
   */
  public function RequestData() {
    $lightId = $this->ReadPropertyInteger("LightId");
    $light = HUE_Request($this->GetBridge(), "/lights/$lightId", null);
    if(is_array($light) && @$light[0]->error) {
      $error = @$light[0]->error->description;
      $this->SetStatus(202);
      IPS_LogMessage("SymconHUE", "Es ist ein Fehler aufgetreten: $error");
    } else {
      $this->ApplyData($light);
    }
  }

  public function ApplyData($data) {
    $data = (array)$data;
    $state = (array)@$data['state'];

    // Status
    if ($this->ReadPropertyString("UniqueId") == '') {
      $this->SetStatus(104);
      return false;
    } elseif ($state['reachable']) {
      $this->SetStatus(102);
    } else {
      $this->SetStatus(201);
    }

    /*
     * Properties
     */

    $dirty = false;

    $modelid = utf8_decode((string)$data['modelid']);
    $type = utf8_decode((string)$data['type']);
    $name = utf8_decode((string)$data['name']);
    if (IPS_GetProperty($this->InstanceID, 'ModelId') != $modelid) {
      IPS_SetProperty($this->InstanceID, 'ModelId', $modelid);
      $dirty = true;
    }
    if (IPS_GetProperty($this->InstanceID, 'Type') != $type) {
      IPS_SetProperty($this->InstanceID, 'Type', $type);
      $dirty = true;
    }
    if (IPS_GetName($this->InstanceID) != $name) {
      IPS_SetName($this->InstanceID, $name);
      $dirty = true;
    }

    // Setze den Modus
    if (isset($state['ct']) && isset($state['hue'])) {
      // HUE+CT Lamp
      $lightFeature = 0;
    } elseif(isset($state['hue'])) {
      // HUE Lamp
      $lightFeature = 1;
    } elseif(isset($state['ct'])) {
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

    if ($dirty) IPS_ApplyChanges($this->InstanceID);

    /*
     * Variables
     */

    $stateId = $this->RegisterVariableBoolean("STATE", "Zustand", "~Switch");
    $this->EnableAction("STATE");
    IPS_SetPosition($stateId, 1);

    $cmId = $this->RegisterVariableInteger("COLOR_MODE", "Modus", "ColorModeSelect.Hue");
    $this->EnableAction("COLOR_MODE");
    IPS_SetPosition($cmId, 2);
    IPS_SetIcon($cmId, 'ArrowRight');

    $briId = $this->RegisterVariableInteger("BRIGHTNESS", "Helligkeit", "~Intensity.100");
    $this->EnableAction("BRIGHTNESS");
    IPS_SetIcon($briId, 'Sun');
    IPS_SetPosition($briId, 5);

    if ($lightFeature == 0 || $lightFeature == 1) {
      $hueId = $this->RegisterVariableInteger("HUE", "Hue");
      IPS_SetHidden($hueId, true);
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
      $ctId = $this->RegisterVariableInteger("COLOR_TEMPERATURE", "Farbtemperatur", "~Intensity.100");
      $this->EnableAction("COLOR_TEMPERATURE");
      IPS_SetIcon($ctId, 'Bulb');
      IPS_SetPosition($ctId, 4);
    } else {
      $delete = @IPS_GetObjectIDByIdent("COLOR_TEMPERATURE", $this->InstanceID);
      if ($delete !== false) IPS_DeleteVariable($delete);
    }

    if ($lightFeature == 0 || $lightFeature == 1) {
      $colorId = $this->RegisterVariableInteger("COLOR", "Farbe", "~HexColor");
      $this->EnableAction("COLOR");
      IPS_SetPosition($colorId, 3);
      IPS_SetIcon($colorId, 'Bulb');

      $satId = $this->RegisterVariableInteger("SATURATION", utf8_decode("Sättigung"), "~Intensity.100");
      $this->EnableAction("SATURATION");
      IPS_SetIcon($satId, 'Intensity');
      IPS_SetPosition($satId, 6);

    } else {
      $delete = @IPS_GetObjectIDByIdent("COLOR", $this->InstanceID);
      if ($delete !== false) IPS_DeleteVariable($delete);
      $delete = @IPS_GetObjectIDByIdent("SATURATION", $this->InstanceID);
      if ($delete !== false) IPS_DeleteVariable($delete);
    }

    /*
     * Values
     */

    if (!$state['reachable']) {
      SetValueBoolean($stateId, false);
    } else {
      SetValueBoolean($stateId, $state['on']);
    }
    SetValueInteger($briId, round($state['bri'] * 100 / 254));
    if (@$satId) SetValueInteger($satId, round($state['sat'] * 100 / 254));
    if (@$hueId) SetValueInteger($hueId, $state['hue']);
    if (@$ctId) SetValueInteger($ctId, 100 - round(( $state['ct'] - 153) * 100 / 347));

    switch (@$state['colormode']) {
      case 'xy':
      case 'hs':
        $hex = $this->HSV2HEX($state['hue'], $state['sat'], $state['bri']);
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
  }

  public function RequestAction($key, $value) {
    switch ($key) {
      case 'STATE':
         $value = $value == 1;
         break;
      case 'COLOR_TEMPERATURE':
        $value = 500 - round(347 * $value / 100);
        break;
      case 'SATURATION':
      case 'BRIGHTNESS':
         $value = round($value * 2.54);
         break;
    }
    $this->SetValue($key, $value);
  }

  /*
   * HUE_GetValue($lightId, $key)
   * Liefert einen Lampenparameter (siehe HUE_SetValue)
   */
  public function GetValue($key) {
    switch ($key) {
      default:
        $value = GetValue(@IPS_GetObjectIDByIdent($key, $this->InstanceID));
        break;
    }
    return $value;
  }

  /*
   * HUE_SetValue($lightId, $key, $value)
   * Anpassung eines Lampenparameter siehe SetValues
   */
  public function SetValue($key, $value) {
    $autoOn = false;
    if (in_array($key,array('COLOR', 'BRIGHTNESS', 'SATURATION')))  $autoOn = true;
    return $this->SetValues(array( $key => $value, 'STATE' => $autoOn ));
  }

  /*
   * HUE_SetValues($lightId, $list)
   * Anpassung mehrere Lampenparameter.
   * array('KEY1' => 'VALUE1', 'KEY2' => 'VALUE2'...)
   *
   * Mögliche Keys:
   *
   * STATE -> true oder false für an/aus
   * COLOR_TEMPERATURE -> Farbtemperatur (153 bis 500)
   * SATURATION -> Sättigung (0 bis 255)
   * BRIGHTNESS -> Helligkeit in (0 bis 255)
   * COLOR -> Farbe als integer
   * ALERT -> Wird durchgereicht
   * EFFECT -> Wird durchgereicht
   * TRANSITIONTIME -> Wird durchgereicht
   *
   */
  public function SetValues($list) {
    $stateId = IPS_GetObjectIDByIdent('STATE', $this->InstanceID);
    $cmId = IPS_GetObjectIDByIdent('COLOR_MODE', $this->InstanceID);
    $ctId = @IPS_GetObjectIDByIdent('COLOR_TEMPERATURE', $this->InstanceID);
    $briId = IPS_GetObjectIDByIdent('BRIGHTNESS', $this->InstanceID);
    $satId = @IPS_GetObjectIDByIdent('SATURATION', $this->InstanceID);
    $hueId = @IPS_GetObjectIDByIdent('HUE', $this->InstanceID);
    $colorId = @IPS_GetObjectIDByIdent('COLOR', $this->InstanceID);

    $stateValue = GetValueBoolean($stateId);
    $cmValue = $cmId ? GetValueInteger($cmId) : 0;
    $ctValue = $ctId ? (500 - round(347 * GetValueInteger($ctId) / 100)) : 0;
    $briValue = round(GetValueInteger($briId)*2.54);
    $satValue = $satId ? round(GetValueInteger($satId)*2.54) : 0;
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
      SetValueInteger($satId, round($satNewValue * 100 / 254));
      $changes['sat'] = $satNewValue;
    }
    if (isset($briNewValue)) {
      SetValueInteger($briId, round($briNewValue * 100 / 254));
      $changes['bri'] = $briNewValue;
    }
    if (isset($ctNewValue)) {
      SetValueInteger($ctId, 100 - round(($ctNewValue - 153) * 100 / 347));
      $changes['ct'] = $ctNewValue;
    }
    if (isset($cmNewValue)) {
      SetValueInteger($cmId, $cmNewValue);
      $changes['colormode'] = $cmNewValue == 1 ? 'ct' : 'hs';
    }

    $lightId = $this->ReadPropertyInteger("LightId");
    return HUE_Request($this->GetBridge(), "/lights/$lightId/state", $changes);
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

    $v = $maxRGB * 254;

    if ($chroma == 0) return array('h' => 0, 's' => 0, 'v' => $v);

    $s = ($chroma / $maxRGB) * 254;

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
    if (!($s >= 0 && $s <= 254)) throw new Exception("s property must be between 0 and 255, but is: ${s}");
    if (!($v >= 0 && $v <= 254)) throw new Exception("v property must be between 0 and 255, but is: ${v}");

    $h = $h * 6 / (21845*3);
    $s = $s / 254;
    $v = $v / 254;

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
