<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class systembridge extends eqLogic {
	public static function cron() {
    $eqLogics = eqLogic::byType('systembridge', true);
    foreach ($eqLogics as $eqLogic) {
      $eqLogic->refresh();
    }
  }

	public function loadCmdFromConf($type) {
		if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
			return;
		}
		$content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
		if (!is_json($content)) {
			return;
		}
		$device = json_decode($content, true);
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
				if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
				|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
					$cmd = $liste_cmd;
					break;
				}
			}
			if ($cmd == null || !is_object($cmd)) {
				$cmd = new systembridgeCmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd, $command);
				$cmd->save();
			}
		}
	}

	public function postSave() {
		$this->loadCmdFromConf('systembridge');
		$this->refresh();
	}

	public function refresh() {
		$this->getAudio();
		$this->getBattery();
		$this->getCpu();
		$this->getFilesystem();
		$this->getMemory();
		$this->getNetwork();
		$this->getOs();
		$this->getProcesses();
	}

	public function callOpenData($_url, $_put = 'none', $_method = 'put') {
		$_url = 'http://' . $this->getConfiguration('ip') . ':9170' . $_url;
		log::add('systembridge', 'debug', 'Parse ' . $_url);
		$request_http = new com_http($_url);
    $request_http->setNoReportError(true);
		$headers = array();
		$headers[] = 'api-key: ' . $this->getConfiguration('key');
		$request_http->setHeader($headers);
		if ($_put != 'none') {
			if ($_method == 'put') {
				$request_http->setPut($_put);
			} else {
				$request_http->setPost($_put);
			}
		}
    $return = $request_http->exec(15,2);
		log::add('systembridge', 'debug', 'Result ' . $return);
		return json_decode($return, true);
	}

	public function getAudio() {
		$data =$this->callOpenData('/audio');
		$this->checkAndUpdateCmd('audio:muted', $data['current']['muted']);
		$this->checkAndUpdateCmd('audio:volume', $data['current']['volume']);
	}

	public function getBattery() {
		$data =$this->callOpenData('/battery');
		$this->checkAndUpdateCmd('battery:hasBattery', $data['hasBattery']);
		$this->checkAndUpdateCmd('battery:isCharging', $data['isCharging']);
		$this->checkAndUpdateCmd('battery:percent', $data['percent']);
		$this->checkAndUpdateCmd('battery:timeRemaining', $data['timeRemaining']);
	}

	public function getCpu() {
		$data =$this->callOpenData('/cpu');
		$this->checkAndUpdateCmd('cpu:speed', $data['cpu']['speed']);
		$this->checkAndUpdateCmd('cpu:speedMin', $data['cpu']['speedMin']);
		$this->checkAndUpdateCmd('cpu:speedMax', $data['cpu']['speedMax']);
		$this->checkAndUpdateCmd('cpu:cores', $data['cpu']['cores']);
	}

	public function getFilesystem() {
		$data =$this->callOpenData('/filesystem');
		$i=0;
		foreach ($data['fsSize'] as $key => $value) {
			$i++;
			$this->checkAndUpdateCmd('filesystem:' . $i . ':name', $key);
			$this->checkAndUpdateCmd('filesystem:' . $i . ':fs', $value['fs']);
			$this->checkAndUpdateCmd('filesystem:' . $i . ':size', $value['size']);
			$this->checkAndUpdateCmd('filesystem:' . $i . ':use', $value['use']);
			$this->checkAndUpdateCmd('filesystem:' . $i . ':mount', $value['mount']);
			$this->checkAndUpdateCmd('filesystem:' . $i . ':type', $value['type']);
			if ($i == 3) {
				break;
			}
		}
	}

	public function getMemory() {
		$data =$this->callOpenData('/memory');
		$this->checkAndUpdateCmd('memory:total', $data['total']);
		$this->checkAndUpdateCmd('memory:free', $data['free']);
		$this->checkAndUpdateCmd('memory:used', $data['used']);
		$this->checkAndUpdateCmd('memory:active', $data['active']);
		$this->checkAndUpdateCmd('memory:available', $data['available']);
		$this->checkAndUpdateCmd('memory:swaptotal', $data['swaptotal']);
		$this->checkAndUpdateCmd('memory:swapused', $data['swapused']);
	}

	public function getNetwork() {
		$data =$this->callOpenData('/network');
		$this->checkAndUpdateCmd('network:gatewayDefault', $data['gatewayDefault']);
		$this->checkAndUpdateCmd('network:interfaceDefault', $data['interfaceDefault']);
		$i=0;
		foreach ($data['interfaces'] as $key => $value) {
			$i++;
			$this->checkAndUpdateCmd('network:' . $i . ':name', $key);
			$this->checkAndUpdateCmd('network:' . $i . ':iface', $value['iface']);
			$this->checkAndUpdateCmd('network:' . $i . ':ip4', $value['ip4']);
			$this->checkAndUpdateCmd('network:' . $i . ':ip6', $value['ip6']);
			$this->checkAndUpdateCmd('network:' . $i . ':operstate', $value['operstate']);
			$this->checkAndUpdateCmd('network:' . $i . ':type', $value['type']);
			if ($i == 2) {
				break;
			}
		}
	}

	public function getOs() {
		$data =$this->callOpenData('/os');
		$this->checkAndUpdateCmd('os:distro', $data['distro']);
		$this->checkAndUpdateCmd('os:hostname', $data['hostname']);
		$this->checkAndUpdateCmd('os:fqdn', $data['fqdn']);
	}

	public function getProcesses() {
		$data =$this->callOpenData('/processes');
		$this->checkAndUpdateCmd('processes:avgLoad', $data['load']['avgLoad']);
		$this->checkAndUpdateCmd('processes:currentLoad', $data['load']['currentLoad']);
		$this->checkAndUpdateCmd('processes:currentLoadUser', $data['load']['currentLoadUser']);
		$this->checkAndUpdateCmd('processes:currentLoadSystem', $data['load']['currentLoadSystem']);
		$this->checkAndUpdateCmd('processes:currentLoadNice', $data['load']['currentLoadNice']);
		$this->checkAndUpdateCmd('processes:currentLoadIdle', $data['load']['currentLoadIdle']);
		$this->checkAndUpdateCmd('processes:currentLoadIrq', $data['load']['currentLoadIrq']);
	}
}

