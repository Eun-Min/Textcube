<?php
/// Copyright (c) 2004-2007, Needlworks / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/doc/LICENSE, /doc/COPYRIGHT)
$activePlugins = array();
$eventMappings = array();
$tagMappings = array();
$sidebarMappings = array();
$coverpageMappings = array();
$centerMappings = array();
$storageMappings = array();
$storageKeymappings = array();
$adminMenuMappings = array();
$adminHandlerMappings = array();

$configMappings = array();
$baseConfigPost = $service['path'].'/owner/setting/plugins/currentSetting';
$configPost  = '';
$configVal = '';
$typeSchema = null;

$formatterMapping = array('html' => array('name' => _t('HTML'), 'editors' => array('plain' => '')));
$editorMapping = array('plain' => array('name' => _t('편집기 없음')));
list($currentTextcubeVersion) = explode(' ', TEXTCUBE_VERSION, 2);

if (getBlogId()) {
	$activePlugins = DBQuery::queryColumn("SELECT name FROM {$database['prefix']}Plugins WHERE blogid = ".getBlogId());
	$xmls = new XMLStruct();
	$editorCount     = 0;
	$formatterCount  = 0;
	foreach ($activePlugins as $plugin) {
		$version = '';
		$disablePlugin= false;
		
		$manifest = @file_get_contents(ROOT . "/plugins/$plugin/index.xml");
		if ($manifest && $xmls->open($manifest)) {
			$requiredTattertoolsVersion = $xmls->getValue('/plugin/requirements/tattertools');
			$requiredTextcubeVersion = $xmls->getValue('/plugin/requirements/textcube');
			
			if (!is_null($requiredTattertoolsVersion) && !is_null($requiredTextcubeVersion)) {
				if ($currentTextcubeVersion < $requiredTattertoolsVersion && $currentTextcubeVersion < $requiredTextcubeVersion)
					$disablePlugin = true;
			} else if (!is_null($requiredTattertoolsVersion) && is_null($requiredTextcubeVersion)) {
				if ($currentTextcubeVersion < $requiredTattertoolsVersion)
					$disablePlugin = true;
			} else if (is_null($requiredTattertoolsVersion) && !is_null($requiredTextcubeVersion)) {
				if ($currentTextcubeVersion < $requiredTextcubeVersion)
					$disablePlugin = true;
			}
			
			if ($disablePlugin == false) {
				if ($xmls->doesExist('/plugin/version')) {
					$version = $xmls->getValue('/plugin/version');
				}
				if ($xmls->doesExist('/plugin/storage')) {
					foreach ($xmls->selectNodes('/plugin/storage/table') as $table) {
						$storageMappings = array();
						$storageKeymappings = array();					 
						if(empty($table['name'][0]['.value'])) continue;
						$tableName = htmlspecialchars($table['name'][0]['.value']);
						if (!empty($table['fields'][0]['field'])) {
							foreach($table['fields'][0]['field'] as $field) 
							{
								if (!isset($field['name']))
									continue; // Error? maybe loading fail, so skipping is needed.
								$fieldName = $field['name'][0]['.value'];
							
								if (!isset($field['attribute']))
									continue; // Error? maybe loading fail, so skipping is needed.
								$fieldAttribute = $field['attribute'][0]['.value'];
							
								$fieldLength = isset($field['length']) ? $field['length'][0]['.value'] : -1;
								$fieldIsNull = isset($field['isnull']) ? $field['isnull'][0]['.value'] : 1;
								$fieldDefault = isset($field['default']) ? $field['default'][0]['.value'] : null;
								$fieldAutoIncrement = isset($field['autoincrement']) ? $field['autoincrement'][0]['.value'] : 0;
							
								array_push($storageMappings, array('name' => $fieldName, 'attribute' => $fieldAttribute, 'length' => $fieldLength, 'isnull' => $fieldIsNull, 'default' => $fieldDefault, 'autoincrement' => $fieldAutoIncrement));
							}
						}
						if (!empty($table['key'][0]['.value'])) {
							foreach($table['key'] as $key) {
								array_push($storageKeymappings, $key['.value']);
							}
						}
						treatPluginTable($plugin, $tableName, $storageMappings, $storageKeymappings, $version);
						unset($tableName);
						unset($storageMappings);
						unset($storageKeymappings);
					}
				}
				if ($xmls->doesExist('/plugin/binding/listener')) {
					foreach ($xmls->selectNodes('/plugin/binding/listener') as $listener) {
						if (!empty($listener['.attributes']['event']) && !empty($listener['.value'])) {
							if (!isset($eventMappings[$listener['.attributes']['event']]))
								$eventMappings[$listener['.attributes']['event']] = array();
							array_push($eventMappings[$listener['.attributes']['event']], array('plugin' => $plugin, 'listener' => $listener['.value']));
						}
					}
					unset($listener);
				}
				if ($xmls->doesExist('/plugin/binding/tag')) {
					foreach ($xmls->selectNodes('/plugin/binding/tag') as $tag) {
						if (!empty($tag['.attributes']['name']) && !empty($tag['.attributes']['handler'])) {
							if (!isset($tagMappings[$tag['.attributes']['name']]))
								$tagMappings[$tag['.attributes']['name']] = array();
							array_push($tagMappings[$tag['.attributes']['name']], array('plugin' => $plugin, 'handler' => $tag['.attributes']['handler']));
						}
					}
					unset($tag);
				}
				if (doesHaveMembership() && $xmls->doesExist('/plugin/binding/center')) {
					$title = htmlspecialchars($xmls->getValue('/plugin/title[lang()]'));
					foreach ($xmls->selectNodes('/plugin/binding/center') as $center) {
						if (!empty($center['.attributes']['handler'])) {
							array_push($centerMappings, array('plugin' => $plugin, 'handler' => $center['.attributes']['handler'], 'title' => $title));
						}
					}
					unset($title);
					unset($center);
				}
				if ($xmls->doesExist('/plugin/binding/sidebar')) {
					$title = htmlspecialchars($xmls->getValue('/plugin/title[lang()]'));
					foreach ($xmls->selectNodes('/plugin/binding/sidebar') as $sidebar) {
						if (!empty($sidebar['.attributes']['handler'])) {
							// parameter parsing
							$parameters = array();
							if (isset($sidebar['params']) && isset($sidebar['params'][0]) && isset($sidebar['params'][0]['param'])) {
								foreach($sidebar['params'][0]['param'] as $param) {
									$parameter = array('name' => $param['name'][0]['.value'], 'type' => $param['type'][0]['.value'], 'title' => XMLStruct::getValueByLocale($param['title']));
									array_push($parameters, $parameter);				
								}
							}
							array_push($sidebarMappings, array('plugin' => $plugin, 'title' => $sidebar['.attributes']['title'], 'display' => $title, 'handler' => $sidebar['.attributes']['handler'], 'parameters' => $parameters));
						}
					}
					unset($sidebar);
				}
				if ($xmls->doesExist('/plugin/binding/coverpage')) {
					$title = htmlspecialchars($xmls->getValue('/plugin/title[lang()]'));
					foreach ($xmls->selectNodes('/plugin/binding/coverpage') as $coverpage) {
						if (!empty($coverpage['.attributes']['handler'])) {
							// parameter parsing
							$parameters = array();
							if (isset($coverpage['params']) && isset($coverpage['params'][0]) && isset($coverpage['params'][0]['param'])) {
								foreach($coverpage['params'][0]['param'] as $param) {
									$parameter = array('name' => $param['name'][0]['.value'], 'type' => $param['type'][0]['.value'], 'title' => XMLStruct::getValueByLocale($param['title']));
									array_push($parameters, $parameter);				
								}
							}
							array_push($coverpageMappings, array('plugin' => $plugin, 'title' => $coverpage['.attributes']['title'], 'display' => $title, 'handler' => $coverpage['.attributes']['handler'], 'parameters' => $parameters));
						}
					}
					unset($coverpage);
				}
				if($xmls->doesExist('/plugin/binding/config')) {
					$config = $xmls->selectNode('/plugin/binding/config');
					if( !empty( $config['.attributes']['dataValHandler'] ) )
						$configMappings[$plugin] = 
						array( 'config' => 'ok' , 'dataValHandler' => $config['.attributes']['dataValHandler'] );
					else
						$configMappings[$plugin] = array( 'config' => 'ok') ;
				}
				if (doesHaveMembership() && $xmls->doesExist('/plugin/binding/adminMenu')) {
					$title = htmlspecialchars($xmls->getValue('/plugin/title[lang()]'));

					if ($xmls->doesExist('/plugin/binding/adminMenu/viewMethods')) {
						foreach($xmls->selectNodes('/plugin/binding/adminMenu/viewMethods/method') as $adminViewMenu) {
							$menutitle = htmlspecialchars(XMLStruct::getValueByLocale($adminViewMenu['title']));
							if (empty($menutitle)) continue;
							if(isset($adminViewMenu['topMenu'][0]['.value'])) {
								$pluginTopMenuLocation = htmlspecialchars($adminViewMenu['topMenu'][0]['.value']);
								switch($pluginTopMenuLocation) {
									case 'center':
									case 'entry':
									case 'link':
									case 'skin':
									case 'plugin':
									case 'setting':
										break;
									default:
										$pluginTopMenuLocation = 'plugin';
								}
							} else {
								$pluginTopMenuLocation = 'plugin';
							}
							//var_dump($pluginTopMenuLocation);
							$pluginContentMenuOrder = empty($adminViewMenu['contentMenuOrder'][0]['.value'])? '100':$adminViewMenu['contentMenuOrder'][0]['.value'];
							$menuhelpurl = empty($adminViewMenu['helpurl'][0]['.value'])?'':$adminViewMenu['helpurl'][0]['.value'];
						
							if (!isset($adminViewMenu['handler'][0]['.value'])) continue;
							$viewhandler = htmlspecialchars($adminViewMenu['handler'][0]['.value']);	
							if (empty($viewhandler)) continue;
							$params = array();
							if (isset($adminViewMenu['params'][0]['param'])) {
								foreach($adminViewMenu['params'][0]['param'] as $methodParam) {
										if (!isset($methodParam['name'][0]['.value']) || !isset($methodParam['type'][0]['.value'])) continue;
										$mandatory = null;
										$default   = null;
										if( isset($methodParam['mandatory'][0]['.value']) ) {
											$mandatory = $methodParam['mandatory'][0]['.value'];
										}
										if( isset($methodParam['default'][0]['.value']) ) {
											$default = $methodParam['default'][0]['.value'];
										}
										array_push($params,array(
												'name' => $methodParam['name'][0]['.value'],
												'type' => $methodParam['type'][0]['.value'],
												'mandatory' => $mandatory,
												'default' => $default
												));
								}
							}
								
							$adminMenuMappings[$plugin . '/' . $viewhandler] = array(
								'plugin'   => $plugin, 
								'title'    => $menutitle,
								'handler'  => $viewhandler,
								'params'   => $params,
								'helpurl'  => $menuhelpurl,
								'topMenu'  => $pluginTopMenuLocation,
								'contentMenuOrder' => $pluginContentMenuOrder
							);
						}
					}
				
					unset($menutitle);
					unset($viewhandler);
					unset($adminViewMenu);
					unset($params);
				
					if (doesHaveMembership() &&$xmls->doesExist('/plugin/binding/adminMenu/methods')) {
						foreach($xmls->selectNodes('/plugin/binding/adminMenu/methods/method') as $adminMethods) {
							$method = array();
							$method['plugin'] = $plugin;
							if (!isset($adminMethods['handler'][0]['.value'])) continue;
							$method['handler'] = $adminMethods['handler'][0]['.value'];
							$method['params'] = array();
								if (isset($adminMethods['params'][0]['param'])) {
								foreach($adminMethods['params'][0]['param'] as $methodParam) {
									if (!isset($methodParam['name'][0]['.value']) || !isset($methodParam['type'][0]['.value'])) continue;
									$mandatory = null;
									$default   = null;
									if( isset($methodParam['mandatory'][0]['.value']) ) {
										$mandatory = $methodParam['mandatory'][0]['.value'];
									}
									if( isset($methodParam['default'][0]['.value']) ) {
										$default = $methodParam['default'][0]['.value'];
									}
									array_push($method['params'],array(
										'name' => $methodParam['name'][0]['.value'],
										'type' => $methodParam['type'][0]['.value'],
										'mandatory' => $mandatory,
										'default' => $default
									));
								}
							}
							$adminHandlerMappings[$plugin . '/' . $method['handler']] = $method;
						}
					}
				
					unset($method);
					unset($methodParam);
					unset($adminMethods);
				
				}
				if ($xmls->doesExist('/plugin/binding/formatter[lang()]')) {
					$formatterCount = $formatterCount + 1;
					foreach (array($xmls->selectNode('/plugin/binding/formatter[lang()]')) as $formatter) {
						if (!isset($formatter['.attributes']['name'])) continue;
						if (!isset($formatter['.attributes']['id'])) continue;
						$formatterid = $formatter['.attributes']['id'];
						$formatterinfo = array('id' => $formatterid, 'name' => $formatter['.attributes']['name'], 'plugin' => $plugin, 'editors' => array());
						if (isset($formatter['format'][0]['.value'])) $formatterinfo['formatfunc'] = $formatter['format'][0]['.value'];
						if (isset($formatter['summary'][0]['.value'])) $formatterinfo['summaryfunc'] = $formatter['summary'][0]['.value'];
						if (isset($formatter['usedFor'])) {
							foreach ($formatter['usedFor'] as $usedFor) {
								if (!isset($usedFor['.attributes']['editor'])) continue;
								$formatterinfo['editors'][$usedFor['.attributes']['editor']] = @$usedFor['.value'];
							}
						}
						$formatterMapping[$formatterid] = $formatterinfo;
					}
					unset($formatter);
					unset($formatterid);
					unset($formatterinfo);
					unset($usedFor);
				}
				if (doesHaveMembership() && $xmls->doesExist('/plugin/binding/editor[lang()]')) {
					$editorCount = $editorCount + 1;
					foreach (array($xmls->selectNode('/plugin/binding/editor[lang()]')) as $editor) {
						if (!isset($editor['.attributes']['name'])) continue;
						if (!isset($editor['.attributes']['id'])) continue;
						$editorid = $editor['.attributes']['id'];
						$editorinfo = array('id' => $editorid, 'name' => $editor['.attributes']['name'], 'plugin' => $plugin);
						if (isset($editor['initialize'][0]['.value'])) $editorinfo['initfunc'] = $editor['initialize'][0]['.value'];
						if (isset($editor['usedFor'])) {
							foreach ($editor['usedFor'] as $usedFor) {
								if (!isset($usedFor['.attributes']['formatter'])) continue;
								$formatterMapping[$usedFor['.attributes']['formatter']]['editors'][$editorid] = @$usedFor['.value'];
							}
						}
						$editorMapping[$editorid] = $editorinfo;
					}
					unset($editor);
					unset($editorid);
					unset($editorinfo);
					unset($usedFor);
				}
			}
		} else {
			$disablePlugin = true;
		}
		
		if ($disablePlugin == true) {
			deactivatePlugin($plugin);
		}
	}
	if(empty($formatterCount)) { // Any formatter is used, add the ttml formatter.
		activatePlugin('FM_TTML');
	}
	if(empty($editorCount)) { // Any editor is used, add the textcube editor.
		activatePlugin('FM_Modern');
	}
	unset($xmls);
	unset($currentTextcubeVersion, $disablePlugin, $plugin, $query, $requiredTattertoolsVersion, $requiredTextcubeVersion);

	// sort mapping by its name, with exception for default formatter and editor
	function _cmpfuncByFormatterName($x, $y) {
		if ($x == 'html') return -1;
		if ($y == 'html') return +1;
		return strcmp($formatterMapping[$x]['name'], $formatterMapping[$y]['name']);
	}
	function _cmpfuncByEditorName($x, $y) {
		if ($x == 'plain') return -1;
		if ($y == 'plain') return +1;
		return strcmp($editorMapping[$x]['name'], $editorMapping[$y]['name']);
	}
	uksort($editorMapping, '_cmpfuncByEditorName');
	uksort($formatterMapping, '_cmpfuncByFormatterName');
	foreach ($formatterMapping as $formatterid => $formatterentry) {
		uksort($formatterMapping[$formatterid]['editors'], '_cmpfuncByEditorName');
	}
	unset($formatterid);
	unset($formatterentry);
}
?>
