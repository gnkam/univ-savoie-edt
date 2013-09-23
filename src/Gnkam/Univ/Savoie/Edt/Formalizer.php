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

/**
 * Formalizer class
 * @author Anthony Rey <anthony.rey@mailoo.org>
 * @since 15/09/2013
 */
class Formalizer
{

	/**
	* Cache directory
	* @var string
	*/
	private $cache;
	
	/**
	* Know there is a cache
	* @var boolean
	*/
	private $cachingOk = false;
	
	/**
	* Update time in seconds
	* @var integer
	*/
	private $update;
	
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
		if(is_dir($cache))
		{
			$this->cache = rtrim($cache, '/');
			$this->cachingOk = true;
		}
		
		$this->projectId = $projectId;
		
		$this->update = $update;
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
		
		# Check for cache
		if(!$this->cachingOk)
		{
			return null;
		}
		
		# Create cache group directory if not exists
		$fileDir = $this->cache . '/group';
		if(!is_dir($fileDir))
		{
			if(!mkdir($fileDir))
			{
				return null;
			}
		}
		
		# Files to create
		$filePath = $fileDir . '/' . $group . '.json';
		$filePathPending = $filePath . '.lock';
		
		# Initialisation
		$json = array();
		$recreate = false;
		$currentTime = time();
		
		# Test pending
		$pending = $this->testPending($filePathPending);

		# File already exist
		if(is_file($filePath))
		{
			$json = json_decode(file_get_contents($filePath), true);
			if($pending)
			{
				$json['status'] = 'pending';
			}
			else
			{
				if(isset($json['updated']))
				{
					$updateTimeMax = $json['updated'] + $this->update;
					if(time() > $updateTimeMax)
					{
						$recreate = true;
					}
				}
				else
				{
					$recreate = true;
				}
			}
		}
		else
		{
			$recreate = true;
		}
		
		# Recreate file
		if($recreate)
		{
			if($pending AND is_file($filePath))
			{
				$json = json_decode(file_get_contents($filePath), true);
				$json['status'] = 'pending';
			}
			else
			{
				# Create lock file
				file_put_contents($filePathPending, time());
				
				# Receive the group json data
				$reciever = new GroupReceiver($this->projectId);
				$json['data'] = $reciever->getArrayData($group);
				
				# Set meta group informations
				$json['group'] = $group;
				$json['status'] = 'last';
				$json['updated'] = time();
				$json['date'] = time();
				
				# Put it in a string
				$string = json_encode($json);
				
				# Test data
				if(!empty($string) AND count($json['data']) > 0)
				{
					file_put_contents($filePath, $string);
				}
				else
				{
					# Error case (example : impossible to contact ADE)
					if(is_file($filePath))
					{
						# Old file exist : send old file
						$json = json_decode(file_get_contents($filePath), true);
						$json['status'] = 'old';
						$json['updated'] = time() - $locktimeup;
						$string = json_encode($json);
						file_put_contents($filePath, $string);
					}
					else
					{
						# Send error
						$json = array('error' => 'resource get failure');
					}
				}
				# Remove lock file
				unlink($filePathPending);
			}
		}
		return $json;
	}
	
	/**
	* Test if service is lockeb by another call
	* @param string $file_Lockfile path
	*/
	public function testPending($file)
	{
		$locktimeup = $this->update/2;
		if(is_file($file))
		{
			$lockTimeMax = file_get_contents($file) + $locktimeup;
			if($currentTime > $lockTimeMax)
			{
				unlink($file);
			}
			else
			{
				return true;
			}
		}
		return false;
	}
}
