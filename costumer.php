<?php
// Connect to database
$con = new mysqli("localhost", "root", "", "datasave");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Check POST data
if (isset($_POST['selected']) && isset($_POST['table'])) {
    $selected = $_POST['selected'];
    $table = $_POST['table'];

    // Insert into database
    $save = "INSERT INTO orders (`Table`, `Items`) VALUES ('$table', '$selected')";
    if (mysqli_query($con, $save)) {
        $message = "✅ Your order has been submitted successfully!";
    } else {
        $message = "❌ Error: " . mysqli_error($con);
    }
} else {
    $message = "⚠️ No order data received.";
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Submitted</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f9;
      text-align: center;
      padding: 50px;
    }
    .box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      display: inline-block;
      box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
    }
    h1 {
      color: green;
    }
    .btn {
      margin-top: 20px;
      padding: 10px 20px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      text-decoration: none;
      font-size: 16px;
    }
    .btn:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>

  <div class="box">
    <h1>Order Status</h1>
    <p><?php echo $message; ?></p>
    <a href="costumer.html" class="btn">⬅ Back to Menu</a>
  </div>

</body>
</html>
