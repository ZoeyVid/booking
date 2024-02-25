# booking

Ein Reservierungsystem mit Kontrolle per QRCode oder PIN. Benötigt php-sqlite3, php-curl, php-ctype, php-openssl und php-session. Bitte die config.example.php vorher zu config.php umbennen und ausfüllen! <br>
Unterstützte Variablen im E-Mail versand:

- ?email? = E-Mail
- ?pin? = PIN
- ?vn? = Vorname
- ?nn? = Nachname
- ?bookinglink? = Link um Reservierung zu bestätigen
- ?stornolink? = Link um Reservierung zu stornieren
- ?qrcode? = fügt den QRCode ein (nur in HTML unterstützt)
