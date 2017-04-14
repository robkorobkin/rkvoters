<?php

/**
 * @package RK VOTERS
 */
/*
Plugin Name: RK VOTERS
Plugin URI: http://robkforcouncil.com/
Description: Super simple campaign management tool.
Version: 1.0.0
Author: Rob Korobkin
Author URI: http://robkorobkin.org
License: GPLv2 or later
Text Domain: crowdfolio
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

include('rkvoters-api.php');



add_action('init','rkv_api');
function rkv_api(){

	// is admin
	global $isAdmin;
	session_start();
	$user = wp_get_current_user();
	$isAdmin = false;
	if ( in_array( 'delete_others_posts', (array) $user->allcaps ) ) {
		$isAdmin = true;
		if($_SESSION['routeBackToApp']){
			unset($_SESSION['routeBackToApp']);
			header('Location: /voting-tool');
		}
	}
	else {
		
		// if unauthenticated and on app page - set flag and route to login
		if(strpos($_SERVER['REQUEST_URI'], 'voting-tool') === 1){
			$_SESSION['routeBackToApp'] = true;
			header('Location: /wp-admin');
			exit;
		}
	}
	
	
	if(count($_POST) == 0){
		$request = (array) json_decode(file_get_contents('php://input'));
	}
	else {
		$request = $_POST;
	}


	if(isset($request['api']) && $isAdmin){
		extract($request);
		$data_client = new RKVoters_Client();
		$data_client -> request = $request;

		$response = $data_client -> $api(); 
		echo json_encode($response);
		exit;
	}
	
	
	if(isset($_GET['export']) && $isAdmin){
		header("Content-Type: text/csv"); 
		header("Content-Disposition: attachment; filename=\"Contacts.csv\"");
		$data_client = new RKVoters_Client();
		
		// export emails
		if($_GET['export'] == 'emails'){
			$contacts = $data_client -> getContactsWithEmails();			

			// headers
			$keys = array_keys($contacts[0]);
			echo implode(',', $keys) . "\n";

			foreach($contacts as $contact){
				echo implode(',', $contact) . "\n"; 
			}

			exit;		
		}
		
		// export mailing list
		if($_GET['export'] == 'mailinglist'){
			$contacts = $data_client -> getMailingList();

			echo "Name; Address 1; Address 2 \n";			
			foreach($contacts as $k => $contact){
				echo "Everybody at; " . $contact['addr1'] . '; ' . $contact['addr2'] . "\n";
			}
			exit;		
		}
		
		
		// export donors
		if($_GET['export'] == 'donors'){
			$contacts = $data_client -> exportDonations();			

			// headers
			$keys = array_keys($contacts[0]);
			echo implode(',', $keys) . "\n";

			foreach($contacts as $contact){
				echo implode(',', $contact) . "\n"; 
			}

			exit;		
		}

	}
	
}


function rkvoters_template_main(){
	global $isAdmin;
	if(!$isAdmin) {
		echo "please login...";
		return;
	}
	$data_client = new RKVoters_Client();
	$rkvoters_data['streets'] = $data_client -> getStreetList();
	$rkvoters_data['turfs'] = $data_client -> getTurfList();	
	include('templates/tpl-main.php');
}
add_shortcode( 'rkvoters', 'rkvoters_template_main' );