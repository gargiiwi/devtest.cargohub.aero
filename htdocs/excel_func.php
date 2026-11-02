<?php

// Database connection
$servername = "localhost";
$username = "cargoits_v10";
$password = "7e31100dc9f66856";
$dbname = "cargoits_v10";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


//LIVE $csvFile = 'Events-GridExport[2024-12-11 06_51_02]_1.csv';

$csvFile = 'Events-Grid Export [2024-12-13 06_40_14]_15000_16903_7.csv';
$file = fopen($csvFile, "r");
$i=0;
while (($getData = fgetcsv($file, 10000, ",")) !== FALSE) {
    // echo "<hr>";echo "<pre>";print_r($getData); exit;
    $master_org_id = 0;
    $master_id =  0;
    $trackId = $getData[0];
    $query = "SELECT id, master_org_id, track FROM issues WHERE track = '$trackId'";

    $result = mysqli_query($conn, $query);
    while ($property = mysqli_fetch_assoc($result)) {
       $master_org_id =  $property['master_org_id'];
       $master_id =  $property['id'];
    }
    

    // [0] => Tracking #
    // [1] => Created [Time]
    // [2] => Priority
    // [3] => Status
    // [4] => Submit Organization
    // [5] => Submit E-mail
    // [6] => **Title
    // [7] => Subm. Company name
    // [8] => CaPa Date
    // [9] => CaPa Deadline
    // [10] => CaPa Procedure
    // [11] => CaPa Responsible
    // [12] => CaPa Start
    // [13] => CaPa Types
    // [14] => Corrective actions
    // [15] => Preventive actions
    // [16] => Event type
    // [17] => Event severity


    if($getData[8] !='' || $getData[9] !='' || $getData[10] !='' || $getData[11] !='' || $getData[12] !='' ||  $getData[17] !=''){
        
        $audit_type = $getData[17];
        if($audit_type == 'Moderate'){
            $audit_type = 'MO';
        }elseif($audit_type == 'Insignificant'){
            $audit_type = 'IN';
        }elseif($audit_type == 'Critical'){
            $audit_type = 'CR';
        }elseif($audit_type == 'Major'){
            $audit_type = 'MA';
        }elseif($audit_type == 'Minor'){
            $audit_type = 'MI';
        }else{
            $audit_type = '';
        }
        
        $caPaTypes = '';
        $CaPaDetails = '';
        if($getData[16] == 'act' || $getData[16] == 'Actual'){
            $caPaTypes = '1';//Corrective
            $CaPaDetails = $getData[14];
        }
        if($getData[16] == 'pot' || $getData[16] == 'Potential'){
            
            $caPaTypes = '2';//preventive
            $CaPaDetails = $getData[15];
        }

        
        $arrayData = array(
            'trn'=>'(re)Training',
            'guid'=>'Guidelines used',
            'new'=>'New procedure',
            'upd'=>'Updated procedure',
            'wrn'=>'Official warning',
            'dis'=>'Dismissal',
        );
        $selectedProcedures = array_map('trim', explode(',', $getData[10]));
        $matchingKeys = array();
        foreach ($selectedProcedures as $procedure) {
            $key = array_search($procedure, $arrayData); // Find the key by value
            if ($key !== false) {
                $matchingKeys[] = $key; // Store the matching key
            }
        }

        $_CaPaProcedure = implode(',', $matchingKeys);

        $getDataArray = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'title'=>$getData[6],
            'caPaCloseData'=>$getData[8],
            'CaPaProcedure_val'=>$_CaPaProcedure,
            'AssignedTo'=>$getData[11],
            'caPaStartDate'=>$getData[12],
            'caPaDeadline'=>$getData[9],
            
            // 'CaPaProcedure'=>$getData[10],
           
           
            'caPaTypes'=>$caPaTypes,
            'caPaDetails'=>$CaPaDetails,



            // 'CorrectiveActions'=>$getData[13],
            // 'PreventiveActions'=>$getData[14],
            'EventType'=>$getData[16],
            // 'EventSeverity'=>$getData[160],
            'eventSeverity'=>$audit_type,
            'issue_type_id'=>31,
        );
        
        $_getDataArray[] = $getDataArray;   
    }
    
    // $getDataArray[] = $getData;
   
    $i++;
}
unset($_getDataArray[0]);
// $processedData = array_slice($_getDataArray, 8); 

$processedData = addIdVal($_getDataArray);
// echo "<hr>";
// echo "<pre>";
// print_r($processedData);
// exit;

foreach($processedData as $val){
    // Insert query into the audit_activity table
    $track = $val['track'];
    $issue_id = $val['id'];
    $org_id = $val['org_id'];
    if($org_id < 1){
        continue;
    }
    $title = preg_replace('/[,\.\']/', '',$val['title']) ;
    $caPaDate = $val['caPaDate'];
    $caPaDeadline = $val['caPaDeadline'];
    $caPaStartDate = $val['caPaStartDate'];
    $caPaCloseData = $val['caPaCloseData'];
    $CaPaProcedure_val = $val['CaPaProcedure_val'];
    $person = $val['AssignedTo'];
    $caPaTypes = $val['caPaTypes'];
    $caPaDetails = $val['caPaDetails'];
    $EventType = $val['EventType'];
    $eventSeverity = $val['eventSeverity'];
    $issue_type_id = $val['issue_type_id'];
    $id_val = $eventSeverity.$val['id_ind'];
    $_currentDate =  date("Y-m-d");
    // $caPaCloseData = "2024-12-13";
    
    if($caPaCloseData !='' && (strtotime($caPaCloseData) < strtotime($_currentDate))) {
        $_status = 'closed';
    } else {
        $_status = 'active';
    }

    

    // $insert_sql = "INSERT INTO audit_activity(track,issue_id,org_id,deadline_date,ctime,closing_date,title,person,caPa_types,issue_type_id,risk,caPa_procedure,id_val,status,improvement) 
    // VALUES ('$track',$issue_id,$org_id,'$caPaDeadline','$caPaStartDate','$caPaCloseData','$title','$person','$caPaTypes','$issue_type_id','$eventSeverity','$CaPaProcedure_val','$id_val','$_status','$caPaDetails')";
    // // echo "<br>".$insert_sql;

    // if (mysqli_query($conn, $insert_sql)) {
    // // Get the last inserted ID
    // $last_id = mysqli_insert_id($conn);
    // echo "Record inserted successfully. Last inserted ID is: " . $last_id . "<br>";

    //     // Now update the id_val column based on the last inserted ID
    //     // $id_val = $risk.$last_id; // You can generate id_val however you need
    //     // $update_sql = "UPDATE audit_activity SET id_val = '$id_val' WHERE id = $last_id";

    //     // if (mysqli_query($conn, $update_sql)) {
    //     // echo "id_val updated successfully with value: " . $id_val;
    //     // } else {
    //     // echo "Error updating id_val: " . mysqli_error($conn);
    //     // }
    // } else {
    // echo "Error: " . $insert_sql . "<br>" . mysqli_error($conn);
    // }
}









function addIdVal($arrayData) {
    $trackCounts = array();
    foreach ($arrayData as &$item) {
        $track = $item['track'];
        if (isset($trackCounts[$track])) {
            $trackCounts[$track]++;
        } else {
            $trackCounts[$track] = 1;
        }
        $item['id_ind'] = $trackCounts[$track];
    }
    return $arrayData;
}







fclose($file);










