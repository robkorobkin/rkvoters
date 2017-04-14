<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.15/angular.min.js"></script>
<script src="//angular-ui.github.io/bootstrap/ui-bootstrap-tpls-0.10.0.js"></script>
<link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet"> 
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">

<script>
	
	var app = angular.module('RKVApp', ['ui.bootstrap']);

	app.controller('RKVCtrl', ['$scope', '$http', '$sce', '$rootScope', '$window', '$modal',
		function($scope, $http, $sce, $rootScope, $window, $modal){
			
			// INIT STATE
			var $ = jQuery;
			$scope.init = function(){			
				$rootScope.appScope = $scope;
				$scope.people = {};
			
				// load server-side data
				<?php
					echo '$scope.rkvoters_data = ' . json_encode($rkvoters_data) . ";";
				?>

			
				$scope.listRequest = {
					'street_name' : 'Select Street...',
					'status' : '-',
					'only_active' : true,
					'type' : 'Active'
				};
			
				$scope.map = {
					root : 	'https://www.google.com/maps/embed/v1/place?' +
							'key=AIzaSyC6MLx8c1eQORx3uTNmL5RwXY761YSXaVs'
				}
				
				$scope.totals = [
					'active voters', 'contacts', 'supporters'
				];
				
				$rootScope.contactType = 'Phone Call';
				$rootScope.callStatus = 'Connection';
			}			
			$scope.init();
			
			// DATA MODEL
			$scope.load_turfs = function(turf_list){
						
				$scope.total_counts = {
					active_voters: 0,
					contacts: 0,
					likes: 0
				};
				$.each(turf_list, function(idx, turf){
					$scope.total_counts.active_voters += parseInt(turf.active_voters);
					$scope.total_counts.contacts += parseInt(turf.contacts);
					$scope.total_counts.likes += parseInt(turf.supporters);
				});			
			
				$scope.turf_hash = {};
				$.each(turf_list, function(idx, turf){
					$scope.turf_hash[turf.turfid] = turf;
					turf.totals = {};
					$.each($scope.totals, function(i, total_name){
						if(total_name == 'active voters'){
							turf.active_voters = parseInt(turf.active_voters);
							var percent =  parseInt((turf.active_voters / $scope.total_counts.active_voters) * 100);
							turf.totals['active voters'] = {
								num: turf.active_voters,
								percent: percent + '%' 
							}
						}
						else {
							turf[total_name] = parseInt(turf[total_name]);
							var percent = parseInt((turf[total_name] / turf.active_voters) * 100);
							turf.totals[total_name] = {
								num: turf[total_name],
								percent: percent + '%' 
							}
						}
					});
				});
				
			}
			$scope.load_turfs($scope.rkvoters_data.turfs);
			
			$scope.load_streets = function(street_list, openTurfs){
				$scope.streets = {};
				$.each(street_list, function(idx, street){
					var turf = $scope.turf_hash[street.turfid];
				
					if(!(street.turfid in $scope.streets)){
						$scope.streets[street.turfid] = {
							turf: turf,
							state : 'closed',
							toggle_command : 'open',
							streets: []
						}
						if(openTurfs && $.inArray(street.turfid, openTurfs) != -1){
							$scope.toggleStreetSet($scope.streets[street.turfid], true);
						}
					}
					street.totals = {};
					$.each($scope.totals, function(i, total_name){
						if(total_name == 'active voters'){
							street.active_voters = parseInt(street.active_voters);
							var percent = parseInt((street.active_voters / turf.active_voters) * 100);
							street.totals['active voters'] = {
								num: street.active_voters,
								percent: percent + '%'
							}
						}
						else {
							street[total_name] = parseInt(street[total_name]);
							var percent = parseInt((street[total_name] / street.active_voters) * 100);
							street.totals[total_name] = {
								num: street[total_name],
								percent: percent + '%' 
							}
						}
					});
					$scope.streets[street.turfid].streets.push(street);
				});
			}
			$scope.load_streets($scope.rkvoters_data.streets);
			
			$scope.load_knocklist = function(data){
				$scope.knocklist = {people: [], addresses: [], contacts: []};
				$scope.contactList = {};
				$scope.contactList.length_label = data.length + ' People';

				
				var current_addr = { address : '', residents : []};
				
				
				$.each(data, function(person_index, person){
					$scope.load_person(person);
					$scope.knocklist.people.push(person);
					
					if(person.status != 0) $scope.knocklist.contacts.push(person);
					
					// load address block
					var addr = person.stnum + ' ' + person.stname1;
					if(addr != current_addr.address){
						if(current_addr.address != '') {
							$scope.knocklist.addresses.push(current_addr);
						}
						current_addr = { 
							address : addr, 
							stname1 : person.stname1,
							stnum: person.stnum,
							residents : []
						};
					}
					current_addr.residents.push(person);
				});
				if(current_addr.address != '') {
					$scope.knocklist.addresses.push(current_addr);
				}
				
				
				// build multi-street object
				if($scope.viewMode == 'multi-sheet'){
					$scope.street_sets = [];
					var current_street = 'x';
					var street_index = 0;
					$.each($scope.knocklist.addresses, function(index, address){
						if(address.stname1 == '') return;
						if(address.stname1 != current_street){
							street_index++;
							current_street = address.stname1;
							$scope.street_sets[street_index] = {
								street_name : address.stname1,
								safeUrl : $sce.trustAsResourceUrl($scope.map.root + 
											'&q=' + address.stname1 + '+PORTLAND+ME'),
								addresses : []
							}
						}
						$scope.street_sets[street_index].addresses.push(address);
					});
				}

				
				$scope.$digest();
			}

			$scope.load_person = function(person){
				person.age = 2015 - person.yob;
				person.address = person.stnum + ' ' + person.stname1;
				person.residentLabel = '';
				if(person.unit != '') {
					person.address += ' - ' + person.unit;
					person.residentLabel += person.unit + ' - ';
				}
				person.residentLabel += person.firstname + ' ' + person.lastname + 
										' - ' + person.enroll + ' - ' + person.age;
				
				person.residentLabel = person.residentLabel.toUpperCase();
				
				if(person.votedin2011 == 1) {
					
					person.residentLabel += '*';
				}
				if(person.votedin2013 == 1) person.residentLabel += '*';				
				
				if(!('active' in person)) person.active = true;
				
				
				if(!(person.voterid in $scope.people)){
					$scope.people[person.voterid] = {};
				}
				
				for(var field in person){
					$scope.people[person.voterid][field] = person[field];
				}
				
			}

			$scope.reverse = function(){
				$scope.knocklist.people.reverse();
				$scope.knocklist.addresses.reverse();
			}



			// API
			$scope.getAddress = function(address){
				$scope.listRequest = {
					street_name : address.stname1,
					stnum : address.stnum
				}
				$scope.search();
			}
			
			$scope.search = function(){
				var request = {
					api: 'get_knocklist',
					listRequest: $scope.listRequest
				}
				var currentReference = this;
				$scope.currentReference = currentReference;
				$.post('', request, function(response){
					delete $scope.listRequest.stnum;
					$scope.updateMap($scope.listRequest.street_name);
					if($scope.currentReference != currentReference) return;
					$scope.load_knocklist(response);
				}, 'json');
			}
			
			$scope.updateTurfAssignment = function(street, oldTurfId){
				var request = {
					api: 'updateTurfAssignment',
					streetid: street.streetid,
					turfid: street.turfid
				}
				$.post('', request, function(streetlist){
					var openTurfs = [oldTurfId, street.turfid];
					$scope.load_streets(streetlist, openTurfs);
					$scope.$digest();
				}, 'json');
				
			}
			
			$scope.updateTotals = function(){
				var request = {
					api: 'updateTotals'
				}
				$.post('', request, function(response){
					$scope.load_turfs(response.turfs);
					$scope.load_streets(response.streets);
					$scope.$digest();
				}, 'json');
				
			}
			
			$scope.updateReportMode = function(viewMode){			
				if(viewMode == 'fundraising'){
					var request = {
						api: 'getDonations'
					}
					$.post('', request, function(response){
						$scope.donationTotal = response[0].total + response[1].total + response[2].total;
						$scope.donationSets = response;
						$scope.$digest();
					}, 'json');
				}
				if(viewMode == 'emailLocalSupporters'){
					var request = {
						api: 'getLocalSupporterEmails'
					}
					$.post('', request, function(response){
						$scope.localSupporterEmailList = response;
						$scope.$digest();
					}, 'json');
				}
				if(viewMode == 'mailingList'){
					var request = {
						api: 'getMailingList'
					}
					$.post('', request, function(response){
						console.log(response.length);
						$scope.listLength = response.length;
						$scope.mailingList = response;
						$scope.$digest();
					}, 'json');
				}
				
				
			}
			
			$scope.setStatus = function(status, person){
			
			
				person.status = status;
				var request = {
					api : 'updatePerson',
					voterid : person.voterid,
					person : person,
					listRequest: $rootScope.appScope.listRequest
				}
				$.post('', request, function(response){
					$rootScope.appScope.load_knocklist(response);	
				}, 'json');
			}
			
			$scope.openStreet = function(street_name){
				$scope.listRequest.street_name = street_name;
				$scope.loadComponent('knocklist');
				$scope.search();
			}
			
			
			// UI CONTROLS
			$scope.loadComponent = function(componentName){
				$scope.component = componentName;
				if(componentName == 'report'){
					$scope.showMap = 1;
					$rootScope.viewMode = 'totals';
				}
				if(componentName == 'knocklist'){
					$scope.showMap = -1;
					$rootScope.viewMode = 'knocknotes';				
				}

			}
			
			$scope.updateMap = function(street_name){
				if($scope.showMap == 1){
					var street_path = street_name + '+PORTLAND+ME';
					$scope.map.safeUrl = $sce.trustAsResourceUrl($scope.map.root + '&q=' + street_path);
				}
			}
			
			$scope.toggleMap = function(){
				$scope.showMap *= -1;
				if($scope.showMap == 1){
					$scope.updateMap($scope.listRequest.street_name);
				}
			}
			
			$scope.openPerson = function(person){
			
				if('knocklist' in $scope){
					$.each($scope.knocklist.people, function(index, row){
						if(row.voterid == person.voterid){
							$scope.selected_index = index;			
						}
					});
				}
					
				var request = {
					api : 'getFullPerson',
					voterid : person.voterid
				}
				$.post('', request, function(person){
					if(person.neighbors){
						$.each(person.neighbors, function(k, neighbor){
							$scope.load_person(neighbor);
						})
					}
					$scope.load_person(person);
					$scope.featured_person = person;					
					$modal.open({
						template: $('#modal_template').html(),
						controller: 'FeaturePersonCtrl',
					});			
				}, 'json');
			}
			
			$scope.openListManager = function(){								
				$modal.open({
					template: $('#modal_listManager').html(),
					controller: 'ListManagerCtrl',
				});			
			}
			
			$scope.openPersonAdder = function(){
				$rootScope.mode = 'Add';
				$modal.open({
					template: $('#modal_personAdder').html(),
					controller: 'PersonAdderCtrl'
				});		
			}
			
			$scope.toggleStreetSet = function(turf, dontupdate){
				if(turf.state == 'closed'){
					turf.state = 'open';
					turf.toggle_command = 'close';
				}
				else {
					turf.state = 'closed';
					turf.toggle_command = 'open';
				}
				//if(!dontupdate) $scope.$digest();
			}
		
			// AND FIRE!!!	
			$scope.loadComponent('knocklist');

		}
	]);
	
	app.controller('ListManagerCtrl', 
		['$scope', '$rootScope', '$modal',
			function($scope, $rootScope, $modal){
				var $ = jQuery;		
				
				$scope.litbomb = {};	
				
				$scope.dropBomb = function(){
					if(confirm('Are you sure you mean to drop this bomb?')){
						var request = {
							api: 'litBomb',
							date: $scope.litbomb.date,
							voterids : [],
							listRequest: $rootScope.appScope.listRequest
						}
						$.each($rootScope.appScope.knocklist.people, function(i, person){
							request.voterids.push(person.voterid);
						});
						$.post('', request, function(revisedList){
							$rootScope.appScope.load_knocklist(revisedList);
							$scope.$close();
						}, 'json');					
					}
				}
				
				$scope.sendPostcards = function(){
					if(confirm('Are the postcards in the mail?')){
						var request = {
							api: 'send_postcards',
							listRequest: $rootScope.appScope.listRequest
						}
						$.post('', request, function(revisedList){
							$rootScope.appScope.load_knocklist(revisedList);
							$scope.$close();
						}, 'json');					
					}
				
				}
				
			}
		]
	);

	app.controller('PersonAdderCtrl', 
		['$scope', '$rootScope', '$modal',
			function($scope, $rootScope, $modal){
				var $ = jQuery;		
				
				var st = $rootScope.appScope.listRequest.street_name;
				if(st == 'Select Street...') st = '';
				
				$scope.person = {
					stname1 : st,
					enroll: 'U',
					active: 1,
					city: 'Portland',
					state: 'ME'
				};	
				
				$scope.savePerson = function(){
					var request = {
						api: 'addPerson',
						person: $scope.person,
						listRequest: $rootScope.appScope.listRequest
					}
					$.post('', request, function(revisedList){
						$rootScope.appScope.load_knocklist(revisedList);
						$scope.$close();
					}, 'json');
				}
	
				
			}
		]
	);
	
	app.controller('FeaturePersonCtrl', 
		['$scope', '$rootScope', '$modal',
			function($scope, $rootScope, $modal){
				var $ = jQuery;			
				$scope.person = $rootScope.appScope.featured_person;
				$scope.newContact = {
					type : 'Post Card'
				};
				
				$scope.editBasicInfo = function(){
					$scope.$close();
					$rootScope.mode = 'Edit';
					$modal.open({
						template: $('#modal_personAdder').html(),
						controller: 'FeaturePersonCtrl'
					});
				}
				
				$scope.goBack = function(){			
					$scope.$close();
					$modal.open({
						template: $('#modal_template').html(),
						controller: 'FeaturePersonCtrl',
					});
				}
				
				// update person
				$scope.savePerson = function(mode){
					$scope.person.active = 1;
					var request = {
						api : 'updatePerson',
						voterid : $scope.person.voterid,
						person : $scope.person,
						listRequest: $rootScope.appScope.listRequest
					}
					$.post('', request, function(person){
						$scope.person = person;
						$rootScope.appScope.load_person(person);
						$scope.$digest();
						if(mode == 1) $scope.$close();
						if(mode == 2) $scope.openNext();
					}, 'json');
				}
				
				// record contact
				$scope.recordContact = function(progress){
					$scope.newContact.voterid = $scope.person.voterid;
					$scope.newContact.type = $rootScope.contactType;
					$scope.newContact.status = $rootScope.callStatus;
					
					var request = {
						api : 'recordContact',
						voterid : $scope.person.voterid,
						contact : $scope.newContact,
						person : $scope.person
					}
					$.post('', request, function(person){
						$scope.newContact = { }; 
						$scope.person = person;
						$rootScope.appScope.load_person(person);
						$scope.$digest();
						if(progress) $scope.openNext();				
					}
					, 'json');
				}
				
				// open next person
				$scope.openNext = function(){
					$scope.$close();
									
					var i = $rootScope.appScope.selected_index;
					i++;
					if(i == $rootScope.appScope.knocklist.people.length){
						i = 0;
					}
					$rootScope.appScope.selected_index = i;
					var p = $rootScope.appScope.knocklist.people[i];
					$rootScope.appScope.openPerson(p);
				}
				
				// open prev person
				$scope.openPrev = function(){
					$scope.$close();				
				
					var i = $rootScope.appScope.selected_index;
					i--;
					if(i == -1){
						i = $rootScope.appScope.knocklist.people.length - 1;
					}
					$rootScope.appScope.selected_index = i;
					var p = $rootScope.appScope.knocklist.people[i];
					$rootScope.appScope.openPerson(p);					
				}
				
				// remove
				$scope.removePerson = function(){
					if(confirm('Are you sure you want to remove this person?')){
						var request = {
							api : 'removePerson',
							voterid : $scope.person.voterid,
							listRequest: $rootScope.appScope.listRequest
						}
						$.post('', request, function(response){
							if(response.status == 'deleted'){
								$rootScope.appScope.load_knocklist(response.knocklist);
								$scope.$close();
							}
						}, 'json');
					}
				}
				
				// delete contact
				$scope.deleteContact = function(contact){
					var request = {
						api : 'deleteContact',
						vc_id : contact.vc_id,
						voterid: contact.voterid
					}
					$.post('', request, function(person){
						$scope.newContact = {}; 
						$scope.person = person;
						$rootScope.appScope.load_person(person);
						$scope.$digest();								
					}
					, 'json');
				}
			}
		]
	);
	
