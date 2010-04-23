<?php
/*    
	This file is part of STFC.
	Copyright 2006-2007 by Michael Krauss (info@stfc2.de) and Tobias Gafner
		
	STFC is based on STGC,
	Copyright 2003-2007 by Florian Brede (florian_brede@hotmail.com) and Philipp Schmidt
	
    STFC is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    STFC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


//########################################################################################
//########################################################################################

/* 23. May 2008
  @Author: Carolfi - Delogu
  @Action: carry on destruction to the galaxy
*/

define (BORG_SIGNATURE, 'We are the Borg. Lower your shields and surrender your ships.<br>We will add your biological and technological distinctiveness to our own.<br>Your culture will adapt to service us. Resistance is futile.');

define (BORG_SPHERE, 'Borg Sphere');

define (BORG_CUBE, 'Borg Cube');

define (BORG_RACE,'6'); // Well, this one should be defined in global.php among the other races */

define (BORG_QUADRANT, 2); // Default Borg belong to Delta quadrant

define (BORG_CYCLE, 3360); // One attack each how many tick?

define (BORG_CHANCE, 80); // Attack is not sistematic, leave a little chance

define (BORG_MINATTACK, 10); // Attack only players with at least n planets

define (BORG_BIGPLAYER, 2000); // Send a cube instead of spheres to player above this points

/* ######################################################################################## */
/* ######################################################################################## */
// Startconfig of Borg
class Borg extends NPC
{
	public function Execute($debug=0)
	{
		$starttime = ( microtime() + time() );

		// Read debug config
		if($debug_data = $this->db->queryrow('SELECT * FROM borg_debug LIMIT 0,1'))
		{
			if($debug_data['debug']==0 || $debug_data['debug']==1)
				$debug=$debug_data['debug'];
		}

		$game = new game();

		$this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
			'<b>Starting Borg Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

		// Bot also enable the life we may need a few more
		$Environment = $this->db->queryrow('SELECT * FROM config LIMIT 0 , 1');
		$ACTUAL_TICK = $Environment['tick_id'];
		$STARDATE = $Environment['stardate'];

		$this->sdl->start_job('SevenOfNine basic system', TICK_LOG_FILE_NPC);

		//Only with adoption Bot has an existence
		if($Environment)
		{
			$this->sdl->log("The conversation with SevenOfNine begins, oh, but I think that there is no possibility to talk with her",
				TICK_LOG_FILE_NPC);
			$Bot_exe=$this->db->query('SELECT * FROM borg_bot LIMIT 0,1');

			// Create BOT table if it doesn't exist
			if($Bot_exe === false)
			{
				$sql = 'CREATE TABLE `'.$this->db->login['database'].'`.`borg_bot` (
				            `id` INT( 2 ) NOT NULL AUTO_INCREMENT ,
				            `user_id` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT \'0\',
				            `planet_id` SMALLINT( 5 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `ship_template1` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `ship_template2` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `user_tick` INT( 10 ) NOT NULL ,
				            `attack_quadrant` TINYINT( 3 ) UNSIGNED NOT NULL DEFAULT  \''.BORG_QUADRANT.'\',
				            `attacked_user1` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `attacked_user2` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `attacked_user3` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `attacked_user4` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `last_attacked` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `wrath_size` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT  \'30\',
				            PRIMARY KEY (  `id` )
				        ) ENGINE = MYISAM';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> cannot create borg_bot table - ABORTED', TICK_LOG_FILE_NPC);
					return;
				}
			}

