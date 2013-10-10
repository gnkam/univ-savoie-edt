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
 * @author Camille Colomb
 * @author Sebastien Franchon
 * @author Anthony Rey <anthony.rey@mailoo.org>
 * @since 08/09/2013
 */
class GroupReceiver
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
	* Page content
	* @var string
	*/
	protected $page = null;
	
	/**
	* Reset group
	* @var boolean
	*/
	protected $resetGroup = true;
	
	/**
	* Reset week
	* @var boolean
	*/
	protected $resetWeek = true;
	
	/**
	* Year project id
	* @var integer
	*/
	protected $projectId;

	/**
	 * Receiver constructor
	 * @param integer $projectId Year project id
	 */
	public function __construct($projectId)
	{
		$this->projectId = $projectId;
		$this->client = new Client('http://ade52-savoie.grenet.fr/ade/');
		$connect = $this->connect();
		$nodeTrainee = $this->openNode('trainee');
		$nodeInstructor = $this->openNode('instructor');
		$nodeRoom = $this->openNode('room');
		$nodeResource = $this->openNode('resource');
		$showTable = $this->showTable();
		$this->selectAllWeeks();
	}
	
	/**
	* Select all weeks
	*/
	protected function selectAllWeeks()
	{
		for($i=0; $i<56; $i++)
		{
			$this->selectWeek($i);
		}
	}
	
	/**
	* Get array for a group
	*/
	public function getArrayData($group)
	{
		$this->resetGroup = true;
		$group = $this->selectGroup($group);
		$this->page = $this->page();
		return $this->parsing();
	}
	
	/**
	* Parse to array
	* @return array
	*/
	protected function parsing()
	{
		# Get the page
		$page = $this->page->getContent();
		if(empty($page))
		{
			return array();
		}
		
		# Clean the page
		$page = preg_replace('#(.+)\<table\>(.+)\<\/table\>(.+)#s', '<table>$2</table>', $page);
		$page = preg_replace ('#\<tr class\=\"subHeader1\"\>(.+)\<\/tr\>#sU', '', $page);
		$page = preg_replace ('#\<a href\=\"javascript\:ev\(([0-9]+)\)\"\>([^\<\/a\>]+)\<\/a\>#sU', '$2', $page);
		
		# Put in Dom
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($page);
		
		# Get values
		$raws = $dom->getElementsByTagName('tr');
		$matieres = array();
		foreach($raws as $raw){
			$cells = $raw->getElementsByTagName('td');
			$cellData = array();
			
			# Get cells values
			foreach($cells as $cell){
				$cellData[] = $cell->nodeValue;
			}
			
			# Transform to timestamp
			$dayMonthYear = explode('/', $cellData[0]);
			$hourMin = explode('h', $cellData[4]);
			$duration = str_replace('min', '',$cellData[5]);
			$duration = explode('h', $duration);
			if(preg_match('#min#', $cellData[5]))
			{
				# There is minutes
				if(!isset($duration[1]))
				{
					$duration[1] = $duration[0];
					$duration[0] = 0;
				}
			}
			else if(preg_match('#h#', $cellData[5]))
			{
				# There is hours but no mintes
				if(!isset($duration[1]))
				{
					$duration[1] = 0;
				}
			}
			else
			{
				# No specification about time format
				$duration[0] = (isset($duration[0])) ? $duration[0] : 0;
				$duration[1] = (isset($duration[1])) ? $duration[1] : 0;
			}
			
			# Save Data
			$matiere = array();
			$matiere['code'] = trim($cellData[1]);
			$matiere['week'] = intval($cellData[2]);
			$matiere['start'] = mktime ($hourMin[0], $hourMin[1], 0, $dayMonthYear[1], $dayMonthYear[0], $dayMonthYear[2]);
			$matiere['duration'] = ($duration[0] * 60 * 60) + ($duration[1] * 60);
			$matiere['end'] = $matiere['start'] + $matiere['duration'];
			$matiere['type'] = strtolower(trim($cellData[7]));
			$matiere['name'] = trim($cellData[12]);
			$matiere['teacher'] = trim($cellData[19]);
			$placeExtract = array();
			$placeExtract = explode('(', $cellData[20]);
			$matiere['place'] = trim($placeExtract[0]);
			$matiere['projector'] = false;
			if(isset($placeExtract[1]))
			{
				# Seats calcul
				$capacityExtract = array();
				$capacityExtract = explode(')', $placeExtract[1]);
				if(preg_match('#([0-9]+)places#', $capacityExtract[0], $seats))
				{
					$matiere['seats'] = intval($seats[1]);
				}
				# There is a projector ?
				if(isset($capacityExtract[1]))
				if(preg_match('#vidéo\.proj#', $capacityExtract[1]))
				{
					$matiere['projector'] = true;
				}
			}
			$matieres[] = $matiere;
		}
		return $matieres;
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
	
	/**
	* Open Node
	* @return Gnkw\Http\Resource
	*/
	protected function openNode($category)
	{
		$url = new Uri('/standard/gui/tree.jsp');
		$url->addParam('category', $category);
		$url->addParam('expand', 'false');
		$url->addParam('forceLoad', 'false');
		$url->addParam('reload', 'false');
		$url->addParam('relscrolload', 0);
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
	* Show table with all params
	* @return Gnkw\Http\Resource
	*/
	protected function showTable()
	{
		$url = new Uri('/custom/modules/plannings/direct_planning.jsp');
		$url->addParam('keepSelection', '');
		$url->addParam('showTree', 'true');
		$request = $this->client->post($url, null, 'showTab=true&showTabActivity=true&showTabWeek=true&showTabDay=true&showTabStage=true&showTabDate=true&showTabHour=true&aC=true&aTy=true&aUrl=true&showTabDuration=true&aSize=true&aMx=true&aSl=true&aCx=true&aCy=true&aCz=true&aTz=true&aN=true&aNe=true&displayConfId=26&showPianoWeeks=true&showPianoDays=true&display=true&x=&y=&isClickable=true&changeOptions=true&displayType=0&showLoad=false&showTree=true&showTabTrainees=true&sC=false&sTy=false&sUrl=false&sE=false&sM=false&sJ=false&sA1=false&sA2=false&sZp=false&sCi=false&sSt=false&sCt=false&sT=false&sF=false&sCx=false&sCy=false&sCz=false&sTz=false&showTabInstructors=true&iC=false&iTy=false&iUrl=false&iE=false&iM=false&iJ=false&iA1=false&iA2=false&iZp=false&iCi=false&iSt=false&iCt=false&iT=false&iF=false&iCx=false&iCy=false&iCz=false&iTz=false&showTabRooms=true&roC=false&roTy=false&roUrl=false&roE=false&roM=false&roJ=false&roA1=false&roA2=false&roZp=false&roCi=false&roSt=false&roCt=false&roT=false&roF=false&roCx=false&roCy=false&roCz=false&roTz=false&showTabResources=true&reC=false&reTy=false&reUrl=false&reE=false&reM=false&reJ=false&reA1=false&reA2=false&reZp=false&reCi=false&reSt=false&reCt=false&reT=false&reF=false&reCx=false&reCy=false&reCz=false&reTz=false&showTabCategory5=true&c5C=false&c5Ty=false&c5Url=false&c5E=false&c5M=false&c5J=false&c5A1=false&c5A2=false&c5Zp=false&c5Ci=false&c5St=false&c5Ct=false&c5T=false&c5F=false&c5Cx=false&c5Cy=false&c5Cz=false&c5Tz=false&showTabCategory6=true&c6C=false&c6Ty=false&c6Url=false&c6E=false&c6M=false&c6J=false&c6A1=false&c6A2=false&c6Zp=false&c6Ci=false&c6St=false&c6Ct=false&c6T=false&c6F=false&c6Cx=false&c6Cy=false&c6Cz=false&c6Tz=false&showTabCategory7=true&c7C=false&c7Ty=false&c7Url=false&c7E=false&c7M=false&c7J=false&c7A1=false&c7A2=false&c7Zp=false&c7Ci=false&c7St=false&c7Ct=false&c7T=false&c7F=false&c7Cx=false&c7Cy=false&c7Cz=false&c7Tz=false&showTabCategory8=true&c8C=false&c8Ty=false&c8Url=false&c8E=false&c8M=false&c8J=false&c8A1=false&c8A2=false&c8Zp=false&c8Ci=false&c8St=false&c8Ct=false&c8T=false&c8F=false&c8Cx=false&c8Cy=false&c8Cz=false&c8Tz=false');
		$cookies = array();
		if(isset($this->cookies['etudiant-displaysav52']))
		{
			$cookies['etudiant-displaysav52'] = $this->cookies['etudiant-displaysav52'];
		}
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
	* Select a week
	* @return Gnkw\Http\Resource
	*/
	protected function selectWeek($week)
	{
		if($this->resetWeek)
		{
			$reset =  'true';
			$this->resetWeek = false;
		}
		else
		{
			$reset = 'false';
		}
		$url = new Uri('/custom/modules/plannings/bounds.jsp');
		$url->addParam('week',$week);
		$url->addParam('reset',$reset);
		$request = $this->client->get($url);
		$cookies = array();
		if(isset($this->cookies['etudiant-displaysav52']))
		{
			$cookies['etudiant-displaysav52'] = $this->cookies['etudiant-displaysav52'];
		}
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
	* Receive page content (HTML)
	* @return Gnkw\Http\Resource
	*/
	protected function page()
	{
		$url = new Uri('/custom/modules/plannings/info.jsp');
		$request = $this->client->get($url);
		$cookies = array();
		if(isset($this->cookies['etudiant-displaysav52']))
		{
			$cookies['etudiant-displaysav52'] = $this->cookies['etudiant-displaysav52'];
		}
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
