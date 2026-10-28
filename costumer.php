<?php
// Order submission - saves the order into phpMyAdmin (datasave.orders + history)
include 'config.php';

$con = db_orders();

if (isset($_POST['selected']) && isset($_POST['table'])) {
    $selected = trim($_POST['selected']);
    $table = trim($_POST['table']);

    if ($selected !== '' && $table !== '') {
        // Prepared statements: safe against SQL injection
        $stmt = $con->prepare("INSERT INTO orders (`Table`, `Items`) VALUES (?, ?)");
        $stmt->bind_param("ss", $table, $selected);
        $ok = $stmt->execute();

        // Save a copy into history
        $stmt2 = $con->prepare("INSERT INTO history (`Table`, `Items`) VALUES (?, ?)");
        $stmt2->bind_param("ss", $table, $selected);
        $ok = $ok && $stmt2->execute();

        $message = $ok ? "✅ Your order for Table " . htmlspecialchars($table) . " has been sent to the kitchen!"
                       : "❌ Error: " . htmlspecialchars($con->error);
    } else {
        $message = "⚠️ No order data received.";
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
    <a href="costumer.html<?php echo ($table ?? '') !== '' ? '?table=' . urlencode($table) : ''; ?>" class="btn">⬅ Back to Menu</a>
  </div>

</body>
</html>
