<?php
/*
* Copyright (c) 2013 GNKW & Kamsoft.fr
*
* This file is part of Gnkam Univ Savoie Edt.
*
* Gnkam Univ Savoie Edt is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Gnkam Univ Savoie Edt is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with Gnkam Univ Savoie Edt.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gnkam\Univ\Savoie\Edt;

use Gnkw\Http\Rest\Client;
use Gnkw\Http\Uri;

/**
 * Receiver class
 * @author Anthony Rey <anthony.rey@mailoo.org>
 * @since 14/10/2013
 */
class TreeReceiver
{
	/**
	* Crawler base client
	* @var Gnkw\Http\Rest\Client
	*/
	protected $client;
	
	/**
	* Cookies
	* @var array
	*/
	protected $cookies = null;
	
	/**
	* Year project id
	* @var integer
	*/
	protected $projectId;
	
	protected $tree;
	
	protected $cacheDir = 'treepath';

	/**
	 * Receiver constructor
	 * @param string $cacheDir Cache directory
	 * @param integer $projectId Year project id
	 */
	public function __construct($cacheDir, $projectId)
	{
		$this->cacheDir = rtrim($cacheDir, '/') .  '/treepath';
		$this->projectId = $projectId;
		$this->client = new Client('http://ade52-savoie.grenet.fr/ade/');
		$this->doConnect();
	}
	
	public function doConnect()
	{
		$connect = $this->connect();
		$this->tree = $this->openTree();
// 		$this->tree = $this->openBranch(50);
// 		$this->tree = $this->openBranch(5635);
// 		$this->tree = $this->openBranch(5660);
// 		$this->tree = $this->openBranch(5920);
// 		$this->tree = $this->openCategory('instructor');
// 		$this->tree = $this->openCategory('room');
// 		$this->tree = $this->openCategory('resource');
	}
	
	protected function cleanDivs($divs)
	{
		$array = array();
		foreach($divs as $div)
		{
			foreach($div->attributes as $attribute)
			{
				if($attribute->value === 'treeline')
				{
					$array[] = $div;
					break;
				}
			}
		}
		return $array;
	}
	
	private function getNodes()
	{
		$page = $this->tree->getContent();
		// Change encoding
		$page = iconv("CP1252", "UTF-8", $page);
		preg_match_all('#\<DIV class\=\"treeline\"\>(.+)\<\/DIV\>#sU', $page, $doc);
		return $doc[1];
	}
	
	private function nodeReconstitution($node)
	{
		# Check for node type
		if(preg_match('#&nbsp;#', $node))
		{
			preg_match('#^([&nbsp;]+)(.+)#', $node, $nbsp);
			$count = substr_count($nbsp[1], '&nbsp');
			$level = $count/3;
			if(preg_match('#\"treebranch\"#', $node))
			{
				# Reconstitute treebranch
				preg_match('#\<SPAN class\=\"(.+)\"\>\<a href\=\"javascript\:(.+)\(([0-9]+)\,\ \'(.+)\'\)\"\>(.+)\<\/a\>\<\/SPAN\>#s', $node,$match);
				$data = array('name' => $match[5], 'id' => intval($match[3]), 'type' => $match[1]);
			}
			else
			{
				# Reconstitute treeitem
				preg_match('#\<\/SPAN\>(.+)<SPAN CLASS\=\"(.+)\"\>\<a href\=\"javascript\:(.+)\(([0-9]+)\,\ \'(.+)\'\)\;\"\>(.+)\<\/a\>\<\/SPAN\>#s', $node,$match);
				$data = $match;
				$data = array('name' => $match[6], 'id' => intval($match[4]), 'type' => $match[2]);
			}
		}
		else
		{
			# Reconstitute category
			$level = 0;
			preg_match('#\<SPAN CLASS\=\"(.+)\"\>\<a href\=\"javascript\:(.+)\(\'(.+)\'\)\"\>(.+)\<\/a\>\<\/SPAN\>#s', $node,$match);
			$data = array('name' => $match[4], 'id' => $match[3], 'type' => $match[1]);
		}
		return array('data' => $data, 'level' => $level);
	}
	
// 	protected function getArrayNodes()
// 	{
// 		$nodes = $this->getNodes();
// 		
// 		$lastLevel = 0;
// 		$tableLevel[-1] = -1;
// 		$parent = -1;
// 		$precLevel = 0;
// 		$array = array('data' => 'root', 'childs' => array());
// 		$table[-1] = &$array;
// 		foreach($nodes  as $key => $node)
// 		{
// 			$reconstitution = $this->nodeReconstitution($node);
// 			$level = $reconstitution['level'];
// 			$data = $reconstitution['data'];
// 			
// 			# Add object to general table
// 			$table[$key] = array('data' => $data);
// 			
// 			# Reconstitude tree in array
// 			if($level === $precLevel +1)
// 			{
// 				$parent = $tableLevel[$level - 1];
// 			}
// 			else if($level < $precLevel)
// 			{
// 				$oldLevel = $level - ($precLevel - $level);
// 				$parent = $tableLevel[$oldLevel];
// 			}
// 			$tableLevel[$level] = $key;
// 			$table[$parent]['childs'][] = &$table[$key];
// 			$precLevel = $level;
// 		}
// 		return $array['childs'];
// 	}
	