class systembridgeCmd extends cmd {
	public function execute($_options = null) {
			if ($this->getType() == 'action') {
				$eqLogic = $this->getEqLogic();
				if ($this->getLogicalId() == 'refresh') {
					$eqLogic->refresh();
					return;
				}
				$put = array();
				if ($this->getSubType() == 'slider') {
					$put[$this->getConfiguration('argument')] = $_options['slider'];
				} else if ($this->getSubType() == 'select') {
					$put[$this->getConfiguration('argument')] = $_options['select'];
				} else if ($this->getSubType() == 'message') {
					$put[$this->getConfiguration('argument')] = $_options['title'];
					if ($this->getConfiguration('argument') == 'command') {
						$put['arguments'][] = $_options['message'];
					} else {
						if (strpos('icone=',$_options['message']) === false) {
							$put['message'][] = $_options['message'];
						} else {
							$parts = explode(';', str_replace('icone=','',$_options['message']));
							$put['message'][] = $parts[1];
							$put['icon'][] = $parts[0];
						}
					}
				} else {
					$put[$this->getConfiguration('argument')] = $this->getConfiguration('value');
				}
				if (strpos('audio',$this->getConfiguration('request')) === false) {
					$method = 'post';
				} else {
					$method = 'put';
				}
				$eqLogic->callOpenData($this->getConfiguration('request'),$put,$method);
			}
		}
}
?>
