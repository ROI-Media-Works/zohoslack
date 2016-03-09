<?php
		
	// example request to get records - https://support.zoho.com/api/json/requests/getrecords?authtoken=80d8*****&portal=PORTALNAME&department=DEPARTMENTNAME
	
	// example request to get request threads - https://support.zoho.com/api/xml/requests/getrequestthreads?authtoken=80d8*****&portal=PORTALNAME&department=DEPARTMENTNAME&requestid=CASEID&needdescription=true
	
	// example request to get thread info - https://support.zoho.com/api/xml/requests/getthreadinfo?authtoken=80d8*****&portal=PORTALNAME&department=DEPARTMENTNAME&threadid=THREADID
	
	
	date_default_timezone_set('America/Vancouver');
	define("START_TIME", "8"); // aka 8 am
	define("PAUSE_TIME", "17"); // aka 5 pm
	$current_time = date('H');
	
	if($current_time >= START_TIME && $current_time < PAUSE_TIME) {
		checkTickets();
	}
	
	
	// checking new tickets on Zoho Support
	function checkTickets() {
		
		// variables initialisation 
		$token = "zoho_token";
		$portal = "zoho_portal";
		$department = "zoho_department";
		$get_records = "https://support.zoho.com/api/json/requests/getrecords?authtoken={$token}&portal={$portal}&department={$department}";
		$prefix = "https://support.zoho.com";
		$id_file = "zoho_last_id.txt"; // name of the file last ticket ID is stored at
		$prev_id = $last_id = intval(file_get_contents($id_file)); // last ticket ID
		
		// sending request to get records
		$records = sendCurl($get_records);
		
		// decompiling response into needed records
		foreach(($records -> {"response"} -> {"result"} -> {"Cases"} -> {"row"}) as $ticket) {
			
			$current_id = $ticket -> {"fl"} [9] -> {"content"}; 
			$contact_name = $ticket -> {'fl'} [5] -> {"content"}; 
			$contact_email = $ticket -> {'fl'} [6] -> {"content"}; // can be empty!
			$ticket_subject = $ticket -> {'fl'} [8] -> {"content"};
			$ticket_link = $ticket -> {'fl'} [1] -> {"content"}; 	
			$case_id = $ticket -> {'fl'}[0] -> {"content"};
			
			// compiling request to recieve thread on each ticket
			$get_request_threads = "https://support.zoho.com/api/json/requests/getrequestthreads?authtoken={$token}&portal={$portal}&department={$department}&requestid={$case_id}&needdescription=true";
		
			$threads = sendCurl($get_request_threads);
		
			// extracting thread id from response
			$thread_id = $threads -> {"response"} -> {"result"} -> {"Cases"} -> {"threadinfo"} -> {'fl'}[0] -> {'content'};
			
			// compiling request to recieve detailed info on thread
		 	$get_thread_info = "https://support.zoho.com/api/json/requests/getthreadinfo?authtoken={$token}&portal={$portal}&department={$department}&threadid={$thread_id}";
		 	
			$thread_info = sendCurl($get_thread_info);
			
			// extracting ticket body 
			$ticket_content = $thread_info -> {"response"} -> {"result"} -> {"Cases"} -> {"threadinfo"} -> {"fl"}[5] -> {"content"};	
			
			// checking if ticket is new or not
			if ($current_id > $last_id) {
				
				if ($contact_email != "null" && $contact_email != null) {
				  $contact_name .= " ({$contact_email})";
				}
	
				// composing message
				$message = array(array(
				  "fallback"    =>	"New ticket from {$contact_name} - Ticket #{$current_id}: {$ticket_subject} - {$prefix}{$ticket_link}",
				  "pretext"     =>	"New ticket from {$contact_name} ",
			          "title"       =>	"Ticket #{$current_id}: {$ticket_subject}",
			          "title_link"  =>	"{$prefix}{$ticket_link}",
			          "text"        =>	"{$ticket_content}",
			          "color"       =>	"#7CD197"
				));
					
				sendMessage($message);
				
				// updating record with id if the newest ticket				
				if ($current_id > $prev_id ) {
				file_put_contents($id_file, $current_id); // storing ID of the newest ticket
				$prev_id = $current_id;
				}
			}
		}
	}
	
	
	// sending curl requests
	function sendCurl($request) {
		
		// curl to zoho server 
		$curl = curl_init($request);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);
		curl_close($curl);
		
		// decoding response - analyzing json
		$curl_response = json_decode($curl_response);
		
		return $curl_response;
	}
	
	
	// composing and sending message to Slack
	function sendMessage($message) {
		
		// variables initialisation 
		$hook_link = "slack_hook";
		$channel = "#_general";
		
		// compiling json | sending message as attachments for richer formatting
		$data = json_encode(array(
		        "channel"	=>	$channel,
		        "username"	=>	"Zoho Support",
		        "icon_emoji"	=>	":zoho_support:",
		        "attachments"	=>	$message
		));
		
		$ch = curl_init($hook_link);
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		$result = curl_exec($ch);
		curl_close($ch);
	}
