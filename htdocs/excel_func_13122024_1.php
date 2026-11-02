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
// echo "test";exit;

$csvFile = 'LIVE/LIVE_excel/Audits-Grid_Export333_2024-10-22 05_54_40.csv';
$file = fopen($csvFile, "r");
$i=0;
$a_id=0;
$a_track=0;
while (($getData = fgetcsv($file, 10000, ",")) !== FALSE) {
    $master_org_id = 0;
    $master_id =  0;
    $trackId = $getData[0];
    // if($trackId == 'Tracking #'){
    //     continue;
    // }
    // echo $trackId."<br>";
    // echo "<br>".$i;
    // if($i == 3){ break;}
    $query = "SELECT id, master_org_id, track FROM issues WHERE track = '$trackId'";
    // echo $query."<br>";
    $result = mysqli_query($conn, $query);
    while ($property = mysqli_fetch_assoc($result)) {
        // echo "<pre>";print_r($property);
       $master_org_id =  $property['master_org_id'];
       $master_id =  $property['id'];
    }


    if($getData[8] !='' || $getData[9] !='' || $getData[10] !='' || $getData[11] !='' || $getData[14] !='' ){

        
        $audit_type = '';
        $audit_risk = '';
        if($getData[15] !='' &&  $getData[15] !='(not set)'){
            if (strpos($getData[15], '(') !== false && strpos($getData[15], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[15], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[15];
            }


            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }

        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A1CaPa'=>$getData[8],
            'audit_improvement'=>$getData[8],
            'audit_cause_comments'=>$getData[9],//A1Cause
            'audit_closing_date'=>$getData[10],//A1Closedate
            'status'=>($getData[10] !='' && $getData[10] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[11],//A1 Deadline
            'person'=>$getData[12],//A1 Deadline
            'audit_title'=>$getData[14],//A1 Title
            '_audit_type'=>$getData[15],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }

    if($getData[16] !='' || $getData[17] !='' || $getData[18] !='' || $getData[19] !='' || $getData[22] !='' ){
        
        $audit_type = '';
        $audit_risk = '';
        if($getData[23] !='' &&  $getData[23] !='(not set)'){
            if (strpos($getData[23], '(') !== false && strpos($getData[23], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[23], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[23];
            }

            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }

        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A2CaPa'=>$getData[16],
            'audit_improvement'=>$getData[16],
            'audit_cause_comments'=>$getData[17],//A1Cause
            'audit_closing_date'=>$getData[18],//A1Closedate
            'status'=>($getData[18] !='' && $getData[18] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[19],//A1 Deadline
            'person'=>$getData[20],//A1 Deadline
            'audit_title'=>$getData[22],//A1 Title
            '_audit_type'=>$getData[23],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }

    if($getData[24] !='' || $getData[25] !='' || $getData[26] !='' || $getData[27] !='' || $getData[30] !='' ){
        
        $audit_type = '';
        $audit_risk = '';
        if($getData[31] !='' &&  $getData[31] !='(not set)'){
            if (strpos($getData[31], '(') !== false && strpos($getData[31], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[31], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[31];
            }

            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }
        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A3CaPa'=>$getData[24],
            'audit_improvement'=>$getData[24],
            'audit_cause_comments'=>$getData[25],//A1Cause
            'audit_closing_date'=>$getData[26],//A1Closedate
            'status'=>($getData[26] !='' && $getData[26] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[27],//A1 Deadline
            'person'=>$getData[28],//A1 Deadline
            'audit_title'=>$getData[30],//A1 Title
            '_audit_type'=>$getData[31],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }

    if($getData[32] !='' || $getData[33] !='' || $getData[34] !='' || $getData[35] !='' || $getData[38] !='' ){
        
        $audit_type = '';
        $audit_risk = '';
        if($getData[39] !='' &&  $getData[39] !='(not set)'){
            if (strpos($getData[39], '(') !== false && strpos($getData[39], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[39], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[39];
            }

            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }
        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A4CaPa'=>$getData[32],
            'audit_improvement'=>$getData[32],
            'audit_cause_comments'=>$getData[33],//A1Cause
            'audit_closing_date'=>$getData[34],//A1Closedate
            'status'=>($getData[34] !='' && $getData[34] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[35],//A1 Deadline
            'person'=>$getData[36],//A1 Deadline
            'audit_title'=>$getData[38],//A1 Title
            '_audit_type'=>$getData[39],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }

    if($getData[40] !='' || $getData[41] !='' || $getData[42] !='' || $getData[43] !='' || $getData[46] !='' ){
        
        $audit_type = '';
        $audit_risk = '';

        if($getData[47] !='' &&  $getData[47] !='(not set)'){
            if (strpos($getData[47], '(') !== false && strpos($getData[47], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[47], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[47];
            }


            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }
        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A5CaPa'=>$getData[40],
            'audit_improvement'=>$getData[40],
            'audit_cause_comments'=>$getData[41],//A1Cause
            'audit_closing_date'=>$getData[42],//A1Closedate
            'status'=>($getData[42] !='' && $getData[42] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[43],//A1 Deadline
            'person'=>$getData[44],//A1 Deadline
            'audit_title'=>$getData[46],//A1 Title
            '_audit_type'=>$getData[47],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }

    if($getData[48] !='' || $getData[49] !='' || $getData[50] !='' || $getData[51] !='' || $getData[54] !=''  ){
        
        $audit_type = '';
        $audit_risk = '';
        if($getData[55] !='' &&  $getData[55] !='(not set)'){
            if (strpos($getData[55], '(') !== false && strpos($getData[55], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[55], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[55];
            }


            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }
        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A6CaPa'=>$getData[48],
            'audit_improvement'=>$getData[48],
            'audit_cause_comments'=>$getData[49],//A1Cause
            'audit_closing_date'=>$getData[50],//A1Closedate
            'status'=>($getData[50] !='' && $getData[50] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[51],//A1 Deadline
            'person'=>$getData[52],//A1 Deadline
            'audit_title'=>$getData[54],//A1 Title
            '_audit_type'=>$getData[55],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }
    if($getData[56] !='' || $getData[57] !='' || $getData[58] !='' || $getData[59] !='' || $getData[62] !=''  ){
        
        $audit_type = '';
        $audit_risk = '';
        if($getData[63] !='' &&  $getData[63] !='(not set)'){
            if (strpos($getData[63], '(') !== false && strpos($getData[63], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[63], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[63];
            }


            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }


        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A7CaPa'=>$getData[56],
            'audit_improvement'=>$getData[56],
            'audit_cause_comments'=>$getData[57],//A1Cause
            'audit_closing_date'=>$getData[58],//A1Closedate
            'status'=>($getData[58] !='' && $getData[58] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[59],//A1 Deadline
            'person'=>$getData[60],//A1 Deadline
            'audit_title'=>$getData[62],//A1 Title
            '_audit_type'=>$getData[63],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }
    if($getData[64] !='' || $getData[65] !='' || $getData[66] !='' || $getData[67] !='' || $getData[70] !=''  ){
        
        $audit_type = '';
        $audit_risk = '';

        if($getData[71] !='' &&  $getData[71] !='(not set)'){
            if (strpos($getData[71], '(') !== false && strpos($getData[71], ')') !== false) {
                preg_match('/(\w+)\s*\((\w+)\)/', $getData[71], $matches);
                $audit_type = $matches[1];
                $audit_risk = $matches[2];
            } else {
                $audit_type = $getData[71];
            }


            if($audit_type == 'Finding'){
                $audit_type = 'finding';
            }
            if($audit_type == 'Improvement opportunity'){
                $audit_type = 'opportunity';
            }
            if($audit_type == 'Observation'){
                $audit_type = 'observation';
            }
            if($audit_risk == 'minor'){
                $audit_risk = 'MI';
            }elseif($audit_risk == 'Major'){
                $audit_risk = 'MA';
            }else{
                $audit_risk = 'MI';
            }
        }
        $getDataArray[] = array(
            'track'=>$getData[0],
            'id'=>$master_id,
            'org_id'=>$master_org_id,
            'A8CaPa'=>$getData[64],
            'audit_improvement'=>$getData[64],
            'audit_cause_comments'=>$getData[65],//A1Cause
            'audit_closing_date'=>$getData[66],//A1Closedate
            'status'=>($getData[66] !='' && $getData[66] !='0000-00-00') ? 'closed' : 'active' ,//A1 Deadline
            'audit_deadline_date'=>$getData[67],//A1 Deadline
            'person'=>$getData[68],//A1 Deadline
            'audit_title'=>$getData[70],//A1 Title
            '_audit_type'=>$getData[71],//A1 Type
            'audit_type'=>$audit_type,//A1 Type
            'audit_risk'=>$audit_risk,//A1 Type
        );
    }

    $_getDataArray = $getDataArray;   
    $i++;
}
$_getDataArray = array_slice($_getDataArray, 8); 

$processedData = addIdVal($_getDataArray);


// echo "<hr>";
echo "<pre>";
print_r($processedData);
// exit;

foreach($processedData as $val){
        // Insert query into the audit_activity table
        $track = $val['track'];
        $issue_id = $val['id'];
        $org_id = $val['org_id'];
        $improvement = $val['audit_improvement'];
        $comments = $val['audit_cause_comments'];
        $closing_date =$val['audit_closing_date'];
        $deadline_date = $val['audit_deadline_date'];
        $title = $val['audit_title'];
        $type = $val['audit_type'];
        $risk = $val['audit_risk'];
        $person = $val['person'];
        $status = $val['status'];
        $id_val = $val['audit_risk'].$val['id_ind'];

        if($issue_id < 1 ){
            continue;
        }


        // $insert_sql = "INSERT INTO audit_activity (track, issue_id, org_id, improvement, comments, closing_date, deadline_date, title, type, risk,person,status,id_val) 
        // VALUES ('$track', $issue_id, $org_id, '$improvement', '$comments', '$closing_date', '$deadline_date', '$title', '$type', '$risk', '$person', '$status', '$id_val')";
        // // echo $insert_sql;exit;

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