	public function getTableNodes()
	{
		$table = array();
		$nodes = $this->getNodes();
		foreach($nodes AS $node)
		{
		    $reconstitution = $this->nodeReconstitution($node);
		    $table[] = $reconstitution['data'];
		}
		return $table;
	}
	
	public function getAllNodes()
	{
		$nodes = $this->getNodes();
		
		$lastLevel = 0;
		$tableLevel[-1] = -1;
		$parent = -1;
		$precLevel = 0;
		$table[-1] = array('id' => -1, 'level' => -1, 'type' => 'root', 'childs' => array());
		foreach($nodes  as $node)
		{
			$reconstitution = $this->nodeReconstitution($node);
			$level = $reconstitution['level'];
			$data = $reconstitution['data'];
			$data['level'] = $level;
			
			$key = $data['id'];
			
			# Reconstitude tree in array
			if($level === $precLevel +1)
			{
				$parent = $tableLevel[$level - 1];
			}
			else if($level < $precLevel)
			{
				$oldLevel = $level - ($precLevel - $level);
				$parent = $tableLevel[$oldLevel];
			}
			$data['parent'] = $parent;
			
			# Add object to general table
			$table[$key] = $data;
			$tableLevel[$level] = $key;
			$table[$parent]['childs'][] = $data;
			$precLevel = $level;
		}
		return $table;
	}
	
	public function getMagicNodes($node)
	{
		return $this->getAllNodes();
	}
	
	/**
	* Get array for a group
	*/
	public function getArrayData($node)
	{
		if($node != -1)
		{
			if(!$this->getPathFromCache($node))
			{
				return null;
			}
		}
		$this->putPathInCache();
		$array = $this->getAllNodes();
		return $array[$node];
	}
	
	public function putPathInCache()
	{
		if(!is_dir($this->cacheDir))
		{
			mkdir($this->cacheDir);
		}
		$array = $this->getTableNodes();
		foreach($array as $element){
			$id = $element['id'];
			file_put_contents($this->cacheDir . '/' . $id . '.json',json_encode($this->getPath($id)));
		}
	}
	
	public function getPathFromCache($id)
	{
		$filePath = $this->cacheDir . '/' . $id . '.json';
		if(!is_file($filePath))
		{
			return false;
		}
		$element = json_decode(file_get_contents($filePath), true);
		$child = true;
		
		while($child)
		{
			switch($element['type'])
			{
				case 'treecategory':
					$this->tree = $this->openCategory($element['id']);
					break;
				case 'treebranch':
					$this->tree = $this->openBranch($element['id']);
					break;
			}
			if(isset($element['child']))
			{
				$element = $element['child'];
			}
			else
			{
				$child = false;
			}
		}
		return true;
	}
	
