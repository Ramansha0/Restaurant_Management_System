<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurant Waiter Dashboard - History</title>
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
  padding: 20px 15px;
  text-align: center;
  font-size: 26px;
  font-weight: bold;
  letter-spacing: 1px;
  box-shadow: 0px 4px 8px rgba(0,0,0,0.2);
  position: relative;
}

.hamburger {
  display: none;
  position: absolute;
  right: 15px;
  top: 20px;
  font-size: 28px;
  cursor: pointer;
  user-select: none;
}

.container {
  display: grid;
  grid-template-columns: 180px 1fr;
  min-height: 100vh;
}

.sidebar {
  background: #fff0eb;
  border-right: 2px solid #ff6347;
  padding: 15px 10px;
  transition: transform 0.3s ease;
}
.sidebar h2 {
  color: #ff6347;
  font-size: 20px;
  margin-bottom: 15px;
}
#sub{
     background-color: #ff6347;
  height: 28px;
  color:white;
  border-radius:5px;
  border:none;
  cursor: pointer;
  margin-right: 8px;
  padding: 0 10px; 
  font-size:16px;
  font-weight:700;
}
.sidebar ul { list-style: none; padding: 0; margin:0; }
.sidebar li { margin: 10px 0; }
.sidebar a {
  text-decoration: none;
  color: #333;
  font-weight: 500;
  padding: 8px 10px;
  display: block;
  border-radius: 6px;
  transition: 0.3s;
}
.sidebar a:hover { background: #ff6347; color: white; }

.main { 
  padding: 20px; 
  background: #fffdfc;
  min-height: 100vh;
}

.section-title {
  font-size: 24px;
  font-weight: bold;
  color: #ff6347;
  margin-bottom: 15px;
}

.orders { 
  display: grid; 
  gap: 15px; 
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
}

.order-card {
  background: white;
  padding: 15px;
  border-left: 6px solid #2ecc71; /* green for served */
  border-right: 6px solid #2ecc71;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  box-shadow: 4px 4px 10px rgba(0,0,0,0.1);
  transition: transform 0.2s;
  margin-top:20px;
}

.order-card:hover { transform: translateY(-2px); }

.order-header { font-size: 18px; font-weight: bold; color: #2ecc71; margin-bottom: 8px; }
.order-items { font-size: 15px; margin: 4px 0; }

footer {
  text-align: center;
  padding: 15px;
  background: #ff6347;
  color: white;
  margin-top: 20px;
  font-weight: bold;
}
.btn2{
   background: #23c138ff;
  height: 28px;
  color:white;
  border-radius:5px;
  border:none;
  cursor: pointer;
  margin-right: 8px;
  padding: 0 10px;
  width:30%;
}

/* -------- Responsive: Tablets -------- */
@media (max-width: 1024px) {
  .container { grid-template-columns: 150px 1fr; }
  .section-title { font-size: 26px; }
  .order-header { font-size: 20px; }
  .order-items { font-size: 17px; }
  .sidebar a { font-size: 16px; padding: 10px; }
}

/* -------- Responsive: Mobiles -------- */
@media (max-width: 600px) {
  .container { grid-template-columns: 1fr; }
  .sidebar { 
    position: fixed; 
    width: 70%; 
    max-width: 250px; 
    height: 100%; 
    top: 0; 
    left: -100%; 
    z-index: 1000; 
  }
  .sidebar.active { left: 0; }
  .hamburger { display: block; color:white; }
  .section-title { font-size: 28px; text-align:center; }
  .order-card { width: 90%; margin:auto; padding: 10px; font-size: 16px; margin-top:20px} 
  .order-header { font-size: 20px; }
  .order-items { font-size: 16px; }
}
</style>
</head>
<body>

<header>
  🍴  Waiter Dashboard - History
  <div class="hamburger">&#9776;</div>
</header>

<div class="container">
  <aside class="sidebar">
    <h2>Navigation</h2>
    <ul>
      <li><a href="waiter.php">📋 Orders</a></li>
      <li><a href="history.php">⚙️ History</a></li>
    </ul>
  </aside>

  <main class="main">
    <h2 class="section-title">Order History</h2>
    <form method="POST" >
<button  type="submit" name="submits" id="sub" >Clear Order History</button>

</form>
    <div class="orders"><?php
$con = new mysqli("localhost", "root", "", "datasave");
if ($con->connect_error) die("Connection failed: " . $con->connect_error);
//delete//
if(isset($_POST["submits"])){
    $submit=$_POST["submits"];
    $delete ="DELETE FROM history";
    $result = $con->query($delete);
    if($result===true){
        echo "No order history";
    }
}
// Display all orders in reverse order (newest first)
$sql = "SELECT * FROM history ORDER BY `Table` DESC"; // Or use a timestamp column if you have one
$result = $con->query($sql);

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        echo '<div class="order-card">';
        echo '<div class="order-header">Order #' . $row['Table'] . '</div>';
        echo '<div class="order-items">' . htmlspecialchars($row['Items']) . '</div>';
         echo "<button  class='btn2'>Served</button>";
        echo '</div>';
    }
} 

?>


    </div>
  </main>
</div>

<footer>© 2025 Tomato Restaurant – Waiter Interface</footer>

<script>
// Hamburger toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
let open = false;

hamburger.addEventListener('click', () => {
  sidebar.classList.toggle('active');
  open = !open;
  hamburger.innerHTML = open ? '&#10006;' : '&#9776;';
});
</script>

</body>
</html>
