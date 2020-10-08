<?php
/*
	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	error_reporting(-1);
*/

require_once('../php/util/load_wp.php');
require_once('../php/seatreg_functions.php');
require_once('../php/libs/tfpdf/tfpdf.php');

seatreg_bookings_is_user_logged_in(); 

$showWhat = 'all';

if(!isset($_GET['s2']) && isset($_GET['s1'])) {
	$showWhat = 'pending';
}

if(!isset($_GET['s1']) && isset($_GET['s2'])) {
	$showWhat = 'confirmed';
}

if(empty($_GET['zone'])) {
	echo 'Timezone is missing';
	exit();
}

$registrationInfo = seatreg_get_options($_GET['v'])[0];

//print_r($registrationInfo);
//echo $registrationInfo->registration_name;
//exit();

$registrations = seatreg_get_data_for_booking_file($_GET['v'], $showWhat);

//print_r($registrations);
//exit();

$projectName = $registrationInfo->registration_name;
$customFields = json_decode($registrationInfo->custom_fields, true);
$customFieldsCount = count($customFields);
$regLen = count($registrations);

//echo count($customFields[0]);
//echo '<pre>', print_r($customFields), '</pre>';
//echo $customFields[0]['label'];
//echo '<pre>', print_r($registrations), '</pre>';
//echo '<pre>', print_r($registrationInfo), '</pre>';

function customFieldWithValuePDF($label, $custom_data) {
	$cust_len = count($custom_data);
	//echo 'Custom field length: ',$cust_len, '<br>';
	$foundIt = false;
	$string = $label . ': ';
	
	//echo '-----Alustan otsinguga----<br>';
	for($k = 0; $k < $cust_len; $k++) {
		//echo 'Otsin: ', $label, '<br>';
		//echo 'Leidsin: ', $custom_data[$k]['label'], '<br>';
		if($custom_data[$k]->label == $label) {
			//echo 'Match leitud!!!!!!!!!!!!!!';
			if($custom_data[$k]->value === true) {
				$string .= 'Yes';
			}else if($custom_data[$k]->value === false) {
				$string .= 'No';
			}else {
				$string .= $custom_data[$k]->value;
			}
			$foundIt = true;
			break;
		}else {
			//echo 'Label: ',$label, 'ei võrdu: ',$custom_data[$k]['label'], '<br />';
			//echo 'Ei olnud match<br></br>';
		}
	}

	if(!$foundIt) {
		$string .= ' not set';
	}

	return $string;
}

class PDF extends tFPDF {
	// Page header
	function Header() {
		$this->SetFont('Arial','B',14);
		// Logo
		//$this->Cell(30,0,'SeatReg.com',0,0,'C');
		$this->Image('../img/seatreg_logo.png',9,5,30);
		// Arial bold 15
		
		// Move to the right
		$this->Cell(70);
		// Title
		$this->Cell(30,20,$GLOBALS['projectName'],0,0,'C');

		$this->Cell(40);
		$this->SetFont('Arial','',10);
		$UTC = new DateTimeZone("UTC");

		try {
			$newTZ = new DateTimeZone($_GET['zone']);
		}catch(Exception $e) {
			echo 'Cant generate PDF because of Unknown or bad timezone (', $_GET['zone'], ')';
			exit(); 
		}

		$currentDate = new DateTime(null, $UTC);
		$currentDate->setTimezone( $newTZ );
		
		$this->Cell(30,0,$currentDate->format('Y-M-d H:i:s'),0,0,'C');
		// Line break
		$this->Ln(20);
	}

	// Page footer
	function Footer() {
		// Position at 1.5 cm from bottom
		$this->SetY(-15);
		// Arial italic 8
		$this->SetFont('Arial','I',8);
		// Page number
		$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
	}
}

// Instanciation of inherited class
$pdf = new PDF();
//$pdf->SetTitle('Hey hey');  //$GLOBALS['projectName']
//$pdf->AddFont('DejaVu','','/DejaVuSansCondensed.ttf',true);
//php/libs/tfpdf/font/unifont/
$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('DejaVu','U',12);
$pdf->Cell(20,10,'Seat number',0,0,'C');
$pdf->Cell(6);
$pdf->Cell(40,10,'Room name',0,0,'C');
$pdf->Cell(40,10,'Date',0,0,'C');
$pdf->Cell(40,10,'Email',0,0,'C');
$pdf->Cell(40,10,'Status',0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('DejaVu','',10);

$UTC = new DateTimeZone("UTC");

try {
	$newTZ = new DateTimeZone($_GET['zone']);
}catch(Exception $e) {
	echo 'Cant generate PDF because of Unknown or bad timezone (', $_GET['zone'], ')';
	exit();
}

//echo $date->format('Y-m-d H:i:s');
for($i=0;$i<$regLen;$i++) {
	$registrantCustomData = json_decode($registrations[$i]->custom_field_data, true);
	$status = ($registrations[$i]->status == 2) ? "Confirmed" : "Pending";

	$pdf->Cell(20,10,$registrations[$i]->seat_nr,0,0,'C');
	$pdf->Cell(6);
	$pdf->Cell(40,10,$registrations[$i]->room_name,0,0,'C');

	$date = new DateTime($registrations[$i]->registration_date, $UTC );
	$date->setTimezone( $newTZ );

	$pdf->Cell(40,10,$date->format('Y-M-d H:i:s'),0,0,'C');
	$pdf->Cell(40,10,$registrations[$i]->email,0,0,'C');
	$pdf->Cell(40,10,$status,0,1,'C');
	$pdf->Cell(80,10,'Name: ' . $registrations[$i]->first_name . ' ' . $registrations[$i]->last_name,0,1);

	if($status =='Confirmed') {
		$date = new DateTime($registrations[$i]->registration_confirm_date, $UTC );
		$date->setTimezone( $newTZ );
		$pdf->Cell(80,10,'Confirm date: ' . $date->format('Y-M-d H:i:s'),0,1);
	}

	for($j = 0; $j < $customFieldsCount; $j++) {
		$pdf->Cell(40,10,customFieldWithValuePDF($customFields[$j]->label, $registrantCustomData),0,1);
	}

	$pdf->Ln(10);
	//$text = $registrations[$i]['seat_nr'] . ' ' . $registrations[$i]['room_name'] . $registrations[$i]['first_name'] . ' ' . $registrations[$i]['last_name'];
	//$pdf->Cell(0,10,$text,0,1);
}
	
$pdf->Output();	