	public function getPath($id)
	{
		$array = $this->getAllNodes();
		$path = array();
		$node = $id;
		$oldPath = null;
		while($node !== -1)
		{
			$path = array(
				'type' => $array[$node]['type'],
				'id' => $array[$node]['id']
			);
			if(null !== $oldPath)
			{
				$path['child'] = $oldPath;
			}
			$node = $array[$node]['parent'];
			$oldPath = $path;
		}
		return $path;
	}
	
	/**
	* Connect to ADE
	* @return Gnkw\Http\Resource
	*/
	protected function connect()
	{
		
		$url = new Uri('/custom/modules/plannings/direct_planning.jsp');
		$url->addParam('projectId', $this->projectId);
		$url->addParam('login', 'ENSEIGNANT');
		$url->addParam('password', 'en734sa');
		$url->addParam('displayConfName', 'Consultation Portail');
		$request = $this->client->get($url);
		$response = $request->getResource();
		$this->filterateCookies($response);
		return $response;
	}
	
	protected function openTree()
	{
		$url = new Uri('/standard/gui/tree.jsp');
		$url->addParam('forceLoad', 'false');
		$url->addParam('isDirect', 'true');
		$request = $this->client->get($url);
		$cookies = array();
		if(isset($this->cookies['JSESSIONID']))
		{
			$cookies['JSESSIONID'] = $this->cookies['JSESSIONID'];
		}
		$request->setCookies($cookies);
		$response = $request->getResource();
		$this->filterateCookies($response);
		return $response;
	}
	
	/**
	* Open Category
	* @return Gnkw\Http\Resource
	*/
	protected function openCategory($category)
	{
		$url = new Uri('/standard/gui/tree.jsp');
		$url->addParam('category', $category);
		$url->addParam('expand', 'false');
		$url->addParam('forceLoad', 'false');
		$url->addParam('reload', 'false');
		$url->addParam('scroll', 0);
		$request = $this->client->get($url);
		$cookies = array();
		if(isset($this->cookies['JSESSIONID']))
		{
			$cookies['JSESSIONID'] = $this->cookies['JSESSIONID'];
		}
		$request->setCookies($cookies);
		$response = $request->getResource();
		$this->filterateCookies($response);
		return $response;
	}
	
	protected function openBranch($branchId)
	{
		$url = new Uri('/standard/gui/tree.jsp');
		$url->addParam('branchId', $branchId);
		$url->addParam('expand', 'false');
		$url->addParam('forceLoad', 'false');
		$url->addParam('reload', 'false');
		$url->addParam('scroll', 0);
		$request = $this->client->get($url);
		$cookies = array();
		if(isset($this->cookies['JSESSIONID']))
		{
			$cookies['JSESSIONID'] = $this->cookies['JSESSIONID'];
		}
		$request->setCookies($cookies);
		$response = $request->getResource();
		$this->filterateCookies($response);
		return $response;
	}
	
	/**
	* Select a group
	* @return Gnkw\Http\Resource
	*/
	protected function selectGroup($id)
	{
		if($this->resetGroup)
		{
			$reset =  'true';
			$this->resetGroup = false;
		}
		else
		{
			$reset = 'false';
		}
		$url = new Uri('/standard/gui/tree.jsp');
		$url->addParam('selectId',$id);
		$url->addParam('reset',$reset);
		$url->addParam('forceLoad','false');
		$url->addParam('scroll',0);
		$request = $this->client->get($url);
		$cookies = array();
		if(isset($this->cookies['JSESSIONID']))
		{
			$cookies['JSESSIONID'] = $this->cookies['JSESSIONID'];
		}
		$request->setCookies($cookies);
		$response = $request->getResource();
		$this->filterateCookies($response);
		return $response;
	}
	
	/**
	* Get cookies from Resource headers
	*/
	protected function filterateCookies($response)
	{
		$headers = $response->getHeaders();
		$val = array();
		foreach($headers AS $header)
		{
			$val = array_map('trim', explode(':', $header));
			if($val[0] === 'Set-Cookie')
			{
				$tempArray = array_map('trim', explode(';', $val[1]));
				foreach($tempArray as $cookies){
					$cookie = array_map('trim', explode('=',$cookies));
					$key = $cookie[0];
					$value = $cookie[1];
					$this->cookies[$key] = $value;
				}
			}
		}
	}
}