			$num_bot=$this->db->num_rows($Bot_exe);
			if($num_bot < 1)
			{
				$sql = 'INSERT INTO borg_bot (user_id,user_tick,planet_id,ship_template1,ship_template2)
				        VALUES ("0","0","0","0","0")';
				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Abort the program because of errors when creating the user', TICK_LOG_FILE_NPC);
					return;
				}
			}

			//So now we give the bot some data so that it is also Registered
			$this->bot = $this->db->queryrow('SELECT * FROM borg_bot LIMIT 0,1');

			//Check whether the bot already lives
			if($this->bot['user_id']==0)
			{
				$this->sdl->log('We need to create SevenOfNine', TICK_LOG_FILE_NPC);

				$sql = 'INSERT INTO user (user_active, user_name, user_loginname, user_password, user_email,
				                          user_auth_level, user_race, user_gfxpath, user_skinpath, user_registration_time,
				                          user_registration_ip, user_birthday, user_gender, plz, country,
				                          user_enable_sig,user_message_sig,
				                         user_signature)
				         VALUES (1, "Borg(NPG)", "BorgBot", "'.md5("borgcube").'", "borg@stfc.it",
				                 1, '.BORG_RACE.', "", "skin1/", '.time().',
				                 "127.0.0.1", "23.05.2008", "", 16162 , "Italia",
				                 1, "<br><br><p><b>We are the Borg, resistance is futile</b></p>",
				                 "'.BORG_SIGNATURE.'")';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Bot: Could not create SevenOfNine', TICK_LOG_FILE_NPC);
				}
				else
				{
					$sql = 'Select * FROM user WHERE user_name="Borg(NPG)" and user_loginname="BorgBot" and user_auth_level=1';
					$Bot_data = $this->db->queryrow($sql);
					if(!$Bot_data['user_id'])
					{
						$this->sdl->log('<b>Error:</b> The variable $Bot_data has no content', TICK_LOG_FILE_NPC);
						//break;
					}
					$sql = 'UPDATE borg_bot SET user_id="'.$Bot_data['user_id'].'",user_tick="'.$ACTUAL_TICK.'" WHERE id="'.$this->bot['id'].'"';
					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Bot card: Could not change the card', TICK_LOG_FILE_NPC);
					}
					$this->bot = $this->db->queryrow('SELECT * FROM borg_bot');
				}
			} // end user bot creation

			//The bot should also have a body of what looks
			if($this->bot['planet_id']==0)
			{
				$this->sdl->log('<b>SevenOfNine needs new body</b>', TICK_LOG_FILE_NPC);

				while($this->bot['planet_id']==0 or $this->bot['planet_id']=='empty')
				{
					$this->sdl->log('Create new planet', TICK_LOG_FILE_NPC);
					$this->db->lock('starsystems_slots');
					$this->bot['planet_id']=create_planet($this->bot['user_id'], 'quadrant', BORG_QUADRANT);
					$this->db->unlock();
					if($this->bot['planet_id'] == 0)
					{
						$this->sdl->log('<b>Error:</b> Bot Planet id doesn\'t go', TICK_LOG_FILE_NPC);
						return;
					}

					$sql = 'UPDATE user SET user_points = "400",user_planets = "1",last_active = "5555555555",
					                        user_attack_protection = "'.($ACTUAL_TICK + 14400).'",
					                        user_capital = "'.$this->bot['planet_id'].'",
					                        active_planet = "'.$this->bot['planet_id'].'"
					        WHERE user_id = '.$this->bot['user_id'];

					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Bot body: Planet has not been created', TICK_LOG_FILE_NPC);
					}
					else
					{
						//Bot gets best values for his body, he should also look good
						$this->sdl->log('Give best values to the planet', TICK_LOG_FILE_NPC);
						$sql = 'UPDATE planets SET planet_points = 1200,building_1 = 9,building_2 = 15,building_3 = 15,
							building_4 = 15,building_5 = 16,building_6 = 9,building_7 = 15,building_8 = 9,
							building_9 = 9,building_10 = 35,building_11 = 9,building_12 = 15,building_13 = 35,
							unit_1 = 20000,unit_2 = 20000,unit_3 = 20000,unit_4 = 5000,unit_5 = 5000,unit_6=5000,
							planet_name = "Unimatrix Zero",
							research_1 = 15,research_2 = 15,research_3 = 15,research_4 = 15,research_5 = 9,
							workermine_1 = 1600,workermine_2 = 1600,workermine_3 = 1600,resource_4 = 4000
							WHERE planet_owner = '.$this->bot['user_id'].' and planet_id='.$this->bot['planet_id'];

						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> Bot body: the body could not be improved', TICK_LOG_FILE_NPC);

						$sql = 'UPDATE borg_bot SET planet_id='.$this->bot['planet_id'].' WHERE user_id = '.$this->bot['user_id'];
						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> Bot card: could not change planet info card', TICK_LOG_FILE_NPC);
					}
				} // end while
			} // end planet creation
			// Check ownership of the BOT's planet
			else {
				$sql = 'SELECT planet_owner FROM planets
					        WHERE planet_id = '.$this->bot['planet_id'];

				$botplanetowner = $this->db->queryrow($sql);
				// Owner are still BORG?
				if($botplanetowner['planet_owner'] != $this->bot['user_id'])
				{
					// The wrath of Borgs begin
					$this->sdl->log("SevenOfNine has lost her homeplanet, her wrath begins!",TICK_LOG_FILE_NPC);

					$this->sdl->log('The User '.$botplanetowner['planet_owner'].' will have a bad day', TICK_LOG_FILE_NPC);

					// Choose a target
					$sql='SELECT p.planet_owner,p.planet_name,p.planet_id,u.user_points FROM (planets p)
					      INNER JOIN (user u) ON u.user_id = p.planet_owner
					      WHERE p.planet_owner ='.$botplanetowner['planet_owner'].' LIMIT 0 , 1';
					$target=$this->db->queryrow($sql);

					// Check if a fleet is already on fly
					$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
					        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "'.$target['planet_name'].'"
					        LIMIT 0,1';
					$fleet = $this->db->queryrow($sql);

					// If the fleet does not exists
					if(empty($fleet['fleet_id'])) {
						// Create a new fleet
						$fleet_id = $this->CreateFleet($target['planet_name'],$this->bot['ship_template2'],
							$this->bot['wrath_size']);

						// Increase wrath size
						$sql = 'UPDATE borg_bot SET wrath_size = wrath_size + 30';
						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> cannot increase Borg wrath', TICK_LOG_FILE_NPC);

						// Send it to the planet
						$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $target['planet_id']);
					}
					// If the fleet exists but it is not moving and it is not at planet
					else if($fleet['planet_id'] != $target['planet_id'] && $target['move_id'] == 0) {
						// Send it to the planet
						$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $target['planet_id']);
					}

					// Now think to reconquer the homeplanet
					$sql='SELECT p.planet_owner,p.planet_name,p.planet_id,u.user_points FROM (planets p)
					      INNER JOIN (user u) ON u.user_id = p.planet_owner
					      WHERE p.planet_id ='.$this->bot['planet_id'];
					$target=$this->db->queryrow($sql);

					// Check if a fleet is already on fly
					$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
					        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "Borg Wrath"
					        LIMIT 0,1';
					$fleet = $this->db->queryrow($sql);

					// If the fleet does not exists
					if(empty($fleet['fleet_id'])) {
						// Create a new fleet
						$fleet_id = $this->CreateFleet("Borg Wrath",$this->bot['ship_template2'],30);

						//$sql='SELECT planet_id FROM planets
						//      WHERE planet_owner ='.$this->bot['user_id'].' LIMIT 0 , 1';
						//$start=$this->db->queryrow($sql);

						// Fix the origin since the bot has lost is home planet
						//$sql = 'UPDATE ships_fleets SET planet_id = '.$start['planet_id'].' WHERE fleet_id = '.$fleet_id;
						//if(!$this->db->query($sql))
						//	$this->sdl->log('<b>Error:</b> Cannot update Borg wrath fleet data', TICK_LOG_FILE_NPC);

						// Send it to the planet
						$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $this->bot['planet_id']);
					}
					// If the fleet exists but it is not moving and it is not at planet
					else if($fleet['planet_id'] != $this->bot['planet_id'] && $target['move_id'] == 0) {

						// Borg are tired?
						if($this->bot['wrath_num'] <= 1)
						{
							// Send it to the planet
							$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $this->bot['planet_id']);

							// Increase wrath num
							$sql = 'UPDATE borg_bot SET wrath_num = wrath_num + 1';
							if(!$this->db->query($sql))
								$this->sdl->log('<b>Error:</b> cannot increase wrath num', TICK_LOG_FILE_NPC);
						}
					}
				}
				else
				{
					$this->sdl->log("SevenOfNine hasn't lost her homeplanet.",TICK_LOG_FILE_NPC);

					// Set wrath size at default value
					$sql = 'UPDATE borg_bot SET wrath_size = 30';
					if(!$this->db->query($sql))
						$this->sdl->log('<b>Error:</b> cannot restore Borg wrath', TICK_LOG_FILE_NPC);
				}
			}

			//Bot shows whether the ship already has templates
			$reload=0;
			if($this->bot['ship_template1']==0)
			{
				/**
				 * Brief comments of SECOND prototype of Borg Sphere:
				 *
				 * Light weapons: 600
				 * Heavy weapons: 600
				 * Planetary weapons: 50
				 * Hull: 700
				 * Shield: 700
				 *
				 * Reaction: 30
				 * Readiness: 30
				 * Agility: 30
				 * Experience: 20
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 20
				 * Camouflage: 0
				 * Energy available: 200
				 * Energy used: 200
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 50000
				 * Minerals: 50000
				 * Dilithium: 50000
				 * Workers: 500
				 * Technicians: 100
				 * Physicians: 10
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 100
				 * Assault drone: 25
				 * Elite drone: 25
				 * Commander drone: 5
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 300
				 * Assault drone: 70
				 * Elite drone: 50
				 * Commander drone: 10
				 */ 
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				         VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_SPHERE.'","Exploration ship","'.BORG_RACE.'",6,2,
				                 -1,-1,-1,-1,-1,
				                 -1,-1,-1,-1,-1,
				                 "600","600","50","700","700",
				                 "30","30","30","20","10",
				                 "20","0","200","200","0",
				                 "50000","50000","50000","500","100","10",
				                 "100","25","25","5",
				                 "300","70","50",10,
				                 0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 1 was not saved', TICK_LOG_FILE_NPC);
			}
			if($this->bot['ship_template2']==0)
			{
				/**
				 * Brief comments of SECOND prototype of Borg Cube:
				 *
				 * Light weapons: 6000
				 * Heavy weapons: 6000
				 * Planetary weapons: 400
				 * Hull: 20000
				 * Shield: 7000
				 *
				 * Reaction: 60
				 * Readiness: 60
				 * Agility: 10
				 * Experience: 60
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 40
				 * Camouflage: 0
				 * Energy available: 2000
				 * Energy used: 2000
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 500000
				 * Minerals: 500000
				 * Dilithium: 500000
				 * Workers: 50000
				 * Technicians: 10000
				 * Physicians: 1000
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 10000
				 * Assault drone: 2500
				 * Elite drone: 2500
				 * Commander drone: 500
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 30000
				 * Assault drone: 7000
				 * Elite drone: 5000
				 * Commander drone: 1000
				 */
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				        VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_CUBE.'","Assimilation ship","'.BORG_RACE.'",11,3,
				                -1,-1,-1,-1,-1,
				                -1,-1,-1,-1,-1,
				                "6000","6000","400","20000","7000",
				                "60","60","10","60","10",
				                "40","0","2000","2000","0",
				                "500000","500000","500000","50000","10000","1000",
				                "10000","2500","2500","500",
				                "30000","7000","5000","1000",
				                0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 2 was not saved', TICK_LOG_FILE_NPC);
			}

			if($reload>0)
			{
				$this->sdl->log('Ship templates built', TICK_LOG_FILE_NPC);
				$Bot_temps=$this->db->query('SELECT id FROM ship_templates s WHERE owner='.$this->bot['user_id']);
				$zaehler_temps=0;
				$Bot_neu = array();

				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[0]=$tempID['id'];
				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[1]=$tempID['id'];


				$sql = 'UPDATE borg_bot SET ship_template1 = '.$Bot_neu[0].',ship_template2 = '.$Bot_neu[1].' WHERE user_id = '.$this->bot['user_id'];
				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: could not save the template id', TICK_LOG_FILE_NPC);
			}
		}else{
			$this->sdl->log('<b>Error:</b> No access to environment table!', TICK_LOG_FILE_NPC);
			return;
		}
		$this->sdl->finish_job('SevenOfNine basic system', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		//PW des Bot änderns - nix angreifen
		$this->ChangePassword();
		// ########################################################################################
		// ########################################################################################
		// Messages answer
		$messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
		$titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

		$this->ReplyToUser($titles,$messages);
		// ########################################################################################
		// ########################################################################################
		//Sensors monitoring and user warning
		$messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
		$titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

		//$this->CheckSensors($ACTUAL_TICK,$titles,$messages);

		/**
		 * 13/11/08 - AC: Stop sending ONLY messages to nasty players! ^^
		 */
		$this->sdl->start_job('Sensors monitor', TICK_LOG_FILE_NPC);
		$msgs_number=0;
		$sql='SELECT * FROM `scheduler_shipmovement`
		      WHERE user_id>9 AND 
		            move_status=0 AND
		            move_exec_started!=1 AND
		            move_finish>'.$ACTUAL_TICK.' AND
		            dest="'.$this->bot['planet_id'].'"';
		$attackers=$this->db->query($sql);
		while($attacker = $this->db->fetchrow($attackers))
		{
			$this->sdl->log('The User '.$attacker['user_id'].' is trying to attack bot planet', TICK_LOG_FILE_NPC);
			$send_message = false;

			// Choose a target
			$sql='SELECT p.planet_owner,p.planet_name,p.planet_id,u.user_points FROM (planets p)
			      INNER JOIN (user u) ON u.user_id = p.planet_owner
			      WHERE p.planet_owner ='.$attacker['user_id'].' LIMIT 0 , 1';
			$target=$this->db->queryrow($sql);

			// Check if a fleet is already on fly
			$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
			        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "'.$target['planet_name'].'"
			        LIMIT 0,1';
			$fleet = $this->db->queryrow($sql);

			// If the fleet does not exists
			if(empty($fleet['fleet_id'])) {
				// Create a new fleet
				if($target['user_points'] > BORG_BIGPLAYER)
					$fleet_id = $this->CreateFleet($target['planet_name'],$this->bot['ship_template2'],1);
				else
					$fleet_id = $this->CreateFleet($target['planet_name'],$this->bot['ship_template1'],3);

				// Send it to the planet
				$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $target['planet_id']);
				$send_message = true;
			}
			// If the fleet exists but it is not moving and it is not at planet
			else if($fleet['planet_id'] != $target['planet_id'] && $target['move_id'] == 0) {
				// Send it to the planet
				$send_message = $this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $target['planet_id']);
			}

			if($send_message) {
				$msgs_number++;

				// Recover language of the sender
				$sql = 'SELECT language FROM user WHERE user_id='.$attacker['user_id'];
				if(!($language = $this->db->queryrow($sql)))
					$this->sdl->log('<b>Error:</b> Cannot read user language!',
						TICK_LOG_FILE_NPC);

				switch($language['language'])
				{
					case 'GER':
						$text=$messages[1];
						$title=$titles[1];
					break;
					case 'ITA':
						$text=$messages[2];
						$title=$titles[2];
					break;
					default:
						$text=$messages[0];
						$title=$titles[0];
					break;
				}

				$this->MessageUser($this->bot['user_id'],$attacker['user_id'],$title,
					str_replace("<TARGETPLANET>",$target['planet_name'],$text));
			}
		}
		$this->sdl->log('Number of "messages" sent:'.$msgs_number, TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Sensors monitor', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################
		//Ships creation
		$this->sdl->start_job('Creating ships', TICK_LOG_FILE_NPC);
		$this->CreateFleet('Borg spheres',$this->bot['ship_template1'],3);
		$this->RestoreFleetLosses("Borg spheres",$this->bot['ship_template1'],3);
		$this->CreateFleet('Borg cube',$this->bot['ship_template2'],1);
		$this->sdl->finish_job('Creating ships', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		//Fleets's crew creation

		// Actually put simply the troops aboard
		$sql = 'UPDATE `ship_fleets` SET `unit_1` = 5000, `unit_2` = 2500, `unit_3` = 1250, `unit_4` = 125
		        WHERE `fleet_name`="Borg cube" AND `user_id` = '.$this->bot['user_id'];
		if(!$this->db->query($sql))
			$this->sdl->log('<b>Warning:</b> cannot update Borg cube crew!', TICK_LOG_FILE_NPC);

		$sql = 'UPDATE `ship_fleets` SET `unit_1` = 1000, `unit_2` = 500, `unit_3` = 250, `unit_4` = 25
		        WHERE `fleet_name`="Borg spheres" AND `user_id` = '.$this->bot['user_id'];
		if(!$this->db->query($sql))
			$this->sdl->log('<b>Warning:</b> cannot update Borg spheres crew!', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################
		// Send ships
		$this->sdl->start_job('Assimilate planets', TICK_LOG_FILE_NPC);

		// First of all check how much time has been elapsed from the previous attack
		if($ACTUAL_TICK > ($this->bot['user_tick'] + BORG_CYCLE)) {
			$sql = 'UPDATE borg_bot SET user_tick="'.$ACTUAL_TICK.'" WHERE id="'.$this->bot['id'].'"';
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Warning:</b> cannot update bot user_tick!', TICK_LOG_FILE_NPC);

			// Give the user a chance not to be attacked
			if(rand(0,100) <= BORG_CHANCE) {
				// Select BORG home planet
				$sql = 'SELECT s.system_global_x, s.system_global_y
				        FROM (planets p)
				        INNER JOIN (starsystems s) ON s.system_id = p.system_id
				        WHERE p.planet_id = '.$this->bot['planet_id'];

				$unimtx0 = $this->db->queryrow($sql);

				// Filter last four attacked users
				$filter = '';
				if($this->bot['last_attacked'] > 0 && $this->bot['last_attacked'] < 4)
				{
					$skip_id = array();
					for($i = 0; $i < $this->bot['last_attacked']; ++$i) {
						$skip_id[$i] = $this->bot['attacked_user'.($i+1)];
					}
					$filter = 'u.user_id NOT IN ('.implode(',', $skip_id).') AND';
				}
				// Reset attacked counter
				else if($this->bot['last_attacked'] >= 4)
					$this->bot['last_attacked'] = 0;

				// Now select the target...
				$sql = 'SELECT p.planet_id, s.system_global_x, s.system_global_y,
				               u.user_points, u.user_id
				        FROM (planets p)
				        INNER JOIN (starsystems s) ON s.system_id = p.system_id
				        INNER JOIN (user u) ON u.user_id = p.planet_owner
				        WHERE u.user_planets > '.BORG_MINATTACK.' AND
				              u.user_vacation_end < '.$ACTUAL_TICK.' AND
				              p.planet_owner <> '.$this->bot['user_id'].' AND
				              '.$filter.'
				              CEIL(p.sector_id / 81) = '.$this->bot['attack_quadrant'].'
				              ORDER BY p.planet_id ASC';

				$targets = $this->db->query($sql);

				// Select the nearest planet
				$min_distance = 10000000;
				while($target = $this->db->fetchrow($targets)) {
					$distance = get_distance(
						array($unimtx0['system_global_x'], $unimtx0['system_global_y']),
						array($target['system_global_x'], $target['system_global_y'])
					);
					if($distance < $min_distance)
					{
						$min_distance = $distance;
						$chosen_target = $target;
					}
				}

				$this->sdl->log('Chosen target is planet: <b>'.$chosen_target['planet_id'].'</b> of user: <b>'.$chosen_target['user_id'].'</b> at distance: <b>'.$min_distance.'</b> from BOT planet',TICK_LOG_FILE_NPC);

				// Select appropriate fleet
				if($chosen_target['user_points'] > BORG_BIGPLAYER)
					$sql = 'SELECT `fleet_id` FROM `ship_fleets`
					        WHERE `fleet_name`="Borg cube" AND `user_id` = '.$this->bot['user_id'].' AND `planet_id` <> 0
					        LIMIT 0,1';
				else
					$sql = 'SELECT `fleet_id` FROM `ship_fleets`
					        WHERE `fleet_name`="Borg spheres" AND `user_id` = '.$this->bot['user_id'].' AND `planet_id` <> 0
					        LIMIT 0,1';

				$fleet = $this->db->queryrow($sql);

				if(!empty($fleet['fleet_id']) && !empty($chosen_target['planet_id'])) {
					$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'],$chosen_target['planet_id']);
					$this->sdl->log('Borg fleet #'.$fleet['fleet_id'].' sent to planet #'.$chosen_target['planet_id'],
						TICK_LOG_FILE_NPC);

					// Next time attack another quadrant
					$this->bot['attack_quadrant']++;
					if($this->bot['attack_quadrant'] > 4)
						$this->bot['attack_quadrant'] = 1;

					$sql = 'UPDATE borg_bot SET
					               attack_quadrant = '.$this->bot['attack_quadrant'].',
					               attacked_user'.($this->bot['last_attacked']+1).' = '.$chosen_target['user_id'].',
					               last_attacked = last_attacked + 1
					        WHERE id="'.$this->bot['id'].'"';
					if(!$this->db->query($sql))
						$this->sdl->log('<b>Warning:</b> cannot update bot attack_quadrant!',
							TICK_LOG_FILE_NPC);
				}
				else {
					$this->sdl->log('No fleets or no targets available!', TICK_LOG_FILE_NPC);
				}
			}
			else
				$this->sdl->log('Today the galaxy is safe!', TICK_LOG_FILE_NPC);
		}

		$this->sdl->finish_job('Assimilate planets', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		// Create defences for BOT planets

		$this->sdl->start_job('Create Borg defences on assimilated planets', TICK_LOG_FILE_NPC);

		// We need many infos here, for StartBuild() function
		$sql = 'SELECT * FROM planets WHERE planet_owner = '.$this->bot['user_id'];

		$planets = $this->db->query($sql);

		// Select each planet
		while($planet = $this->db->fetchrow($planets)) {
			// Build some orbital guns
			if($planet['building_10'] < 15) {
				$res = $this->StartBuild($ACTUAL_TICK,9,$planet);
				if($res == BUILD_ERR_ENERGY)
					$res = $this->StartBuild($ACTUAL_TICK,4,$planet);
			}
			if($planet['building_13'] < 15) {
				$res = $this->StartBuild($ACTUAL_TICK,12,$planet);
				if($res == BUILD_ERR_ENERGY)
					$res = $this->StartBuild($ACTUAL_TICK,4,$planet);
			}

			$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
			        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "'.$planet['planet_name'].'"
			        LIMIT 0,1';
			$fleet = $this->db->queryrow($sql);

			// If the fleet does not exists
			if(empty($fleet['fleet_id'])) {
				// Create a new fleet
				$fleet_id = $this->CreateFleet($planet['planet_name'],$this->bot['ship_template2'],1);

				// Update alarm status
				$sql = 'UPDATE ship_fleets SET alert_phase = '.ALERT_PHASE_RED.'
				        WHERE fleet_id = '.$fleet_id;
				if(!$this->db->query($sql))
					$this->sdl->log('<b>Warning:</b> cannot update fleet alarm status to RED!',
						TICK_LOG_FILE_NPC);

				// Send it to the planet
				$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $planet['planet_id'],11);
			}
			// If the fleet exists but it is not moving and it is not at planet
			else if($fleet['planet_id'] != $planet['planet_id'] && $fleet['move_id'] == 0) {
				// Send it to the planet
				$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $planet['planet_id'],11);
			}
		}

		$this->sdl->finish_job('Create Borg defences on assimilated planets', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################

		$this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
	}

	function SendBorgFleet($ACTUAL_TICK,$fleet_id,$dest,$action = 46) {

		$sql = 'SELECT f.fleet_id, f.user_id, f.n_ships, f.planet_id AS start,
		           s1.system_id AS start_system_id, s1.system_global_x AS start_x, s1.system_global_y AS start_y,
		           s2.system_id AS dest_system_id, s2.system_global_x AS dest_x, s2.system_global_y AS dest_y
		    FROM (ship_fleets f)
		    INNER JOIN (planets p1) ON p1.planet_id = f.planet_id
		    INNER JOIN (starsystems s1) ON s1.system_id = p1.system_id
		    INNER JOIN (planets p2) ON p2.planet_id = '.$dest.'
		    INNER JOIN (starsystems s2) ON s2.system_id = p2.system_id
		    WHERE f.fleet_id = '.$fleet_id;

		if(($fleet = $this->db->queryrow($sql)) === false) {
			$this->sdl->log('Could not query ship data',TICK_LOG_FILE_NPC);
			return false;
		}

		if(empty($fleet['fleet_id'])) {
			$this->sdl->log('Borg fleet for mission does not exist, already moving?',TICK_LOG_FILE_NPC);
			return false;
		}

		if($fleet['start_system_id'] == $fleet['dest_system_id']) {
			$distance = $velocity = 0;
			$min_time = 6;
		}
		else {
			$distance = get_distance(array($fleet['start_x'], $fleet['start_y']), array($fleet['dest_x'], $fleet['dest_y']));
			$velocity = warpf(10);
			$min_time = ceil( ( ($distance / $velocity) / TICK_DURATION ) );
		}

		if($min_time < 1) $min_time = 1;

		$sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
		         VALUES ('.$fleet['user_id'].', 0, 0, '.$fleet['start'].', '.$dest.', '.$distance.', '.$distance.', '.($velocity * TICK_DURATION).', '.$ACTUAL_TICK.', '.($ACTUAL_TICK + $min_time).', '.$fleet['n_ships'].', '.$action.', "")';

		if(!$this->db->query($sql)) {
			$this->sdl->log('Could not insert new movement data',TICK_LOG_FILE_NPC);
			return false;
		}

		$new_move_id = $this->db->insert_id();

		if(empty($new_move_id)) {
			$this->sdl->log('Could not send Borg fleet $new_move_id = empty',TICK_LOG_FILE_NPC);
			return false;
		}

		$sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$new_move_id.' WHERE fleet_id = '.$fleet['fleet_id'];

		if(!$this->db->query($sql)) {
			$this->sdl->log('Could not update Borg fleet data',TICK_LOG_FILE_NPC);
			return false;
		}

		return true;
	}

}


?>
