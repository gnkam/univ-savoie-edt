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
use Gnkam\Base\Formalizer as BaseFormalizer;

/**
 * Formalizer class
 * @author Anthony Rey <anthony.rey@mailoo.org>
 * @since 15/09/2013
 */
class Formalizer extends BaseFormalizer
{
	
	/**
	* ProjectId (change every year, see ADE real edt to know year value)
	* @var integer
	*/
	private $projectId;
	
	/**
	 * Formalizer constructor
	 * @param string $cache Cache directory
	 * @param integer $update Update time in seconds
	 * @param integer $projectId ProjectId (change every year, see ADE real edt to know year value)
	 */
	public function __construct($cache, $update, $projectId)
	{
		parent::__construct($cache, $update);
		
		$this->projectId = $projectId;
	}
	
	/**
	* Call service for Group
	* @param integer $group Group to call
	* @return array Group Data
	*/
	public function serviceGroup($group)
	{
		# Check if empty
		$group = intval($group);
		if(empty($group))
		{
			return null;
		}
		
		$json = $this->service('group', $group);
		return $json;
	}
	
	/**
	* Receive group data
	* @param integer $group Group id to call
	* @return array Group Data
	*/
	public function groupData($group)
	{
		$reciever = new GroupReceiver($this->projectId);
		return $reciever->getArrayData($group);
	}
}
