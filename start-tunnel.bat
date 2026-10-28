@echo off
title RESTAURANT TUNNEL - keep this window OPEN while customers are ordering
echo ----------------------------------------------------------------
echo  Your system is going ONLINE. DO NOT close this window!
echo.
echo  1. Copy the trycloudflare.com URL shown below this
echo  2. Paste it into config.php on the PUBLIC_URL line
echo     (every restart gives a NEW URL - update it again)
echo  3. Reload http://localhost/qr.php and reprint the QR codes
echo ----------------------------------------------------------------
"C:\Users\raman\Downloads\cloudflared-windows-amd64.exe" tunnel --url http://localhost:80
pause
