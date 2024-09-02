<?php

require_once(__DIR__.'/vendor/autoload.php');

require_once(__DIR__.'/inc/nagios.php');

// Command line parser
$parser = new Console_CommandLine(array(
	'description'	=> 'Check md raid device status',
	'version'		=> '0.0.1',
	'force_posix'	=> true
));

$parser->addOption('device', array(
	// 'short_name'	=> '-u',
	'long_name'		=> '--device',
	'description'	=> 'Name of the device to check (ex: md0)',
	'action'		=> 'StoreString'
));

try {
	$result = $parser->parse();

	$device = $result->options['device'];
	if (empty($device)) {
		echo 'No device requested: a device must be passed as parameter'."\n";
		exit(NAGIOS_UNKNOWN);
	}

	// exec('cat /proc/mdstat', $output, $return);
	exec('cat ./tests/mdstat-check.txt', $output, $return);
	if ($return !== 0) {
		echo 'Couldn\'t retrieve output from mdstat: returned '.$return."\n";
		exit(NAGIOS_UNKNOWN);
	}

	// var_dump($output);

	$arrays = [];
	foreach ($output as $row) {
		if (preg_match('/^(md[^ ]*) : .* (raid[0-9]+) /', $row, $matches)) {
			// var_dump($matches);
			$array = $matches[1];
			$arrays[$array] = [
				'type' => $matches[2],
			];
		} elseif (preg_match('/.* blocks.* \[([0-9]+)\/([0-9]+)\] \[([U_]+)\]$/', $row, $matches)) {
			// var_dump($matches);
			if (isset($array)) {
				$arrays[$array]['total'] = (int)$matches[1];
				$arrays[$array]['ok'] = (int)$matches[2];
				$arrays[$array]['status'] = $matches[3];
			}
		} elseif (preg_match('/.*\[[=>.]+\]*[ ]+([a-z]+)[ ]+=[ ]+([0-9.]+%).*finish=([^ ]+).*/', $row, $matches)) {
			// var_dump($matches);
			if (isset($array)) {
				$arrays[$array]['action'] = $matches[1];
				$arrays[$array]['action_progress'] = $matches[2];
				$arrays[$array]['action_finish'] = $matches[3];
			}
		}
	}

	// var_dump($arrays);
	
	if (empty($arrays)) {
		echo 'UNKKNOWN - No devices found in mdstat'."\n";
		exit(NAGIOS_UNKNOWN);
	}

	if (!array_key_exists($device, $arrays)) {
		echo 'UNKKNOWN - Device '.$device.' not found in mdstat'."\n";
		exit(NAGIOS_UNKNOWN);
	}

	$status = NAGIOS_OK;
	$output = '';

	$data = $arrays[$device];
	if ($data['total'] === $data['ok']) {
		$output .= $device.': OK '.$data['ok'].'/'.$data['total'];
	} else {
		$output .= $device.': ERR '.$data['ok'].'/'.$data['total'];

		$status = NAGIOS_CRITICAL;
	}

	if (isset($data['action'])) {
		$output .= ' - '.$data['action'].' '.$data['action_progress'].' '.$data['action_finish'];

		if ($status < NAGIOS_WARNING) {
			$status = NAGIOS_WARNING;
		}
	}

	// var_dump($status);
	// var_dump($output);

	echo $output."\n";
	exit($status);
} catch (Exception $exc) {
	$parser->displayError($exc->getMessage());
	exit(NAGIOS_UNKNOWN);
}
