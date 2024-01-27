<?php
$config = [
    "tz" => "Europe/Berlin", // Zeitzone
    "max" => 350, // Anzahl an Plätzen
    "host" => "127.0.0.1", // Domain des Webservers
    "db_path" => "/opt/booking/reservierungen.db", // Name und Pfad der Datenbank - niemals im webroot speichern - keine relativen pfade
    "mail_host" => "mx.example.org", // E-Mail Host
    "mail_address" => "no-reply@example.org", // E-Mail-Adresse und Benutzername
    "mail_replyto" => "hife@example.org", // Antworten-an E-Mail Adresse
    "mail_name" => "Beispiel Reservierungssystem", // Name im E-Mail Header
    "mail_password" => "123456789", // Passwort für die E-Mail-Adresse
    "mail_encryption" => "tls", // Optionen: none (Port 25), ssl (Port 465), tls (Port 587)
    "ennotify" => true, // Sollen Benachrichtigungs-E-Mails versendet werden, wenn eine Stornierung/Reservierung eingeht
    "mail_notify" => "treffen@24dmng.de", // An welche E-Mail sollen diese gehen?
    "hcaptcha_secret" => "0x7abc", // hcaptcha secret für reservierung
    "hcaptcha_key" => "abc-123-cdf-456", // hcaptcha site-key für reservierung
    "recaptcha_secret" => "abc", // recaptcha v3 secret für Reservierungskontrolle
    "recaptcha_key" => "abc", // recaptcha v3 site-key für Reservierungskontrolle
    "recaptcha_score" => 0.7, // mindest Score um Anfrage zuzulassen (Wert zwischen 0.0 und 1.0)
    "err_support" => "hilfe@example.org", // Support E-Mail-Adresse bei Fehlermeldungen
    "people" => "Menschen", // Wer organissiert diese Veranstaltung?
    "event" => '"Veranstaltung" am 01.01.2970', // Was ist die Veranstaltung
    "enyear" => false, // Soll ein jahr abgefragt werden?
    "min_year" => 1997, // minimales Jahr
    "max_year" => 2023, // maximales Jahr
    "type_year" => "Geburtsjahr", // Was ist es für ein Jahr?
    "msg_year" => "Welches Jahr?", // Wie soll das Jahr abgefragt werden?
    "checkpswd" => "sehrs1chererespasswort", // Passwort für die Reservierungskontrolle - leer = aus
    "readpswd" => "nochviels1chererespasswort", // Passwort für das lessen aller Reservierungen - leer = aus
    "ensmime" => "true", // Soll S/MIME aktiviert werden?
    "smimecert" => "/path/to/cert.crt", // Pfad zum S/MIME Zertifikat
    "smimekey" => "/path/to/cert.key", // Pfad zum S/MIME Schlüssel
    "smimecertchain" => "/path/to/certchain.pem", // Pfad zur S/MIME Zertifikatskette
    "smimepass" => "123456789", // Passwort für den S/MIME Key
];
