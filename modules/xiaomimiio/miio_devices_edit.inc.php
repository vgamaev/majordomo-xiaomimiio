<?php
/*
* @author <skysilver.da@gmail.com>
* @copyright 2017 Agaphonov Dmitri aka skysilver <skysilver.da@gmail.com> (c)
* @version 0.6
*/

if ($this->owner->name == 'panel') {
	$out['CONTROLPANEL'] = 1;
}

$table_name = 'miio_devices';

$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

if ($this->mode == 'update') {
	
	$this->getConfig();
	$ok = 1;
	
	if ($this->tab == '') {

		global $title;
		$rec['TITLE'] = $title;
		if ($rec['TITLE'] == '') {
			$out['ERR_TITLE'] = 1;
			$ok = 0;
		}

		global $ip;
		$rec['IP'] = $ip;
		if ($rec['IP'] == '') {
			$out['ERR_IP'] = 1;
			$ok = 0;
		}
		
		global $token;
		$rec['TOKEN'] = $token;
		
		global $device_type;
		$rec['DEVICE_TYPE'] = $device_type;

		global $update_period;
		$rec['UPDATE_PERIOD'] = (int)$update_period;
		if ($rec['UPDATE_PERIOD'] > 0) {
			$rec['NEXT_UPDATE'] = date('Y-m-d H:i:s');
		}
			
		$commands = array('online', 'command', 'message');
	}

	if ($ok) {
		if ($rec['ID']) {
			if ($this->config['API_LOG_DEBMES']) DebMes('Save params for device with IP ' . $rec['IP'], 'xiaomimiio');
			SQLUpdate($table_name, $rec);
		} else {
			if ($this->config['API_LOG_DEBMES']) DebMes('Manual add new device with IP ' . $rec['IP'], 'xiaomimiio');
			$rec['ID'] = SQLInsert($table_name, $rec);
		}
		
		$out['OK'] = 1;

		if ($this->tab == '') {
			foreach($commands as $cmd) {
				$cmd_rec = SQLSelectOne("SELECT * FROM miio_commands WHERE DEVICE_ID=" . $rec['ID'] . " AND TITLE = '" . $cmd . "'");
				if (!$cmd_rec['ID']) {
					$cmd_rec = array();
					$cmd_rec['TITLE'] = $cmd;
					$cmd_rec['DEVICE_ID'] = $rec['ID'];
					SQLInsert('miio_commands', $cmd_rec);
				}
			}
			
			$this->processCommand($rec['ID'], 'online', 0);
						
			if ($rec['TOKEN'] != '' && $rec['IP'] != '') $this->requestInfo($rec['ID']);
			sleep(1);
			if ((int)$update_period == 0 && $rec['TOKEN'] != '' && $rec['DEVICE_TYPE'] != '') $this->requestStatus($rec['ID']);
		}
	} else {
		$out['ERR'] = 1;
	}
}

if ($this->tab == 'data') {
	
	$new_id = 0;
	global $delete_id;
	
	if ($delete_id) {
		SQLExec("DELETE FROM miio_commands WHERE ID='" . (int)$delete_id . "'");
	}
	
	$properties = SQLSelect("SELECT * FROM miio_commands WHERE DEVICE_ID='" . $rec['ID'] . "' ORDER BY ID");
	$total = count($properties);
	
	for($i = 0; $i < $total; $i++) {
		if ($properties[$i]['ID'] == $new_id) continue;
		
		if ($this->mode == 'update') {
			
			global ${'linked_object'.$properties[$i]['ID']};
			$properties[$i]['LINKED_OBJECT'] = trim(${'linked_object'.$properties[$i]['ID']});
			
			global ${'linked_property'.$properties[$i]['ID']};
			$properties[$i]['LINKED_PROPERTY'] = trim(${'linked_property'.$properties[$i]['ID']});
			
			global ${'linked_method'.$properties[$i]['ID']};
			$properties[$i]['LINKED_METHOD'] = trim(${'linked_method'.$properties[$i]['ID']});
			
			SQLUpdate('miio_commands', $properties[$i]);
			
			$old_linked_object = $properties[$i]['LINKED_OBJECT'];
			$old_linked_property = $properties[$i]['LINKED_PROPERTY'];
			
			if ($old_linked_object && $old_linked_object != $properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property != $properties[$i]['LINKED_PROPERTY']) {
				removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
			}
		}
		
		$properties[$i]['VALUE'] = str_replace('",','", ',$properties[$i]['VALUE']);

		if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
			addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
		}
	
		if (file_exists(DIR_MODULES . 'devices/devices.class.php')) {
			if ($properties[$i]['TITLE'] == 'power') {
				$properties[$i]['SDEVICE_TYPE'] = 'relay';
			}
			if ($properties[$i]['TITLE'] == 'bright') {
				$properties[$i]['SDEVICE_TYPE'] = 'dimmer';
			}
			if ($properties[$i]['TITLE'] == 'cct') {
				$properties[$i]['SDEVICE_TYPE'] = 'dimmer';
			}
			if ($properties[$i]['TITLE'] == 'temperature') {
				$properties[$i]['SDEVICE_TYPE'] = 'sensor_temp';
			}
		}
	}
	$out['PROPERTIES'] = $properties;   
}

if (is_array($rec)) {
	foreach($rec as $k => $v) {
		if (!is_array($v)) {
			$rec[$k] = htmlspecialchars($v);
		}
	}
}

outHash($rec, $out);
