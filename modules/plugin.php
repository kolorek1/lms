<?php

/*
 *  iLMS
 *
 *  (C) Copyright 2012-2015 LMS Developers
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
 *  Sylwester Kondracki
 *  sylwester.kondracki@gmail.com
 *  gadu-gadu : 6164816
*/

$layout['pagetitle'] = trans('Configure Plugins');
if (isset($_GET['action']) && !empty($_GET['action']))
{

    switch ($_GET['action'])
    {
    
	case 'enabled' :
		$id = intval($_GET['id']);
		$set = ($_GET['set'] ? 1 : 0);
		$DB->Execute('UPDATE plug SET enabled=? WHERE id = ?;',array($set,$id));
		$SESSION->redirect('?m=plugin');
	break;
	
	case 'install' :
		$ind = (string) $_GET['ind'];
		$name = (string) $_GET['name'];
		
		require_once(PLUG_DIR.'/'.$name.'/configuration.php');
		
		if ($__info['name'] == $name && $__info['indexes'] == $ind) {
			
			if (!$DB->GetOne('SELECT 1 FROM plug WHERE UPPER(name)=? AND UPPER(indexes)=? LIMIT 1;',array(strtoupper($name),strtoupper($ind))))
				$DB->Execute('INSERT INTO plug (name,indexes,enabled,dbver) VALUES (?, ?, ?, ?);',
				array($__info['name'],$__info['indexes'],1,''));
			
		}
		
		$SESSION->redirect('?m=plugin');
	break;
	
	case 'installdb' :
		
		if (!isset($_GET['is_sure']) || !intval($_GET['is_sure']) || intval($_GET['is_sure']) != 1) {
			$SESSION->redirect('?m=plugin');
			die;
		}
		
		$ind = (string) $_GET['ind'];
		$name = (string) $_GET['name'];
		
		require_once(PLUG_DIR.'/'.$name.'/configuration.php');
		
		if ($__info['name'] == $name && $__info['indexes'] == $ind) {
			
			if (!$DB->GetOne('SELECT 1 FROM plug WHERE UPPER(name)=? AND UPPER(indexes)=? LIMIT 1;',array(strtoupper($name),strtoupper($ind))) && file_exists(PLUG_DIR.'/'.$name.'/install/'.$DB->_dbtype.'.install.php')) {
			
				$DB->Execute('INSERT INTO plug (name,indexes,enabled,dbver) VALUES (?, ?, ?, ?);',
				array($__info['name'],$__info['indexes'],1,''));
				
				set_time_limit(0);
				include(PLUG_DIR.'/'.$name.'/install/'.$DB->_dbtype.'.install.php');
			}	
		}
		
		$SESSION->redirect('?m=plugin');
	break;
    
    }

}

$pluglist = array();

$plugindir = opendir(PLUG_DIR);
while ( false != ($dirname = readdir($plugindir))) {
    
    if ((preg_match('/^[a-zA-Z0-9]/',$dirname)) && (is_dir(PLUG_DIR.'/'.$dirname)) && file_exists(PLUG_DIR.'/'.$dirname.'/configuration.php')) {
	
	include(PLUG_DIR.'/'.$dirname.'/configuration.php');
	
	if (isset($__info['disabled']) && $__info['disabled'] == true) continue;
	if (isset($__info['revision']) && $__info['revision'] != LMSR) continue;
	
	$pluglist[$__info['indexes']] = $__info;
    }
}

$installplug = $DB->GetAllByKey('SELECT id,indexes,enabled,dbver, 1 AS install FROM plug ORDER BY name ASC;','indexes');

foreach ($pluglist as $key => $item) {
    if (isset($installplug[$key])) {
	$pluglist[$key]['install'] = $installplug[$key]['install'];
	$pluglist[$key]['id'] = $installplug[$key]['id'];
	$pluglist[$key]['enabled'] = $installplug[$key]['enabled'];
	$pluglist[$key]['dbver'] = $installplug[$key]['dbver'];
    }
}
$SMARTY->assign('pluglist',$pluglist);
$SMARTY->display('plugin.html');

?>