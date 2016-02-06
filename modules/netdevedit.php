<?php

/*
 *  iNET LMS (LMS version 1.11-git)
 *
 *  (C) Copyright 2001-2012 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

if (!$LMS->NetDevExists($_GET['id'])) {
	$SESSION->redirect('?m=netdevlist');
}

$action = !empty($_GET['action']) ? $_GET['action'] : '';
$edit = '';
$subtitle = '';

switch ($action) {
	case 'replace':
		
		if (empty($_GET['id']) || empty($_GET['netdev']))
		    $SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);

		$dev1 = $LMS->GetNetDev($_GET['id']);
		$dev2 = $LMS->GetNetDev($_GET['netdev']);

		if ($dev1['ports'] < $dev2['takenports']) {
			$error['replace'] = trans('It scans for ports in source device!');
		} elseif ($dev2['ports'] < $dev1['takenports']) {
			$error['replace'] = trans('It scans for ports in destination device!');
		}

		if (!$error) {

			$links1 = $DB->GetAll('(SELECT type, speed, 
                        (CASE src WHEN ? THEN dst ELSE src END) AS id,
			(CASE src WHEN ? THEN srcport ELSE dstport END) AS srcport,
			(CASE src WHEN ? THEN dstport ELSE srcport END) AS dstport
			FROM netlinks WHERE src = ? OR dst = ?)
			UNION
			(SELECT linktype AS type, linktechnology AS technology, linkspeed AS speed, id, port AS srcport, NULL AS dstport
			FROM nodes WHERE netdev = ? AND ownerid > 0)
			ORDER BY srcport', array($dev1['id'], $dev1['id'], $dev1['id'],
					$dev1['id'], $dev1['id'], $dev1['id']));
			$links2 = $DB->GetAll('(SELECT type, speed, 
                        (CASE src WHEN ? THEN dst ELSE src END) AS id,
			(CASE src WHEN ? THEN srcport ELSE dstport END) AS srcport,
			(CASE src WHEN ? THEN dstport ELSE srcport END) AS dstport
			FROM netlinks WHERE src = ? OR dst = ?)
			UNION
			(SELECT linktype AS type, linktechnology AS technology, linkspeed AS speed, id, port AS srcport, NULL AS dstport
			FROM nodes WHERE netdev = ? AND ownerid > 0)
			ORDER BY srcport', array($dev2['id'], $dev2['id'], $dev2['id'],
					$dev2['id'], $dev2['id'], $dev2['id']));

			$DB->BeginTrans();

			$DB->Execute('UPDATE netdevices SET location = ? WHERE id = ?', array($dev1['location'], $dev2['id']));
			$DB->Execute('UPDATE netdevices SET location = ? WHERE id = ?', array($dev2['location'], $dev1['id']));

			$DB->Execute('UPDATE netdevices SET latitude = ? WHERE id = ?', array($dev1['latitude'], $dev2['id']));
			$DB->Execute('UPDATE netdevices SET latitude = ? WHERE id = ?', array($dev2['latitude'], $dev1['id']));

			$DB->Execute('UPDATE netdevices SET longitude = ? WHERE id = ?', array($dev1['longitude'], $dev2['id']));
			$DB->Execute('UPDATE netdevices SET longitude = ? WHERE id = ?', array($dev2['longitude'], $dev1['id']));

			$LMS->NetDevDelLinks($dev1['id']);
			$LMS->NetDevDelLinks($dev2['id']);

			$ports = array();
			// przypisujemy urzadzenia/komputer, probujac zachowac numeracje portow
			if ($links1)
				foreach ($links1 as $row) {
					$sport = $row['srcport'];
					if ($sport) {
						if ($sport > $dev2['ports'])
							for ($i = 1; $i <= $dev2['ports']; $i++)
								if (!isset($ports[$sport])) {
									$sport = $i;
									break;
								}

						$ports[$sport] = $sport;
					}

					if (isset($row['dstport'])) // device
						$LMS->NetDevLink($dev2['id'], $row['id'], $row['type'], $sport, $row['dstport'],$row['technology']);
					else // node
						$LMS->NetDevLinkNode($row['id'], $dev2['id'], $row['type'], $sport,$row['technology']);
				}

			$ports = array();
			if ($links2)
				foreach ($links2 as $row) {
					$sport = $row['srcport'];
					if ($sport) {
						if ($sport > $dev1['ports'])
							for ($i = 1; $i <= $dev1['ports']; $i++)
								if (!isset($ports[$sport])) {
									$sport = $i;
									break;
								}

						$ports[$sport] = $sport;
					}

					if (isset($row['dstport'])) // device
						$LMS->NetDevLink($dev1['id'], $row['id'], $row['type'], $sport, $row['dstport'],$row['technology']);
					else // node
						$LMS->NetDevLinkNode($row['id'], $dev1['id'], $row['type'], $sport,$row['technology']);
				}

			$DB->CommitTrans();

			$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
		}

	break;
	
	case 'setproject':
		$project = $_GET['project'];
		$id = $_GET['id'];
		if ($project != '-1') {
		    $DB->Execute('UPDATE nodes SET invprojectid = ? WHERE netdev = ? ;',array(($project ? $project : NULL),$id));
		}
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
	break;

	case 'disconnect':
		$LMS->NetDevUnLink($_GET['id'], $_GET['devid']);
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
	break;

	case 'disconnectnode':
		$LMS->NetDevLinkNode($_GET['nodeid'], 0);
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
	break;

	case 'chkmac':
		$DB->Execute('UPDATE nodes SET chkmac=? WHERE id=?', array($_GET['chkmac'], $_GET['ip']));
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id'] . '&ip=' . $_GET['ip']);
	break;

	case 'duplex':
		$DB->Execute('UPDATE nodes SET halfduplex=? WHERE id=?', array($_GET['duplex'], $_GET['ip']));
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id'] . '&ip=' . $_GET['ip']);
	break;
	
	case 'monit':
		$LMS->SetMonit($_GET['ip'],$_GET['monit']);
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id'] . '&ip=' . $_GET['ip']);
	break;
	
	case 'monitsignal':
		$LMS->SetSignalTestMonit($_GET['ip'],$_GET['monit']);
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id'] . '&ip=' . $_GET['ip']);
	break;

	case 'nas':
		$DB->Execute('UPDATE nodes SET nas=? WHERE id=?', array($_GET['nas'], $_GET['ip']));
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id'] . '&ip=' . $_GET['ip']);
	break;

	case 'connect':

		$linktype = !empty($_GET['linktype']) ? intval($_GET['linktype']) : '0';
		$linkspeed = !empty($_GET['linkspeed']) ? intval($_GET['linkspeed']) : '100000';
		$linktechnology = !empty($_GET['linktechnology']) ? intval($_GET['linktechnology']) : '0';
		$dev['srcport'] = !empty($_GET['srcport']) ? intval($_GET['srcport']) : '0';
		$dev['dstport'] = !empty($_GET['dstport']) ? intval($_GET['dstport']) : '0';
		$dev['id'] = !empty($_GET['netdev']) ? intval($_GET['netdev']) : '0';
		$layer = !empty($_GET['layer']) ? intval($_GET['layer']) : NULL;
		$tracttype = !empty($_GET['tracttype']) ? intval($_GET['tracttype']) : NULL;
		$teleline = !empty($_GET['teleline']) ? intval($_GET['teleline']) : '0';

		$ports1 = $DB->GetOne('SELECT ports FROM netdevices WHERE id = ?', array($_GET['id']));
		$takenports1 = $LMS->CountNetDevLinks($_GET['id']);

		$ports2 = $DB->GetOne('SELECT ports FROM netdevices WHERE id = ?', array($dev['id']));
		$takenports2 = $LMS->CountNetDevLinks($dev['id']);

		if ($ports1 <= $takenports1 || $ports2 <= $takenports2)
			$error['linknode'] = trans('No free ports on device!');
		else {
			if ($dev['srcport']) {
				if (!preg_match('/^[0-9]+$/', $dev['srcport']) || $dev['srcport'] > $ports2) {
					$error['srcport'] = trans('Incorrect port number!');
				} elseif ($DB->GetOne('SELECT id FROM nodes WHERE netdev=? AND port=? AND ownerid>0', array($dev['id'], $dev['srcport']))
						|| $DB->GetOne('SELECT 1 FROM netlinks WHERE (src = ? OR dst = ?)
					AND (CASE src WHEN ? THEN srcport ELSE dstport END) = ?', array($dev['id'], $dev['id'], $dev['id'], $dev['srcport']))) {
					$error['srcport'] = trans('Selected port number is taken by other device or node!');
				}
			}

			if ($dev['dstport']) {
				if (!preg_match('/^[0-9]+$/', $dev['dstport']) || $dev['dstport'] > $ports1) {
					$error['dstport'] = trans('Incorrect port number!');
				} elseif ($DB->GetOne('SELECT id FROM nodes WHERE netdev=? AND port=? AND ownerid>0', array($_GET['id'], $dev['dstport']))
						|| $DB->GetOne('SELECT 1 FROM netlinks WHERE (src = ? OR dst = ?)
					AND (CASE src WHEN ? THEN srcport ELSE dstport END) = ?', array($_GET['id'], $_GET['id'], $_GET['id'], $dev['dstport']))) {
					$error['dstport'] = trans('Selected port number is taken by other device or node!');
				}
			}
		}

		$SESSION->save('devlinktype', $linktype);
		$SESSION->save('devlinktechnology', $linktechnology);
		$SESSION->save('devlinkspeed', $linkspeed);

		if (!$error) {
			$LMS->NetDevLink($dev['id'], $_GET['id'], $linktype, $linkspeed, $dev['srcport'], $dev['dstport'],$linktechnology, $layer, $teleline, $tracttype);
			$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
		}

		$SMARTY->assign('connect', $dev);

	break;

	case 'connectnode':

		$linktype = !empty($_GET['linktype']) ? intval($_GET['linktype']) : '0';
		$linkspeed = !empty($_GET['linkspeed']) ? intval($_GET['linkspeed']) : '0';
		$linktechnology = !empty($_GET['linktechnology']) ? intval($_GET['linktechnology']) : '0';
		$node['port'] = !empty($_GET['port']) ? intval($_GET['port']) : '0';
		$node['id'] = !empty($_GET['nodeid']) ? intval($_GET['nodeid']) : '0';
		$layer = !empty($_GET['layer']) ? intval($_GET['layer']) : NULL;
		$tracttype = !empty($_GET['tracttype']) ? intval($_GET['tracttype']) : NULL;

		$ports = $DB->GetOne('SELECT ports FROM netdevices WHERE id = ?', array($_GET['id']));
		$takenports = $LMS->CountNetDevLinks($_GET['id']);

		if ($ports <= $takenports)
			$error['linknode'] = trans('No free ports on device!');
		elseif ($node['port']) {
			if (!preg_match('/^[0-9]+$/', $node['port']) || $node['port'] > $ports) {
				$error['port'] = trans('Incorrect port number!');
			} elseif ($DB->GetOne('SELECT id FROM nodes WHERE netdev=? AND port=? AND ownerid>0', array($_GET['id'], $node['port']))
					|| $DB->GetOne('SELECT 1 FROM netlinks WHERE (src = ? OR dst = ?)
				AND (CASE src WHEN ? THEN srcport ELSE dstport END) = ?', array($_GET['id'], $_GET['id'], $_GET['id'], $node['port']))) {
				$error['port'] = trans('Selected port number is taken by other device or node!');
			}
		}

		$SESSION->save('nodelinktype', $linktype);
		$SESSION->save('nodelinkspeed', $linkspeed);
		$SESSION->save('nodelinktechnology', $linktechnology);

		if (!$error) {
			$LMS->NetDevLinkNode($node['id'], $_GET['id'], $linktype, $linkspeed, $node['port'],$linktechnology, $layer, $tracttype);
			$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
		}

		$SMARTY->assign('connectnode', $node);

	break;

	case 'addip':

		$subtitle = trans('New IP address');
		$nodeipdata['access'] = 1;
		$nodeipdata['macs'] = array(0 => '');
		$SMARTY->assign('nodeipdata', $nodeipdata);
		$edit = 'addip';
	break;

	case 'editip':

		$nodeipdata = $LMS->GetNode($_GET['ip']);
		$subtitle = trans('IP address edit');
		$nodeipdata['ipaddr'] = $nodeipdata['ip'];
		$macs = array();
		foreach ($nodeipdata['macs'] as $key => $value)
			$macs[] = $nodeipdata['macs'][$key]['mac'];
		$nodeipdata['macs'] = $macs;
		$SMARTY->assign('nodeipdata', $nodeipdata);
		$edit = 'ip';
	break;

	case 'ipdel':

		if ($_GET['is_sure'] == '1' && !empty($_GET['ip'])) {
			$DB->Execute('DELETE FROM nodes WHERE id = ? AND ownerid = 0', array($_GET['ip']));
		}

		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
	break;

	case 'ipset':

		if (!empty($_GET['ip']))
			$DB->Execute('UPDATE nodes SET access = (CASE access WHEN 1 THEN 0 ELSE 1 END)
			WHERE id = ? AND ownerid = 0', array($_GET['ip']));
		else
			$LMS->IPSetU($_GET['id'], $_GET['access']);

		header('Location: ?' . $SESSION->get('backto'));
	break;

	case 'formaddip':

		$subtitle = trans('New IP address');
		$nodeipdata = $_POST['ipadd'];
		$nodeipdata['netid'] = $_POST['nodeeditnetid'];
		$nodeipdata['netid_pub'] = $_POST['nodeeditnetidpub'];
		$nodeipdata['ownerid'] = 0;
		
		foreach ($nodeipdata['macs'] as $key => $value)
			$nodeipdata['macs'][$key] = str_replace('-', ':', $value);

		foreach ($nodeipdata as $key => $value)
			if ($key != 'macs')
				$nodeipdata[$key] = trim($value);

		if ($nodeipdata['ipaddr'] == '' && empty($nodeipdata['macs']) && $nodeipdata['name'] == '' && $nodeipdata['passwd'] == '') {
			$SESSION->redirect('?m=netdevedit&action=addip&id=' . $_GET['id']);
		}

		if ($nodeipdata['name'] == '')
			$error['ipname'] = trans('Address field is required!');
		elseif (strlen($nodeipdata['name']) > 32)
			$error['ipname'] = trans('Specified name is too long (max.$a characters)!', '32');
		elseif ($LMS->GetNodeIDByName($nodeipdata['name']))
			$error['ipname'] = trans('Specified name is in use!');
		elseif (!preg_match('/^[_a-z0-9-]+$/i', $nodeipdata['name']))
			$error['ipname'] = trans('Name contains forbidden characters!');

		if ($nodeipdata['ipaddr'] == '')
			$error['ipaddr'] = trans('IP address is required!');
		elseif (!check_ip($nodeipdata['ipaddr']))
			$error['ipaddr'] = trans('Incorrect IP address!');
		elseif (!$LMS->IsIPValid($nodeipdata['ipaddr']))
			$error['ipaddr'] = trans('Specified address does not belongs to any network!');
		elseif (!$LMS->IsIPFree($nodeipdata['ipaddr'],$nodeipdata['netid']))
			$error['ipaddr'] = trans('Specified IP address is in use!');

		if ($nodeipdata['ipaddr_pub'] != '0.0.0.0' && $nodeipdata['ipaddr_pub'] != '') {
			if (!check_ip($nodeipdata['ipaddr_pub']))
				$error['ipaddr_pub'] = trans('Incorrect IP address!');
			elseif (!$LMS->IsIPValid($nodeipdata['ipaddr_pub']))
				$error['ipaddr_pub'] = trans('Specified address does not belongs to any network!');
			elseif (!$LMS->IsIPFree($nodeipdata['ipaddr_pub'],$nodeipdata['netid_pub']))
				$error['ipaddr_pub'] = trans('Specified IP address is in use!');
		}
		else
			$nodeipdata['ipaddr_pub'] = '0.0.0.0';

		$macs = array();
		foreach ($nodeipdata['macs'] as $key => $value)
			if (check_mac($value)) {
				if ($value != '00:00:00:00:00:00' && !chkconfig($CONFIG['phpui']['allow_mac_sharing']))
					if ($LMS->GetNodeIDByMAC($value))
						$error['mac' . $key] = trans('MAC address is in use!');
				$macs[] = $value;
			}
			elseif ($value != '')
				$error['mac' . $key] = trans('Incorrect MAC address!');
		if (empty($macs))
			$error['mac0'] = trans('MAC address is required!');
		else
			$nodeipdata['macs'] = $macs;

		if (strlen($nodeipdata['passwd']) > 32)
			$error['passwd'] = trans('Password is too long (max.32 characters)!');

		if (!isset($nodeipdata['chkmac']))
			$nodeipdata['chkmac'] = 0;
		if (!isset($nodeipdata['halfduplex']))
			$nodeipdata['halfduplex'] = 0;
		if (!isset($nodeipdata['nas']))
			$nodeipdata['nas'] = 0;
		if (!isset($nodeipdata['monitoring']))
			$nodeipdata['monitoring'] = 0;

		if (!$error) {
			$nodeipdata['warning'] = 0;
			$nodeipdata['location'] = '';
			$nodeipdata['netdev'] = $_GET['id'];
			$LMS->NodeAdd($nodeipdata);
			$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
		}

		$SMARTY->assign('nodeipdata', $nodeipdata);
		$edit = 'addip';
	break;

	case 'formeditip':

		$subtitle = trans('IP address edit');
		$nodeipdata = $_POST['ipadd'];
		$nodeipdata['netid'] = $_POST['nodeeditnetid'];
		$nodeipdata['netid_pub'] = $_POST['nodeeditnetidpub'];
		$nodeipdata['ownerid'] = 0;
		$error = array();
		
		foreach ($nodeipdata['macs'] as $key => $value)
			$nodeipdata['macs'][$key] = str_replace('-', ':', $value);

		foreach ($nodeipdata as $key => $value)
			if ($key != 'macs')
				$nodeipdata[$key] = trim($value);

		if ($nodeipdata['ipaddr'] == '' && empty($nodeipdata['macs']) && $nodeipdata['name'] == '' && $nodeipdata['passwd'] == '')
			$SESSION->redirect('?m=netdevedit&action=editip&id=' . $_GET['id'] . '&ip=' . $_GET['ip']);

		if ($nodeipdata['name'] == '')
			$error['ipname'] = trans('Address field is required!');
		elseif (strlen($nodeipdata['name']) > 32)
			$error['ipname'] = trans('Specified name is too long (max.$a characters)!', '32');
		elseif ($LMS->GetNodeIDByName($nodeipdata['name']) &&
				$LMS->GetNodeName($_GET['ip']) != $nodeipdata['name'])
			$error['ipname'] = trans('Specified name is in use!');
		elseif (!preg_match('/^[_a-z0-9-]+$/i', $nodeipdata['name']))
			$error['ipname'] = trans('Name contains forbidden characters!');

		if ($nodeipdata['ipaddr'] == '')
			$error['ipaddr'] = trans('IP address is required!');
		elseif (!check_ip($nodeipdata['ipaddr']))
			$error['ipaddr'] = trans('Incorrect IP address!');
		elseif (!$LMS->IsIPValid($nodeipdata['ipaddr']))
			$error['ipaddr'] = trans('Specified address does not belongs to any network!');
		elseif (!$LMS->IsIPFree($nodeipdata['ipaddr'],$nodeipdata['netid']) &&
				$LMS->GetNodeIPByID($_GET['ip']) != $nodeipdata['ipaddr'])
			$error['ipaddr'] = trans('IP address is in use!');

		if ($nodeipdata['ipaddr_pub'] != '0.0.0.0' && $nodeipdata['ipaddr_pub'] != '') {
			if (check_ip($nodeipdata['ipaddr_pub'])) {
				if ($LMS->IsIPValid($nodeipdata['ipaddr_pub'])) {
					$ip = $LMS->GetNodePubIPByID($nodeipdata['id']);
					if ($ip != $nodeipdata['ipaddr_pub'] && !$LMS->IsIPFree($nodeipdata['ipaddr_pub'],$nodeipdata['netid_pub']))
						$error['ipaddr_pub'] = trans('Specified IP address is in use!');
				}
				else
					$error['ipaddr_pub'] = trans('Specified IP address doesn\'t overlap with any network!');
			}
			else
				$error['ipaddr_pub'] = trans('Incorrect IP address!');
		}
		else
			$nodeipdata['ipaddr_pub'] = '0.0.0.0';

		$macs = array();
		foreach ($nodeipdata['macs'] as $key => $value)
			if (check_mac($value)) {
				if ($value != '00:00:00:00:00:00' && isset($CONFIG['phpui']['allow_mac_sharing']) && !chkconfig($CONFIG['phpui']['allow_mac_sharing']))
					if (($nodeid = $LMS->GetNodeIDByMAC($value)) != NULL && $nodeid != $_GET['ip'])
						$error['mac' . $key] = trans('MAC address is in use!');
				$macs[] = $value;
			}
			elseif ($value != '')
				$error['mac' . $key] = trans('Incorrect MAC address!');
		if (empty($macs))
			$error['mac0'] = trans('MAC address is required!');
		else
			$nodeipdata['macs'] = $macs;

		if (strlen($nodeipdata['passwd']) > 32)
			$error['passwd'] = trans('Password is too long (max.32 characters)!');

		if (!isset($nodeipdata['chkmac']))
			$nodeipdata['chkmac'] = 0;
		
		if (!isset($nodeipdata['halfduplex']))
			$nodeipdata['halfduplex'] = 0;
		
		if (!isset($nodeipdata['nas']))
			$nodeipdata['nas'] = 0;
		
		if (!isset($nodeipdata['monitoring']))
			$nodeipdata['monitoring'] = 0;
		
		if (!isset($nodeipdata['monitoringsignal']))
		    $nodeipdata['monitoringsignal'] = 0;

		if (empty($error)) {
			$nodeipdata['warning'] = 0;
			$nodeipdata['location'] = '';
			$nodeipdata['netdev'] = $_GET['id'];
			$LMS->NodeUpdate($nodeipdata);
			$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
		} 
		
		$nodeipdata['ip_pub'] = $nodeipdata['ipaddr_pub'];
		$SMARTY->assign('nodeipdata', $nodeipdata);
		$edit = 'ip';
		break;

	default:
		$edit = 'data';
		break;
}

if (isset($_POST['netdev'])) {
	$netdevdata = $_POST['netdev'];
	$netdevdata['id'] = $_GET['id'];
	
	if ($netdevdata['name'] == '')
		$error['name'] = trans('Device name is required!');
	elseif (strlen($netdevdata['name']) > 32)
		$error['name'] = trans('Specified name is too long (max.$a characters)!', '32');

	$netdevdata['ports'] = intval($netdevdata['ports']);

	if ($netdevdata['ports'] < $LMS->CountNetDevLinks($_GET['id']))
		$error['ports'] = trans('Connected devices number exceeds number of ports!');

	if (empty($netdevdata['clients']))
		$netdevdata['clients'] = 0;
	else
		$netdevdata['clients'] = intval($netdevdata['clients']);
	
	if (!empty($netdevdata['ebgp'])) {
	    $netdevdata['ebgp'] = str_replace(',','',$netdevdata['ebgp']);
	    $netdevdata['ebgp'] = str_replace('.','',$netdevdata['ebgp']);
	} else {
	    $netdevdata['ebgp'] = 0;
	}
	
	if (!empty($netdevdata['ibgp'])) {
	    $netdevdata['ibgp'] = str_replace(',','',$netdevdata['ibgp']);
	    $netdevdata['ibgp'] = str_replace('.','',$netdevdata['ibgp']);
	} else {
	    $netdevdata['ibgp'] = 0;
	}
	
	$netdevdata['purchasetime'] = 0;
	if ($netdevdata['purchasedate'] != '') {
		// date format 'yyyy/mm/dd'
		if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/', $netdevdata['purchasedate'])) {
			$error['purchasedate'] = trans('Invalid date format!');
		} else {
			$date = explode('/', $netdevdata['purchasedate']);
			if (checkdate($date[1], $date[2], (int) $date[0])) {
				$tmpdate = mktime(0, 0, 0, $date[1], $date[2], $date[0]);
				if (mktime(0, 0, 0) < $tmpdate)
					$error['purchasedate'] = trans('Date from the future not allowed!');
				else
					$netdevdata['purchasetime'] = $tmpdate;
			}
			else
				$error['purchasedate'] = trans('Invalid date format!');
		}
	}

	if ($netdevdata['guaranteeperiod'] != 0 && $netdevdata['purchasedate'] == '') {
		$error['purchasedate'] = trans('Purchase date cannot be empty when guarantee period is set!');
	}

	if (!$error) {
		if ($netdevdata['guaranteeperiod'] == -1)
			$netdevdata['guaranteeperiod'] = NULL;

		if (!isset($netdevdata['shortname']))
			$netdevdata['shortname'] = '';
		if (!isset($netdevdata['secret']))
			$netdevdata['secret'] = '';
		if (!isset($netdevdata['community']))
			$netdevdata['community'] = '';
		if (!isset($netdevdata['nastype']))
			$netdevdata['nastype'] = 0;

		if (empty($netdevdata['teryt']) && !$netdevdata['networknode']) {
			$netdevdata['location_city'] = null;
			$netdevdata['location_street'] = null;
			$netdevdata['location_house'] = null;
			$netdevdata['location_flat'] = null;
		}
		
		if ($netdevdata['networknode']) {
		    
		    $dane = $DB->GetRow('SELECT city, street, zip, location_city, location_street, location_house, location_flat, longitude, latitude 
					FROM networknode WHERE id = ?;',array($netdevdata['networknode']));
		    $adres = '';
		    $adres .= $dane['zip'].' '.$dane['city'].', ';
		    $adres .= $dane['street'].' '.$dane['location_house'];
		    if ($dane['location_flat'])
			$adres .= '/'.$dane['location_flat'];
		    
		    $netdevdata['location'] = $adres;
		    $netdevdata['location_city'] = $dane['location_city'];
		    $netdevdata['location_street'] = $dane['location_street'];
		    $netdevdata['location_house'] = $dane['location_house'];
		    $netdevdata['location_flat'] = $dane['location_flat'];
		    $netdevdata['longitude'] = $dane['longitude'];
		    $netdevdata['latitude'] = $dane['latitude'];
		}
		
		if (!$netdevdata['devtype'])
		    $netdevdata['managed'] = NIE;
		
		$LMS->NetDevUpdate($netdevdata);
		$SESSION->redirect('?m=netdevinfo&id=' . $_GET['id']);
	}
} else {
	$netdevdata = $LMS->GetNetDev($_GET['id']);

	if ($netdevdata['purchasetime'])
		$netdevdata['purchasedate'] = date('Y/m/d', $netdevdata['purchasetime']);

	if ($netdevdata['city_name'] || $netdevdata['street_name']) {
		$netdevdata['teryt'] = true;
		$netdevdata['location'] = location_str($netdevdata);
	}
}

$netdevdata['id'] = $_GET['id'];

$netdevips = $LMS->GetNetDevIPs($_GET['id']);
$nodelist = $LMS->GetUnlinkedNodes();
$netdevconnected = $LMS->GetNetDevConnectedNames($_GET['id']);
$netcomplist = $LMS->GetNetDevLinkedNodes($_GET['id']);
$netdevlist = $LMS->GetNotConnectedDevices($_GET['id']);

unset($netdevlist['total']);
unset($netdevlist['order']);
unset($netdevlist['direction']);

$replacelist = $LMS->GetNetDevList();

$replacelisttotal = $replacelist['total'];
unset($replacelist['order']);
unset($replacelist['total']);
unset($replacelist['direction']);

$layout['pagetitle'] = trans('Device Edit: $a ($b)', $netdevdata['name'], $netdevdata['producer']);

if ($subtitle)
	$layout['pagetitle'] .= ' - ' . $subtitle;

$SMARTY->assign('projectlist',$DB->getAll('SELECT id,name FROM invprojects WHERE type = 0 ORDER BY name ASC;'));
$SMARTY->assign('error', $error);
$SMARTY->assign('netdevinfo', $netdevdata);
$SMARTY->assign('netdevlist', $netdevconnected);
$SMARTY->assign('netcomplist', $netcomplist);
$SMARTY->assign('nodelist', $nodelist);
$SMARTY->assign('netdevips', $netdevips);
$SMARTY->assign('restnetdevlist', $netdevlist);
$SMARTY->assign('replacelist', $replacelist);
$SMARTY->assign('replacelisttotal', $replacelisttotal);
$SMARTY->assign('devlinktype', $SESSION->get('devlinktype'));
$SMARTY->assign('devlinktechnology', $SESSION->get('devlinktechnology'));
$SMARTY->assign('devlinkspeed', $SESSION->get('devlinkspeed'));
$SMARTY->assign('nodelinktype', $SESSION->get('nodelinktype'));
$SMARTY->assign('nodelinktechnology', $SESSION->get('nodelinktechnology'));
$SMARTY->assign('nodelinkspeed', $SESSION->get('nodelinkspeed'));
$SMARTY->assign('nastype', $LMS->GetNAStypes());
$SMARTY->assign('networknodelist',$LMS->GetListnetworknode());
$SMARTY->assign('devicestype',$LMS->GetDictionaryDevicesClientofType());

$annex_info = array('section'=>'netdev','ownerid'=>$netdevdata['id']);
$SMARTY->assign('annex_info',$annex_info);
include(MODULES_DIR . '/netdevxajax.inc.php');


//include(MODULES_DIR.'/annex.inc.php');


switch ($edit) {
	case 'data':
		if (chkconfig($CONFIG['phpui']['ewx_support']))
			$SMARTY->assign('channels', $DB->GetAll('SELECT id, name FROM ewx_channels ORDER BY name'));

		$SMARTY->display('netdevedit.html');
		break;
	case 'ip':
		$SMARTY->display('netdevipedit.html');
		break;
	case 'addip':
		$SMARTY->display('netdevipadd.html');
		break;
	default:
		$SMARTY->display('netdevinfo.html');
		break;
}
?>
