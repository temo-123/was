# Tech tips on the gym tablet

The tablet (Android, Chrome) shows the tips at **`http://<this computer's IP in the Wi-Fi>:8090`**.
The admin panel → **Tech tips** prints the exact address and a QR code to scan with the tablet.

> "localhost" only means "this same device", so on the tablet `localhost:8090` would look for a server
> *on the tablet*. Always use the computer's LAN IP (e.g. `192.168.1.10:8090`).

On port 8090 Laravel serves **only** the tips (`/tips`); `/`, `/admin`, `/login`… all lead back to them,
so the tablet can't be used to get into the admin panel. The port number is `TIPS_PORT` in `.env`
(default 8090; change it in the Apache file too).

## Ubuntu (development server)

```bash
sudo cp deploy/apache/tech-tips-ubuntu.conf /etc/apache2/sites-available/tech-tips.conf
sudo a2ensite tech-tips
sudo systemctl reload apache2
sudo ufw allow 8090/tcp        # only if ufw is enabled
```

## Windows XAMPP (gym computer)

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and paste the content of `tech-tips-xampp.conf` at the end.
   Change `C:/xampp/htdocs/climbing` (twice) to the folder where this project is.
2. XAMPP Control Panel → Apache **Stop**, then **Start**.
3. Allow the port in the Windows firewall (PowerShell **as administrator**):
   ```powershell
   New-NetFirewallRule -DisplayName "Climbing tech tips" -Direction Inbound -Protocol TCP -LocalPort 8090 -Action Allow
   ```
   (or: Windows Defender Firewall → Advanced settings → Inbound rules → New rule → Port → TCP 8090 → Allow.)
   Also mark the gym Wi-Fi as a **Private** network in Windows, otherwise other devices are often blocked.
4. Check on the computer: <http://localhost:8090> must show the tips.

If the computer's IP changes from time to time, reserve a fixed IP for it in the Wi-Fi router (DHCP reservation),
or set `TIPS_URL=http://192.168.x.x:8090` in `.env` so the admin page shows the right address.

## Without Apache (quick test, both systems)

```bash
php artisan serve --host=0.0.0.0 --port=8090
```

Works the same while the terminal window stays open. Fine for testing, Apache is better for every day.

## Setting up the tablet

- Connect it to the **same Wi-Fi** as the computer, open Chrome, scan the QR code from the admin page.
- Tap the full-screen button (top right). Chrome menu → **Add to Home screen** gives it an icon.
- Settings → Display → Screen timeout: the longest; keep the tablet on its charger.
- For a locked-down kiosk: Settings → Security → **App pinning** (Android "pin" the Chrome app), or a kiosk
  browser app such as "Fully Kiosk Browser" with the same address as start page.
- Untouched for 90 s (`TIPS_IDLE_SECONDS`) the page goes back to the start, switches to the default language
  and shows the tips as a slideshow. New and edited tips appear by themselves within 5 minutes.
