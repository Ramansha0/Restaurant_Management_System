<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Restaurant Waiter Dashboard</title>
  <style>
    body {
      font-family: "Segoe UI", Tahoma, sans-serif;
      background: #fff8f5;
      margin: 0;
      padding: 0;
      color: #333;
    }

    header {
      background: #ff6347;
      color: white;
      padding: 20px;
      text-align: center;
      font-size: 26px;
      font-weight: bold;
      letter-spacing: 1px;
      box-shadow: 0px 4px 8px rgba(0,0,0,0.2);
    }

    .container {
      display: grid;
      grid-template-columns: 250px 1fr;
      min-height: 100vh;
    }

    .sidebar {
      background: #fff0eb;
      border-right: 2px solid #ff6347;
      padding: 20px;
    }
    .sidebar h2 {
      color: #ff6347;
      font-size: 20px;
      margin-bottom: 15px;
    }
    .sidebar ul { list-style: none; padding: 0; }
    .sidebar li { margin: 12px 0; }
    .sidebar a {
      text-decoration: none;
      color: #333;
      font-weight: 500;
      padding: 8px 12px;
      display: block;
      border-radius: 6px;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: #ff6347;
      color: white;
    }

    .main { padding: 20px; background: #fffdfc; }
    .section-title {
      font-size: 22px;
      font-weight: bold;
      color: #ff6347;
      margin-bottom: 15px;
    }

    .orders { display: grid; gap: 20px; }
    .order-card {
      background: white;
      padding: 18px;
      border-left: 6px solid #ff6347;
      border-right: 6px solid #ff6347;
      border-radius: 10px;
      width:40%;
      display :flex;
       flex-wrap: wrap;
       justify-content: space-between;
      box-shadow: 4px 4px 10px rgba(0,0,0,0.1);
     
    }

    .order-header {
      font-size: 18px;
      font-weight: bold;
      margin-bottom: 8px;
      color: #ff6347;
     
    }

    .order-items { font-size: 15px; margin: 6px 0;width:100%;flex-wrap:wrap }
    

    footer {
      text-align: center;
      padding: 15px;
      background: #ff6347;
      color: white;
      margin-top: 20px;
    }
.order-details {
  flex: 1 1 70%;   /* takes most of the space */
}
    .btn{
      width :60px;
      background-color: #ff6347;
      height: 25px;
      color:white;
    
    border-radius:5px;
    border:none;
    postion :relative;
    z-index:1000;
    margin-left:20px;
    }
  </style>
</head>
<body>

<header>🍴 Restaurant Waiter Dashboard</header>

<div class="container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <h2>Navigation</h2>
    <ul>
      <li><a href="#">📋 Orders</a></li>
      <li><a href="#">🍽 Tables</a></li>
      <li><a href="#">👨‍🍳 Kitchen</a></li>
      <li><a href="#">💳 Payments</a></li>
      <li><a href="#">⚙️ Settings</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <h2 class="section-title">Live Orders</h2>
    <div class="orders">
      <?php
      // Connect to database
      $con = new mysqli("localhost", "root", "", "datasave");
      if ($con->connect_error) {
          die("Connection failed: " . $con->connect_error);
      }

      // Fetch all orders
      $sql = "SELECT * FROM orders"; // No ORDER BY id because no id column
      $result = $con->query($sql);

      if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<div  class='order-card'>";
              echo "<div class='written'>";
              echo "<div class='order-header'>Table: " . htmlspecialchars($row['Table']) . "</div>";
              echo "<div class='order-items'>Items: " . htmlspecialchars($row['Items']) . "</div>";
                        echo "</div>";
                        echo "<button class='btn'> Served</button>";
              echo "</div>";
          }
      } else {
          echo "<h1>No orders yet </h1>";
      }

      $con->close();
      ?>
    </div>
  </main>
</div>

<footer>
  © 2025 Tomato Restaurant – Waiter Interface
</footer>

</body>
</html>
