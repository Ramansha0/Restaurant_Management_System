<?php
// Prints one QR code per table, pointing to costumer.html?table=N
// Each table gets its own printed QR code - customers never type the table number.
include 'config.php';

$base = rtrim(PUBLIC_URL, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Table QR Codes</title>
<style>
  body { font-family: 'Poppins', Arial, sans-serif; background: #fff; color: #333; }
  h1 { text-align: center; color: #ff6347; }
  p.hint { text-align: center; color: #777; margin-bottom: 25px; }
  .grid { display: flex; flex-wrap: wrap; gap: 25px; justify-content: center; }
  .card { width: 250px; text-align: center; border: 2px solid #ff6347; border-radius: 14px; padding: 15px; }
  .card h3 { margin: 10px 0 0; color: #ff6347; font-size: 22px; }
  .warning { background: #fff3cd; border: 1px solid #ffcc00; padding: 15px; text-align: center; max-width: 560px; margin: 30px auto; }
  button { display: block; margin: 20px auto; background: #ff6347; color: #fff; border: none; padding: 12px 25px; border-radius: 10px; font-size: 16px; cursor: pointer; }
</style>
</head>
<body>

<h1>🍽️ Scan &amp; Order - Table QR Codes</h1>

<?php if ($base === ''): ?>
  <p class="warning">
    ⚠️ No public URL configured yet.<br><br>
    1. Double-click <b>start-tunnel.bat</b><br>
    2. Copy the <b>trycloudflare.com</b> URL it prints<br>
    3. Paste it into <b>config.php</b> on the <b>PUBLIC_URL</b> line<br>
    4. Reload this page
  </p>
<?php else: ?>
  <p class="hint">
    Code links to <b><?php echo htmlspecialchars($base); ?></b><br>
    Print one per table (Ctrl+P). Add more: open <b>qr.php?count=20</b> for 20 tables.
  </p>
  <div class="grid" id="grid"></div>
  <button onclick="window.print()">🖨️ Print QR Codes</button>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    const base = <?php echo json_encode($base); ?>;
    const params = new URLSearchParams(location.search);
    const count = parseInt(params.get('count') || '10', 10);
    const isStaff = params.get('staff') === '1';
    const grid = document.getElementById('grid');

    function addCard(text, label) {
      const card = document.createElement('div');
      card.className = 'card';

      const box = document.createElement('div');
      card.appendChild(box);

      const lab = document.createElement('h3');
      lab.textContent = label;
      card.appendChild(lab);

      grid.appendChild(card);

      new QRCode(box, {
        text: text,
        width: 220,
        height: 220,
        correctLevel: QRCode.CorrectLevel.H
      });
    }

    if (isStaff) {
      addCard(base + '/api/waiter.php', 'Waiter Dashboard');
      document.querySelector('h1').textContent = '👨‍🍳 Waiter Dashboard QR Code';
    } else {
      for (let t = 1; t <= count; t++) {
        addCard(base + '/costumer.html?table=' + t, 'Table ' + t);
      }
    }
  </script>
<?php endif; ?>

</body>
</html>