</script>

<style>
	body { font-family: "Open Sans",sans-serif; padding: 40px;}
	.searchFilter { margin: 10px 0; }
	.result { padding: 7px 15px 15px; border-top: solid 1px #ccc; font-size: 12px; page-break-inside: avoid; }
	.addressResult:hover { cursor: default; background: white; }
	.addressResident:hover { cursor: pointer; text-decoration: underline; }
	.innerFrame { padding: 0; }
	.status 	{ height: 30px; width: 30px; border: solid 1px #444;
					border-radius: 15px; text-align: center; padding-top: 6px; }
	.status span { display: none; }
	.status0	{ }
	.status1	{ background: green; }
	.status2	{ background: yellow; }
	.status3	{ background: red; }
	.status4	{ background: #777; }	
	.status5	{ background: black; }		
	
	.interaction_count { text-align: center; color: #999; font-size: 12px; margin-top: 5px; }
	.modal-dialog { width: 900px; max-width: 95%; }
	.modalFrame { padding: 15px; }
	.modalFrame .field 	{ padding-top: 15px; }
	.modalFrame  input 	{ width: 100%; }
	.modalFrame  select { width: 100%; }	
	.modalFrame  textarea { width: 100%; height: 60px; padding: 5px; font-size: 12px; }		
	.modalFrame  .topSection { border-bottom: dashed 1px #ccc; margin-bottom: 15px; padding-bottom: 15px; }		
	.modalFrame  h2 { margin-top: 0 }			
	label { margin: 15px 0 2px; }

	/* PRINT STYLES */
	@media print {
		.hp_header 	{ display: none; }
		.searchFilter { display: none; }
		#content { padding-top: 0; }
		#page_footer { display: none; }
		.result .left_info { display: none; }
		.result { padding: 4px 15px 10px 0  !important; font-size: 11px !important; }
		.person1, .person2, .person3 { font-style: italic; }
		.addressResident span { display: none; }
		.addressResident { padding-left: 0; font-size: 10px;}
	}
</style>

<div class="innerFrame" ng-app="RKVApp" ng-controller="RKVCtrl">
	<div class="interface_controller" style="text-align: left;">
		<a ng-click="loadComponent('report')" ng-if="component=='knocklist'">&lt; &lt; See Report</a>
		<a ng-click="loadComponent('knocklist')"  ng-if="component=='report'">
			&lt; &lt; See Knocklists
		</a>
	</div>

	<div ng-if="component=='knocklist'">
		<div class="searchFilter">
			<select ng-model="listRequest.street_name">
				<option>Select Street...</option>
				<optgroup ng-repeat="turf in streets" label="{{turf.turf.turf_name}}">
					<option ng-repeat="street in turf.streets">{{street.street_name}}</option>
				</optgroup>
			</select>
			<input ng-model="listRequest.search_str" ng-change="search()" placeholder="Search..." />
			<select ng-model="listRequest.type">
				<option>All</option>
				<option>Active</option>
				<option>Donors</option>
				<option>Volunteers</option>
				<option>Phones</option>
				<option>Phones - Open</option>
				<option>Phones - Not Called</option>
				<option>Phones (Not Anchor) - Not Called</option>
				<option>Phones - Called</option>				
				<option>Need Postcards</option>		
				<option>Sent Postcards</option>
				<option>Seniors - Phones</option>
				<option>Seniors - Phones - Not Called</option>
				<option>Active Under 35</option>
				<option>Active Under 35 - with phones</option>
				<option>West End - Super - No Contact</option>
				<option>Parkside - Phones</option>																										
			</select>
			<select ng-model="listRequest.party">
				<option>-</option>
				<option>G</option>
				<option>D</option>
				<option>R</option>
			</select>
			<select ng-model="listRequest.status">
				<option>-</option>
				<option>0</option>
				<option>1</option>
				<option>2</option>			
				<option>3</option>
				<option>4</option>
				<option>5</option>				
			</select>
			<button ng-click="search()">SEARCH</button>
			<button ng-click="reverse()">REVERSE</button>
			<button ng-click="openPersonAdder()">ADD PERSON</button>
			<button ng-click="toggleMap()">MAP</button>
				
			<div style="float: right; padding-top: 4px">
				<a ng-click="openListManager()">{{contactList.length_label}}</a>
			</div>
		</div>
	
		<div class="mapFrame" ng-if="showMap == 1">	
			<iframe
			  width="100%"
			  height="300"
			  frameborder="0" style="border:0"
			  ng-src="{{map.safeUrl}}" allowfullscreen>
			</iframe>
	
		</div>
	
		<div class="results clearfix">
			<div ng-if="$root.viewMode == 'individuals'">	
				<div class="result col-sm-4 clearfix" ng-repeat="person in knocklist.people"
						ng-click="openPerson(person, $index)" ng-if="person.active">
					<div style="float: left; margin-right: 20px;" class="left_info">
						<div class="status status{{person.status}}"><span>{{person.status}}</span></div>
						<div class="interaction_count">3</div>
					</div>		
					<div style="float: left;">
						<b>{{person.firstname}} {{person.lastname}}</b>
						<br />- {{person.phone}}
						<br />- {{person.enroll}} - {{person.age}}
					</div>
				</div>
			</div>
		
			<div ng-if="$root.viewMode == 'mark absentees'"> 
				<div ng-repeat="address in knocklist.addresses">	
					<div class="result addressResult col-sm-4 clearfix">
						<b ng-click="getAddress(address)">{{address.address}}</b>
						<div ng-repeat="person in address.residents" 
							ng-if="person.active">
							<div class="person{{person.status}}">
								<span class="status{{person.status}}">
									&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
								</span>
								&nbsp;
								<span ng-click="openPerson(person, $index)">{{person.residentLabel}}</span>
								- <span ng-click="setStatus(4, person)" class="addressResident" >P</span>
								- <span ng-click="setStatus(5, person)" class="addressResident" >V</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		
			<div ng-if="$root.viewMode == 'addresses'"> 
				<div ng-repeat="address in knocklist.addresses">
			
					<div class="result addressResult col-sm-4 clearfix">
						<b ng-click="getAddress(address)">{{address.address}}</b>
						<div ng-repeat="person in address.residents" 
							class="addressResident" ng-if="person.active">
							<div ng-click="openPerson(person)" class="person{{person.status}}">
								<span class="status{{person.status}}">
									&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
								</span>
								&nbsp;{{person.residentLabel}}
							</div>
						</div>
					</div>

					<div class="clearfix" ng-if="$index % 3 == 2"></div>

				</div>

				<div ng-repeat="person in knocklist.contacts" style="clear: both; font-size: 11px">
					<br /><br />
					<b style="text-transform: uppercase;" ng-click="openPerson(person, $index)">{{person.firstname}} {{person.lastname}}</b> - {{person.enroll}} - {{person.age}}
					
					<br />Status: {{person.status}}
					<br />{{person.bio}}
				</div>
			</div>
			
			
		</div>
		
		
		<div ng-if="$root.viewMode == 'knocknotes'"> 
			<div ng-repeat="address in knocklist.addresses" style="clear:both;" class="result clearfix">
				<div class="addressResult col-sm-4 clearfix">
					<b ng-click="getAddress(address)">{{address.address}}</b>
					<div ng-repeat="person in address.residents" 
						class="addressResident" ng-if="person.active">
						<div ng-click="openPerson(person)" class="person{{person.status}}">
							<span class="status{{person.status}}">
								&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
							</span>
							&nbsp;{{person.residentLabel}}
						</div>
					</div>
				</div>

				<div class="col-sm-8" style="font-size: 11px">
					<div ng-repeat="person in address.residents" ng-if="person.status != 0">
						<b style="text-transform: uppercase;">{{person.firstname}} {{person.lastname}}</b>
						<span ng-if="person.phone != ''"> - {{person.phone}}</span>
						<br />Status: {{person.status}} - {{person.bio}}
						<br /><br />
					</div>
				</div>
				<div class="clearfix" ng-if="$index % 3 == 2"></div>
			</div>
		</div>
		
		<div ng-if="$root.viewMode == 'multi-sheet'"> 
			<div ng-repeat="street in street_sets" style="clear:both; page-break-inside: auto" class="result clearfix">
			
				<h3>{{street.street_name}}</h3>
				<iframe
				  width="100%"
				  height="300"
				  frameborder="0" style="border:0"
				  ng-src="{{street.safeUrl}}" allowfullscreen>
				</iframe>
			
				<div ng-repeat="address in street.addresses" style="clear:both;" class="result clearfix">
					<div class="addressResult col-sm-5 clearfix">
						<b ng-click="getAddress(address)">{{address.address}}</b>
						<div ng-repeat="person in address.residents" 
							class="addressResident" ng-if="person.active">
							<div ng-click="openPerson(person)" class="person{{person.status}}">
								<span class="status{{person.status}}">
									&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
								</span>
								&nbsp;{{person.residentLabel}}
							</div>
						</div>
					</div>

					<div class="col-sm-7" style="font-size: 11px">
						<div ng-repeat="person in address.residents" ng-if="person.status != 0">
							<b style="text-transform: uppercase;">{{person.firstname}} {{person.lastname}}</b>
							<br />Status: {{person.status}} - {{person.bio}}
							<br /><br />
						</div>
					</div>
					<div class="clearfix" ng-if="$index % 3 == 2"></div>
				</div>
				
				<div style="page-break-after: always;">&nbsp;&nbsp;</div>
			</div>
		</div>
		
		
	</div>
	<div ng-if="component=='report'" class="clearfix report_frame" style="clear: both;">
		<div class="row" style="margin: 15px 0 30px;">
			<div class="col-md-6"> 
				<div style="float: left;">
					<select ng-model="viewMode" ng-change="updateReportMode(viewMode)">
						<option value="totals">VIEW TOTALS</option>
						<option value="assigner">REDRAW DISTRICTS</option>
						<option value="fundraising">FUNDRAISING REPORT</option>
						<option value="emailLocalSupporters">EMAIL LOCAL SUPPORTERS</option>
						<option value="mailingList">MAILING LIST</option>
					</select>
					<button ng-click="updateTotals()">UPDATE TOTALS</button>
				</div>
				<form action="" target="_blank" style="float: left; margin-left: 5px;">
					<input type="hidden" name="export" value="emails" />
					<input type="submit" value="EXPORT EMAILS"  />
				</form>
			</div>
			<form class="col-md-6" style="text-align: right;" >
				{{total_counts.active_voters|number}} Voters |
				{{total_counts.contacts|number}} Contacts | 
				{{total_counts.likes|number}} Likes
				({{ (100 * total_counts.likes / total_counts.contacts) |number}}%)
			</form>
		</div>
		<div class="turf_assigner col-md-9" ng-if="viewMode == 'assigner'">
			<div class="row">
				<div class="col-md-3"></div>
				<div class="col-md-9">
					<div ng-repeat="turf in turf_hash" class="col-md-2" style="padding: 0 3px">
						<i style="font-size: 11px;">{{turf.turf_name}}</i>
					</div>
				</div>
			</div>
			
			<div ng-repeat="turfObj in streets" style="margin-bottom: 15px;">
				<div>
					<b>{{turfObj.turf.turf_name}}</b> 
					- <a ng-click="toggleStreetSet(turfObj)">{{turfObj.toggle_command}}</a>
				</div>
				<div style="max-height: 400px; overflow-y: scroll; overflow-x: hidden; 
						border: solid 1px #ccc; margin: 5px 0; padding: 5px;" ng-if="turfObj.state == 'open'">
					<div class="row" ng-repeat="street in turfObj.streets" 
							style="padding: 10px 0; border-bottom: solid 1px #ccc">
						<div class="col-md-3">
							<a ng-click="updateMap(street.street_name)">
								{{street.street_name}}
							</a>
						</div>
						<div class="col-md-9">
							<div ng-repeat="turf in turf_hash" class="col-md-2">
								<input 	type="radio" name="street_{{street.streetid}}"
										ng-model="street.turfid"
										value="{{turf.turfid}}"
										ng-checked="street.turfid == turf.turfid"
										ng-change="updateTurfAssignment(street, turfObj.turf.turfid)" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="turf_totals col-md-9" ng-if="viewMode == 'totals'">		
			<div class="row">
				<div class="col-md-3"></div>
				<div class="col-md-9">
					<div ng-repeat="total in totals" class="col-md-4" 
						style="padding: 0 3px 5px; text-align: center;">
						<i style="font-size: 13px; text-transform: uppercase; font-weight: bold">{{total}}</i>
					</div>
				</div>
			</div>
			
			<div ng-repeat="turfObj in streets" style="margin-bottom: 15px;">
				<div class="row">
					<div class="col-md-3">
						<b>{{turfObj.turf.turf_name}}</b> 
						- <a ng-click="toggleStreetSet(turfObj)">{{turfObj.toggle_command}}</a>
					</div>
					<div class="col-md-9" style="text-align: center;">
						<div ng-repeat="total in turfObj.turf.totals" class="col-md-4">
							<div class="col-md-6">{{total.num | number}}</div>
							<div class="col-md-6">{{total.percent}}</div>
						</div>
					</div>
				</div>
				<div style="max-height: 400px; overflow-y: scroll; overflow-x: hidden; 
							border: solid 1px #ccc; margin: 5px 0; padding: 5px; background: #f5f5f5" 
					ng-if="turfObj.state == 'open'">
	
					<div class="row" ng-repeat="street in turfObj.streets" 
							style="padding: 10px 0; border-bottom: solid 1px #ccc">
						<div class="col-md-3">
							<a ng-click="openStreet(street.street_name)">
								{{street.street_name}}
							</a>
							- <a ng-click="updateMap(street.street_name)">MAP</a>
						</div>
						<div class="col-md-9" style="text-align: center;">
							<div ng-repeat="total in street.totals" class="col-md-4">
								<div class="col-md-6">{{total.num}}</div>
								<div class="col-md-6">{{total.percent}}</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="turf_totals col-md-9" ng-if="viewMode == 'fundraising'">
			
			<div style="font-size: 16px; margin-bottom: 40px;">
				<span style="font-weight: bold;">TOTAL: </span>
				<span>{{donationTotal | currency}}</span>				
			</div>

			<div style="font-size: 16px; margin-bottom: 40px;">
				<a href="?export=donors" target="_blank">Download</a>
			</div>
				
				
			<div ng-repeat="donationSet in donationSets">
				<div style="font-weight: bold; font-size: 16px;">{{donationSet.title}}</div>
				<div class="row clearfix" 
					 ng-repeat="donation in donationSet.donors" style="margin: 3px 0;">
					<div style="width: 80px; float: left; ">
						{{donation.amount | currency}}
					</div>
					<div style="width: 80px; float: left; ">
						{{ donation.datetime }}
					</div>
					<div style="float: left;">
						<a ng-click="openPerson(donation)">
							{{donation.firstname}} {{donation.lastname}}
						</a>
					</div>
				</div>
				
				<div class="row" >
					<div class="col-md-6" style="border-top: solid 1px #ccc;">
						<i>{{donationSet.total | currency}}</i>
					</div>
				</div>
				<br /><br />
			</div>			
		</div>
		<div class="turf_totals col-md-9" ng-if="viewMode == 'emailLocalSupporters'">
			{{localSupporterEmailList}}
		</div>		
		<div class="turf_totals col-md-9" ng-if="viewMode == 'mailingList'">
			{{listLength | number}} Records<br /><br />
			<a href="?export=mailinglist">DOWNLOAD</a><br /><br />
			<div ng-repeat="address in mailingList">
				<b>{{address.name}}</b> <br />{{address.address}}<br /><br />
			</div>
		</div>		
		
		<div class="map_frame col-md-3">
			<iframe
			  width="100%"
			  height="600"
			  frameborder="0" style="border:0"
			  ng-src="{{map.safeUrl}}" allowfullscreen>
			</iframe>
		</div>
	</div>
	
	
</div>


<div id="modal_template" style="display: none;">
	<div class="modalFrame clearfix">
		<div class="col-md-6">
			<b>{{person.firstname}} {{person.lastname}}</b>
			- <a ng-click="editBasicInfo()">EDIT</a>
			<br />- {{person.address}}, {{person.city}} {{person.state}} {{person.zip}}
			<br />- {{person.age}} - {{person.enroll}}
			<span ng-if="person.votedin2011 == 1"> - 2011</span>
			<span ng-if="person.votedin2013 == 1"> - 2013</span>			
		</div>
		<div class="col-md-6" style="text-align: right;">
			<div style="padding: 0 5px 10px;">
				<a ng-click="openPrev()">&lt;&lt;</a>
				&nbsp;&nbsp;&nbsp;
				<a ng-click="openNext()">&gt;&gt;</a>
			</div>
			<div style="clear: both;">
				<button ng-click="removePerson()" style="float: right">Remove from List</button>
			</div>
		</div>
		<div class="topSection clearfix" style="clear:both;">
			<div class="col-md-4  field">
				<b>Support Level:</b>
				<br />
				<select ng-model="person.status">
					<option value="0">0 - Unidentified</option>
					<option value="1">1 - With Us</option>
					<option value="2">2 - Undecided</option>
					<option value="3">3 - Against Us</option>
					<option value="4">4 - Pending Absentee</option>
					<option value="5">5 - Voted Absentee</option>					
				</select>			
			</div>
			<div class="col-md-4  field">
				<b>Email:</b>
				<br />
				<input placeholder="Email" ng-model="person.email" />
			</div>
			<div class="col-md-4  field">
				<b>Phone 
					<span ng-if="person.phoneType != ''">({{person.phoneType}})</span>
					<a ng-if="person.phone != ''" href="tel:{{person.phone}}">call</a>
					:
				</b>
				<br />
				<input placeholder="Phone" ng-model="person.phone" />
			</div>
			<div class="col-md-12 field">
				<b>Bio:</b>
				<br />
				<textarea ng-model="person.bio" style="height: 60px;"></textarea>
			</div>
			<div class="col-md-6">
				<label>
					<input id="volunteer_check" ng-model="person.volunteer" type="checkbox" style="width: 20px" 
							 ng-true-value="'true'" ng-false-value="''"/>&nbsp;
					Volunteer
				</label>
				<br /><br />
				<button ng-click="savePerson(1)">Save & Close</button>
				<button ng-click="savePerson(2)">Save & Next</button>
			</div>
			<div class="col-md-6" ng-if="person.neighbors">
				<br />
				<b>Folks at the same number \ address:</b>
				<ul>
					<li ng-repeat="neighbor in person.neighbors">
						{{neighbor.status}} - {{neighbor.residentLabel}} -  - <i>{{neighbor.bio}}</i>
					</li>
				</ul>
			</div>



		</div>
		<div class="bottomSection">
			<div class="col-md-6">
				<div>
					<select ng-model="person.closed">
						<option value="0">Person is Open</option>
						<option value="1">Person is Closed</option>						
					</select>
					<br /><br />
				</div>
			
				<h2>Add Contact</h2>
				<b>Type:</b>
				<select ng-model="$root.contactType">
					<option>Chat at Door</option>
					<option>Chat on Street</option>
					<option>Chat Elsewhere</option>
					<option>Donation</option>					
					<option>Email</option>
					<option>Lit Drop</option>
					<option>Phone Call</option>
					<option>Post Card</option>
					<option>Sent Post Card</option>	
					<option>Update</option>				
				</select>
				
				<br /><br />
				
				<b>Date:</b>
				<br />
				<input 
				type="text" class="form-control" 
				datepicker-popup="shortDate" 
				ng-model="newContact.datetime" 
				close-text="Close" 
				placeholder="Enter date..." />

				<div ng-if="$root.contactType == 'Donation'">
					<br />
					<b>Amount:</b>
					<br /><input type="number" ng-model="newContact.amount" />
				</div>

				<br />
				<b>Note:</b>
				<select ng-if="$root.contactType == 'Phone Call'" ng-model="$root.callStatus" 
						style="margin: 0 0 10px">
					<option>Connection</option>
					<option>VM - Person Confirmed - Message</option>
					<option>VM - Not Confirmed - Message</option>					
					<option>VM - Person Confirmed - No Message</option>
					<option>VM - Not Confirmed - No Message</option>
					<option>Just Ringing</option>
					<option>Bad Number</option>
				</select>
				<br />
				<textarea ng-model="newContact.note"></textarea>
											
				<br /><br />
				<button ng-click="recordContact()">Post</button>
				&nbsp;&nbsp; <button ng-click="recordContact(1)">Post & Next</button>
				
			</div>


			<div class="col-md-6" style="border-left: dashed 1px #ccc;">
				<h2>Log</h2>
				<div ng-repeat="contact in person.contacts">
					<i>{{contact.datetime.split(' ')[0]}}</i> - 
					<a ng-click="deleteContact(contact)">X</a>
					<br /><b>{{contact.type}}</b> 
					<span ng-if="contact.status != ''">
						 - {{contact.status}}
					</span>
					<span ng-if="contact.note != ''">
						 - {{contact.note}}
					</span>
					<span ng-if="contact.amount != 0">
						${{contact.amount}}
					</span>
					<span ng-if="contact.agent != 0">
						<i>- {{contact.agent}}</i>
					</span>
					<br /><br />
				</div>
			</div>
		
		</div>
		
	</div>
</div>

<div id="modal_listManager" style="display: none;">
	<div class="modalFrame clearfix">

		<div class="col-md-6">
			<b>View Mode?</b>
			<br />
			<select ng-model="$root.viewMode">
				<option>addresses</option>
				<option>individuals</option>
				<option>knocknotes</option>
				<option>mark absentees</option>
				<option>multi-sheet</option>								
			</select>
		</div>
		


		<div class="col-md-6">
			<b>Drop a Lit Bomb on this street?</b>
			<br />
			<input 
				type="text" class="form-control" 
				datepicker-popup="shortDate" 
				ng-model="litbomb.date" 
				close-text="Close" 
				placeholder="Enter date..." />
			<br />
			<button ng-click="dropBomb()">DROP!</button>
			
			<br /><br /><br />
			<b>Send Post Cards:</b>
			<br /><button ng-click="sendPostcards()">SEND!</button>
		</div>
		
	</div>
</div>

<div id="modal_personAdder" style="display: none;">
	<div class="modalFrame clearfix">
		<h2>{{$root.mode}} Person 
			<span ng-if="$root.mode == 'Edit'">- <a ng-click="goBack()">Back</a></span>:</h2>

		<div class="col-md-6">
			<label>First Name</label>
			<input ng-model="person.firstname" />
		</div>
		<div class="col-md-6">
			<label>Last Name</label>
			<input ng-model="person.lastname" />
		</div>
		<div class="col-md-12">
			<label>Bio</label>
			<textarea ng-model="person.bio"></textarea>
		</div>
		
		<div class="col-md-6">
			<label>Street Number</label>
			<input ng-model="person.stnum" />
			<br />
			<label>Street Name</label>
			<input ng-model="person.stname1" />
			<br />
			<label>Unit</label>
			<input ng-model="person.unit" />
			<br />
			<label>City</label>
			<input ng-model="person.city" />
			<br />
			<label>State</label>
			<input ng-model="person.state" />
			<br />
			<label>Zip</label>
			<input ng-model="person.zip" />
		</div>
		<div class="col-md-6">
			<label>Support Level</label>
			<select ng-model="person.status">
				<option value="0">0 - Unidentified</option>
				<option value="1">1 - With Us</option>
				<option value="2">2 - Undecided</option>
				<option value="3">3 - Against Us</option>
			</select>		
			<br />
			<label>Party</label>
			<select ng-model="person.enroll">
				<option value="U">Un-enrolled \ Unknown</option>
				<option value="G">Green</option>
				<option value="D">Democrat</option>
				<option value="R">Republican</option>
			</select>		
			<br />
			<label>Year of Birth</label>
			<input ng-model="person.yob" />
			<br />
			<label>Email</label>
			<input ng-model="person.email" />
			<br />
			<label>Phone</label>
			<input ng-model="person.phone" />
			<br />
			<label>Profession</label>
			<input ng-model="person.profession" />
			<br />
			<label>Employer</label>
			<input ng-model="person.employer" />

			<br /><br />
			<button ng-click="savePerson()" class="btn btn-primary">SAVE!</button>
			<button ng-click="savePerson(1)" class="btn btn-primary">SAVE AND CLOSE!</button>			
		</div>
		
		
		
	</div>
</div>
