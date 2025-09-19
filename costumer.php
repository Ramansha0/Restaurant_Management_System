<?php 
$con = new mysqli ("localhost","root","","datasave");
if($con->connect_error){
    die("Connection failed:".$con->connect_error);
}

if (isset($_POST['selected']) &&isset($_POST['table'])){
    $selected = $_POST['selected'];
    $table = $_POST['table'];
$save ="INSERT INTO orders (`Table` ,`Items`) VALUES ('$table','$selected')";
if(mysqli_query($con,$save)){
  echo "New record created successfully";

}


}
$con->close();
?>