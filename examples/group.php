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
	require_once(__DIR__ . '/../app/gnkw.php');
	
	use Gnkam\Univ\Savoie\Edt\Formalizer;
	
	##################
	# Example of use #
	##################
	
	# Set headers
	header('Content-Type: application/json');
	
	# Cache link
	$cacheLink = __DIR__ . '/cache';
	
	# Create cache dir if not exists
	if(!is_dir($cacheLink))
	{
		if(!mkdir($cacheLink))
		{
			echo json_encode('error', 'Impossible to create cache');
			return;
		}
	}
	
	# 6 Hours update
	$update = 6 * 60 * 60;
	
	# Formalize Data
	$formalizer = new Formalizer($cacheLink, $update, 2);
	$json = $formalizer->serviceGroup(4336);
	
	# Show json
	echo json_encode($json);
?>
