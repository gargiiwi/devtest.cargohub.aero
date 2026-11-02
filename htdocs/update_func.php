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

// $i=1;
$fetch_query = "SELECT audit_activity.id, audit_activity.issue_id, issue_orgdata.process FROM audit_activity LEFT JOIN issue_orgdata ON audit_activity.issue_id = issue_orgdata.issue_id WHERE audit_activity.issue_type_id = 31 AND issue_orgdata.process = 'closed'";// LIMIT 0,1
$result = $conn->query($fetch_query);
$row_data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // $row_data[] = $row;
        $update_query = "UPDATE audit_activity SET `status` = 'closed' WHERE id = '".$row['id']."'";
        // echo "<br> update_query : ".$update_query;
        // $i++;
        // if ($conn->query($update_query) === TRUE){
        //     echo "<br>Record with ID ".$row['id']." updated successfully.<br>";
        // } else {
        //     echo "Error updating record with ID ".$row['id'].": " . $conn->error . "<br>";
        // }
    }
}
// echo $i;
// echo "<pre>";
// print_r($row_data);
// exit;


