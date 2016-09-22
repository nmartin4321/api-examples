<?php
// This script will create a bulk list of new behaviors based on JSON file input
// Be sure to create an auth file (php or prefered method)

//Dissabled buffering for large data sets:
header( 'Content-type: text/html; charset=utf-8' );

// // Turn off output buffering
// ini_set('output_buffering', 'off');
// // Turn off PHP output compression
// ini_set('zlib.output_compression', false);
ob_implicit_flush(true);
//set up flush()
if (ob_get_level() == 0) ob_start();

//start time
$startTime = microtime(true);

//Get ticket location:
function curl_tgTicketLocation($session, $authenticationUrl, $postArgs) {
	// initialize the curl session to the authorization URL
	$session = curl_init();

	// set up our curl options for posting the args and make sure we get the header.
	curl_setopt($session, CURLOPT_URL, $authenticationUrl);
	curl_setopt($session, CURLOPT_POST, true);
	curl_setopt($session, CURLOPT_POSTFIELDS, $postArgs);
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($session, CURLOPT_HEADER, true);
	curl_setopt($session, CURLOPT_VERBOSE, 1);
	curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($session, CURLOPT_HTTPHEADER, array ('Accept: ' . 'text/plain', 'Content-type:' . 'application/x-www-form-urlencoded'));

	# Now, let's make our Ticket Granting Ticket request.  We get the location from the response header
	$response = curl_exec($session);

	// print_r("The Raw Resp: ".$response."<br/>");

	// Get the Location out of the response and set tgTicketLocation
	preg_match("!\r\n(?:Location|URI): *(.*?) *\r\n!", $response, $matches);
	$tgTicketLocation = $matches[1];

	# close the session
	curl_close($session);

	if (!$response){
		echo ("No token returned!");
		?><br><?php
		//timing
		$totalTime = microtime(true) - $startTime;
		echo ("Time to complete without data: $totalTime seconds");
		exit;
	}else{
		// You can see the form of the token here
		echo ("my ticket location is: ".$tgTicketLocation." !\n<br />");	
	}
	return $tgTicketLocation;
}

//Get services ticekt for and make Creation API call
function curl_behaviorCreate($session, $serviceTicketLoc, $behaviorCreate, $serviceCall){

	// Add the service for the POST args (This is to get the ticket for the service in question)
	$postArgs = 'service='. urlencode($serviceCall);
	// print_r($postArgs);
	// die;


	// initialize the curl session to the serviceTicketLoc
	$session = curl_init($serviceTicketLoc);

	// Set our opts
	curl_setopt($session,CURLOPT_HTTPHEADER,array('Accept: application/json'));
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($session, CURLOPT_POSTFIELDS, $postArgs);
	curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);

	// Execute the request and get our service ticket
	$serviceTicket = curl_exec($session);
	
	// Close the session
	curl_close($session);
	
	// Now that we have the ticket, let's mate the behavior create call
	// Iniliaze the curl session, making sure to pass the Service Ticket
	// using '&' to allow the concatenation of a string that already has '?'
	// print_r($serviceCall . '&ticket=' . $serviceTicket);
	// die;
	$session = curl_init( $serviceCall . '&ticket=' . $serviceTicket );
	curl_setopt($session,CURLOPT_HTTPHEADER, array('Accept: application/json','Content-type: application/json'));
	curl_setopt($session, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($session, CURLOPT_POSTFIELDS, $behaviorCreate);
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($session, CURLOPT_VERBOSE, 1);
	curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);

	// Get our behavior information back.
	$created = curl_exec($session);

	// Close the session
	curl_close($session);

	return $created;
}

// Include path to authentication file with our username and password definitions
include '../../auth.php';

// urlencode the post args
$postArgs = 'username='.urlencode($username).'&password='.urlencode($password);

// initialize the curl session to the authorization URL
$session = curl_init();

//Get our Ticket Location
$serviceTicketLoc = curl_tgTicketLocation($session, $authenticationUrl, $postArgs);

/*
Now we have our Ticket Granting Ticket, set our service ticket for the service we want to call
The service call will be to create a behavior from a list (raw or file).
This one set to use Aliases
*/
$serviceCall = $baseApiUrl . 'behaviors?use_aliases=true';

// Begin working through the data
//Get the file and start working with it:
$beh_file = "json/demo_behavior_set.json";
$fh = file_get_contents($beh_file);
$fh = json_decode($fh);
$behavior_array = $fh->behaviors;

// print_r($behavior_array);
// die;
$updateListArr = array();
$x = 1;

for($i = 0; $i < count($behavior_array); $i++) {	
	//echo $behaviorIds_array[$i]."</br>";
	$behaviorCreate = json_encode($behavior_array[$i]);
	$x = $x++;
	// echo "<br/>";
	// print_r($behaviorCreate);
	// // die;

	//Run the get behavior
	$created_beh = curl_behaviorCreate($session, $serviceTicketLoc, $behaviorCreate, $serviceCall); 
	echo "behavior: ".$created_beh."<br />";
	// print_r($created_beh);
	// die;

	if ($x > 0 && $x % 10 == 0) {
		// echo str_pad("",1024," ");
		echo("Completed ".$x." behavior creations so far....sleep 1 second...<br />");
		$stopwatch = microtime(true) - $startTime;
		echo ("\nTime to complete: $stopwatch seconds <br />");
		echo " <br />"; //BROWSER TWEAKS
		ob_flush();
		flush();
		ob_end_flush();
		sleep(1);
	}	
}

// Spit out the last update
echo "<br/>The last update was: ". $created_beh;
echo " <br />"; //BROWSER TWEAKS
ob_flush();
flush();
ob_end_flush();

// close the session
curl_close($session);

// for($x = 0; $x < count($updateListArr); $x++) {	
// 	echo "</br>".$updateListArr[$x];
// }

// We were just timing for fun, let's see how long it took
$totalTime = microtime(true) - $startTime;

?><br><?php
echo ("\n</br></br>Time to complete: $totalTime seconds\n");
// Once we are done with the Ticket Granting Ticket we should clean it up'
// Initialize the session
$session = curl_init($serviceTicketLoc);

// We want to delete ...
curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);

// Execute the request
curl_exec($session);

// Display the results
echo ( "<br/><br/>Status for closing TGT: " . curl_getInfo($session, CURLINFO_HTTP_CODE) . "\n");

?>