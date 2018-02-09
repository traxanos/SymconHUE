# SymconHUE

SymconHUE ist eine Erweiterung für die Heimautomatisierung IP Symcon. Diese Erweiterung sellt eine Integration der Philips HUE Lampen bereit. Dabei ist zu beachtet, dass dieses Modul einige Konfiguration vornimmt, welche sich nicht ändern bzw. überschreiben lassen. Damit möchte ich sicher stellen, dass die Lampen direkt Out-Off-The-Box funktionieren ohne weitere Einrichtungen vornehmen zu müssen.

## Einrichtung

Die Einrichtung erfolgt über die Modulverwaltung von Symcon. Nach der Installation des Moduls sollte der Dienst neugestartet werden. Jetzt kann eine neue Instanz vom Typ "_Philips Hue Bridge_" angelegt und konfiguriert werden. In der Konfigurationsmaske könnt ihr auch den _User_ direkt anlernen. Dazu drückt ihr **zuerst** den Knopf auf der HUE Bridge und dann den Knopf "User registieren" betätigen. Nach dem drücken des "Gräte abgleichen"-Schalters, wird für jede Lampe eine Instanz in der angegebenen Lampenkategorie bzw. jede Gruppe in der angegebenen Gruppenkategorie angelegt. Ihr müsst somit **nicht** selber für jede Lampe bzw. Gruppe eine Instanz anlegen.

## Einstellungen

* **Host**  _Der Hostname bzw. die IP-Adresse der HUE Bridge_
* **User**  _Ein Benutzerschlüssel (wird per MD5 umgewandelt, wenn es keine MD5-Key ist_
* **Interval**  _In welchem Abstand soll der Status abgeglichen werden_
* **Lampenkategorie**  _In der ausgewählten Kategorie werden die Lampeninstanzen bereit gestellt_
* **Gruppenkategorie**  _In der ausgewählten Kategorie werden die Gruppeninstanzen bereit gestellt_

**Schalter**

* **Lampen abgleichen** _Es werden für jede, an der HUE Bridge angemeldeten Lampe, eine Instanz in der Lampenkategorie angelegt._
* **User registrieren** _Leitet eine Registrierung eines neuen Benutzers ein._
* **Status abgleichen** _Manueller Abgleich des Status aller Lampen_

## Voraussetzung

* Philips HUE Bridge und Lampen.
* ab Symcon Version 4

## Funktionen

	// Abgleich aller Lampen
	HUE_SyncDevices($bridgeId);

	// Abgleich des Status aller Lampen
	HUE_SyncStates($bridgeId);

	// Liefert zu einer UniqueID die passende Lampeninstanz
	HUE_GetDeviceByUniqueId($bridgeId, $uniqueId);

	// Abgleich des Status einer Lampe (HUE_SyncStates sollte bevorzugewerden,
	// da direkt alle Lampen abgeglichen werden mit nur 1 Request zur HUE Bridge)
	HUE_RequestData($lightId);

	// Anpassung eines Lampenparameter (siehe SetValues)
	HUE_SetValue($lightId, $key, $value);

	// Anpassung mehrere Lampenparameter
	// array('KEY1' => 'VALUE1', 'KEY2' => 'VALUE2'...)
	//
	// Mögliche Keys:
	// STATE -> true oder false für an/aus
	// COLOR_TEMPERATURE -> Farbtemperatur (153 bis 500)
	// SATURATION -> Sättigung (0 bis 254)
	// BRIGHTNESS -> Helligkeit in (0 bis 254)
	// COLOR -> Farbe als integer
	// ALERT -> Wird durchgereicht
	// EFFECT -> Wird durchgereicht
	// TRANSITIONTIME -> Wird durchgereicht
	HUE_SetValues($lightId, $list);

	// Liefert einen Lampenparameter (siehe HUE_SetValue)
	HUE_GetValue($lightId, $key);

	// Weitere Helpergunktionen für Direktverknüpfungen
	HUE_SetState($lightId, $value)
	HUE_GetState($lightId)
	HUE_SetColor($lightId, $value)
	HUE_GetColor($lightId)
	HUE_SetBrightness($lightId, $value)
	HUE_GetBrightness($lightId)
