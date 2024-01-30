<?php
include "config.php";
$tz = $config["tz"];
$max = $config["max"];
$host = $config["host"];
$db_path = $config["db_path"];
$mail_host = $config["mail_host"];
$mail_address = $config["mail_address"];
$mail_replyto = $config["mail_replyto"];
$mail_name = $config["mail_name"];
$mail_password = $config["mail_password"];
$mail_encryption = $config["mail_encryption"];
$ennotify = $config["ennotify"];
$mail_notify = $config["mail_notify"];
$hcaptcha_secret = $config["hcaptcha_secret"];
$hcaptcha_key = $config["hcaptcha_key"];
$err_support = $config["err_support"];
$people = $config["people"];
$event = $config["event"];
$enyear = $config["enyear"];
$min_year = $config["min_year"];
$max_year = $config["max_year"];
$type_year = $config["type_year"];
$msg_year = $config["msg_year"];
$ensmime = $config["ensmime"];
$smimecert = $config["smimecert"];
$smimekey = $config["smimekey"];
$smimecertchain = $config["smimecertchain"];
$smimepass = $config["smimepass"];
$impressum = $config["impressum"];
$err = " Bitte versuche es (in einem neuen Tab) erneut! Wenn dieser Fehler öfter auftritt bitte bei " . $err_support . " melden!";

date_default_timezone_set($tz);
require "vendor/autoload.php";
$db = new SQLite3($db_path);
$db->exec("CREATE TABLE IF NOT EXISTS People (email CHAR(255) UNIQUE NOT NULL, pin CHAR(6) UNIQUE NOT NULL, vn CHAR(255) NOT NULL, nn CHAR(255) NOT NULL, year CHAR(4), bookingtoken CHAR(255) UNIQUE NOT NULL, stornotoken CHAR(255) UNIQUE NOT NULL, cf BOOLEAN NOT NULL, cdate CHAR(255))");
$db->exec("VACUUM");

$free = $max - $db->querySingle("SELECT COUNT(*) FROM People WHERE cf = true");

use chillerlan\QRCode\QRCode;

use PHPMailer\PHPMailer\PHPMailer;
$mail = new PHPMailer();
$mail->isSMTP();
$mail->setLanguage("de", "vendor/phpmailer/phpmailer/language");
$mail->CharSet = PHPMailer::CHARSET_UTF8;

if ($mail_encryption == "tls") {
    $mail_encryption = PHPMailer::ENCRYPTION_STARTTLS;
    $mail_port = 587;
} elseif ($mail_encryption == "ssl") {
    $mail_encryption = PHPMailer::ENCRYPTION_SMTPS;
    $mail_port = 465;
} elseif ($mail_encryption == "none") {
    $mail_port = 25;
} else {
    $mail_encryption = PHPMailer::ENCRYPTION_SMTPS;
    $mail_port = 465;
}

$mail->Host = $mail_host;
$mail->SMTPAuth = true;
$mail->Username = $mail_address;
$mail->Password = $mail_password;
$mail->Port = $mail_port;
if (!empty($mail_encryption)) {
    $mail->SMTPSecure = $mail_encryption;
}
$mail->setFrom($mail_address, $mail_name);
$mail->addReplyTo($mail_replyto, $mail_name);
if ($ensmime) {
    $mail->sign($smimecert, $smimekey, $smimepass, $smimecertchain);
}

if ($free <= 0) {
    $msg = "Es sind keine Plätze mehr frei!";
}
$sr = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && $free > 0 && !array_key_exists("bookingtoken", $_GET) && !array_key_exists("stornotoken", $_GET)) {
    if (array_key_exists("email", $_POST) && array_key_exists("vn", $_POST) && array_key_exists("nn", $_POST) && array_key_exists("h-captcha-response", $_POST)) {
        $vn = $_POST["vn"];
        $nn = $_POST["nn"];
        $email = $_POST["email"];
        if (!empty($_POST["year"]) && $enyear) {
            $year = $_POST["year"];
        } else {
            $year = "";
        }

        $data = [
            "secret" => $hcaptcha_secret,
            "sitekey" => $hcaptcha_key,
            "response" => $_POST["h-captcha-response"],
            "remoteip" => $_SERVER["REMOTE_ADDR"],
        ];
        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($verify);
        $responseData = json_decode($response);

        $query = $db->prepare("SELECT * FROM People WHERE email=:email");
        $query->bindValue(":email", $email);

        if (!$responseData->success) {
            $msg = "hCaptcha ungültig!" . $err;
        } elseif (!PHPMailer::validateAddress($email)) {
            $msg = "Die eingegebene E-Mail Adresse ist ungültig!" . $err;
        } elseif (is_array($query->execute()->fetchArray())) {
            if (!$query->execute()->fetchArray()["cf"]) {
                $msg = "Es liegt bereits eine NICHT bestätigte Reservierung für diese E-Mail-Adresse vor, bitte Bestätige diese! Falls du keine E-Mail erhalten hast melde dich bitte bei: " . $err_support;
            } elseif ($query->execute()->fetchArray()["cf"]) {
                $msg = "Es liegt bereits eine bestätigte Reservierung für diese E-Mai-Adresse vor, bitte nutze eine andere E-Mail-Adresse wenn du einen weiteren Platz reservieren willst! Wenn du deine Reservierung stornieren willst verwende bitte den Storno-Link in deiner Reservierungsbestätigung! Hilfe bekommst du auch bei: " . $err_support;
            } else {
                $msg = "Es liegt bereits eine Reservierung für diese E-Mai-Adresse vor, bitte nutze eine andere E-Mail-Adresse wenn du einen weiteren Platz reservieren willst! Wenn du deine Reservierung stornieren willst verwende bitte den Storno-Link in deiner Reservierungsbestätigung! Hilfe bekommst du auch bei: " . $err_support;
            }
        } else {
            do {
                $bookingtoken = rand(100000, 999999) . rand(100000, 999999) . rand(100000, 999999) . rand(100000, 999999) . rand(100000, 999999);
                $query = $db->prepare("SELECT * FROM People WHERE bookingtoken=:bookingtoken");
                $query->bindValue(":bookingtoken", $bookingtoken);
            } while (is_array($query->execute()->fetchArray()));

            do {
                $stornotoken = rand(100000, 999999) . rand(100000, 999999) . rand(100000, 999999) . rand(100000, 999999) . rand(100000, 999999);
                $query = $db->prepare("SELECT * FROM People WHERE stornotoken=:stornotoken");
                $query->bindValue(":stornotoken", $stornotoken);
            } while (is_array($query->execute()->fetchArray()));

            do {
                $pin = rand(100000, 999999);
                $query = $db->prepare("SELECT * FROM People WHERE pin=:pin");
                $query->bindValue(":pin", $pin);
            } while (is_array($query->execute()->fetchArray()));

            $mail->isHTML(false);
            $mail->clearAddresses();
            $mail->addAddress($email, $vn . " " . $nn);
            $mail->Subject = "[" . $mail_name . "] Bestätigungslink" . $event;
            $mail->Body = "Bitte bestätige deine Reservierung hier: https://" . $host . "?bookingtoken=" . $bookingtoken;

            $query = $db->prepare('INSERT OR IGNORE INTO People (email, pin, vn, nn, year, bookingtoken, stornotoken, cf, cdate) VALUES(:email, :pin, :vn, :nn, :year, :bookingtoken, :stornotoken, false, "")');
            $query->bindValue(":email", $email);
            $query->bindValue(":pin", $pin);
            $query->bindValue(":vn", $vn);
            $query->bindValue(":nn", $nn);
            $query->bindValue(":year", $year);
            $query->bindValue(":bookingtoken", $bookingtoken);
            $query->bindValue(":stornotoken", $stornotoken);

            if (!$query->execute()) {
                $msg = "Fehler beim eintragen in die Datenbank!" . $err;
            } elseif (!$mail->send()) {
                $msg = "Fehler beim E-Mail Versand!" . $err;
            } else {
                $sr = true;
                $msg = "Dir wurde eine E-Mail gesendet. Bitte öffne den Link in dieser um deine Reservierung zu bestätigen!";
            }
        }
    } else {
        $msg = "Formular fehlerhaft!" . $err;
    }
}

if (array_key_exists("bookingtoken", $_GET)) {
    $query = $db->prepare("SELECT * FROM People WHERE bookingtoken=:bookingtoken AND cf = false");
    $query->bindValue(":bookingtoken", $_GET["bookingtoken"]);
    if (is_array($query->execute()->fetchArray())) {
        $email = $query->execute()->fetchArray()["email"];
        $pin = $query->execute()->fetchArray()["pin"];
        $vn = $query->execute()->fetchArray()["vn"];
        $nn = $query->execute()->fetchArray()["nn"];
        $stornotoken = $query->execute()->fetchArray()["stornotoken"];
        $year = $query->execute()->fetchArray()["year"];
        if (empty($year)) {
            $yeartxt = "";
        } else {
            $yeartxt = " (" . $type_year . ": " . $year . ")";
        }

        $mail->isHTML();
        $mail->clearAddresses();
        $mail->addAddress($email, $vn . " " . $nn);
        $mail->Subject = "[" . $mail_name . "] Reservierungsbestätigung" . $event;
        // prettier-ignore
        $mail->Body = "Deine Reservierung ist bestätigt, die PIN deiner Reservierung lautet: " . $pin . ' Bitte bringe diese und/oder den folgenden QRCode (digital/analog) mit zum Einlass (falls dieser nicht (korrekt) angezeigt wird, benutze bitte einen anderen E-Mail-Client z.B. Thunderbird). <br>
                       Deine Reservierung ist nicht übertragbar und erfolgt unverbindlich, wir behalten uns vor deine Reservierung jederzeit zu stornieren. <br>
                       Deine Reservierung beinhaltet lediglich den unentgeltlichen Zugang zur Veranstaltung (mögliche Getränke und/oder Speisen sind nicht enthalten)! <br>
                       <img src="' . (new QRCode())->render("https://" . $host . "/check?pin=" . $pin) . '" style="width: 25%" alt="QRCode - PIN siehe oben"/> <br>
                       Bitte storniere, wenn du doch nicht erscheinen willst! Dies kannst du über folgenden Link tun: <a href="https://' . $host . "?stornotoken=" . $stornotoken . '">https://' . $host . "?stornotoken=" . $stornotoken . "</a>";
        // prettier-ignore
        $mail->AltBody = "Deine Reservierung ist bestätigt, die PIN deiner Reservierung lautet: " . $pin . " Bitte bring ediese und/oder den folgenden QRCode (digital/analog) mit zum Einlass (falls dieser nicht (korrekt) angezeigt wird, benutze bitte einen anderen E-Mail-Client z.B. Thunderbird). \n
                          Deine Reservierung ist nicht übertragbar und erfolgt unverbindlich, wir behalten uns vor deine Reservierung jederzeit zu stornieren. \n
                          Deine Reservierung beinhaltet lediglich den unentgeltlichen Zugang zur Veranstaltung (mögliche Getränke und/oder Speisen sind nicht enthalten)! \n
                          Bitte storniere, wenn du doch nicht erscheinen willst! Dies kannst du über folgenden Link tun: https://" . $host . "?stornotoken=" . $stornotoken;

        $query = $db->prepare("UPDATE People SET cf = true WHERE bookingtoken=:bookingtoken AND cf = false;");
        $query->bindValue(":bookingtoken", $_GET["bookingtoken"]);

        if (!$query->execute()) {
            $msg = "Fehler beim eintragen in die Datenbank!" . $err;
        } elseif (!$mail->send()) {
            $msg = "Fehler beim E-Mail Versand!" . $err;
        } else {
            $msg = "Deine Reservierung wurde erfolgreich bestätigt. Du hast eine PIN und einen QRCode per E-Mail erhalten.";
            if ($ennotify) {
                $mail->isHTML(false);
                $mail->clearAddresses();
                $mail->addAddress($mail_notify, $mail_name);
                $mail->Subject = "[" . $mail_name . "] neue Reservierung " . $event;
                $mail->Body = $vn . " " . $nn . $yeartxt . " hat resviert!";
                $mail->send();
            }
        }
    } else {
        $query = $db->prepare("SELECT * FROM People WHERE bookingtoken=:bookingtoken AND cf = true");
        $query->bindValue(":bookingtoken", $_GET["bookingtoken"]);
        if (is_array($query->execute()->fetchArray())) {
            $msg = "Deine Reservierung ist bereits bestätigt!";
        } else {
            $msg = "Dieser Bestätigungslink ist unbekannt!" . $err;
        }
    }
}

if (array_key_exists("stornotoken", $_GET)) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $query = $db->prepare("SELECT * FROM People WHERE stornotoken=:stornotoken");
        $query->bindValue(":stornotoken", $_GET["stornotoken"]);
        if (is_array($query->execute()->fetchArray())) {
            $email = $query->execute()->fetchArray()["email"];
            $vn = $query->execute()->fetchArray()["vn"];
            $nn = $query->execute()->fetchArray()["nn"];
            $year = $query->execute()->fetchArray()["year"];
            if (empty($year)) {
                $yeartxt = "";
            } else {
                $yeartxt = " (" . $type_year . ": " . $year . ")";
            }

            $mail->isHTML(false);
            $mail->clearAddresses();
            $mail->addAddress($email, $vn . " " . $nn);
            $mail->Subject = "[" . $mail_name . "] Stornierungsbestätigung für " . $event;
            $mail->Body = "Deine Reservierung ist storniert, falls du doch wieder reservieren willst kannst du dies über den folgenden Link tun: https://" . $host;

            $query = $db->prepare("DELETE FROM People WHERE stornotoken=:stornotoken AND cf = true;");
            $query->bindValue(":stornotoken", $_GET["stornotoken"]);

            if (!$query->execute()) {
                $msg = "Fehler beim eintragen in die Datenbank!" . $err;
            } elseif (!$mail->send()) {
                $msg = "Fehler beim E-Mail Versand!" . $err;
            } else {
                $msg = "Deine Stornierung wurde erfolgreich bestätigt.";
                if ($ennotify) {
                    $mail->isHTML(false);
                    $mail->clearAddresses();
                    $mail->addAddress($mail_notify, $mail_name);
                    $mail->Subject = "[" . $mail_name . "] neue Stornierung " . $event;
                    $mail->Body = $vn . " " . $nn . $yeartxt . " hat storniert!";
                    $mail->send();
                }
            }
        } else {
            $msg = "Dieser Stornierungslink ist unbekannt!" . $err;
        }
    } else {
        $msg = 'Bitte drücke den folgenden Knopf, um deine Stornierung zu bestätigen: <form method="post"><input type="submit" value="Stornierung bestätigen!" onClick="this.hidden=true;"></form>';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <title><?php echo "$mail_name für $event"; ?></title>
    <meta charset="utf-8">
    <link rel="icon" type="image/webp" href="/favicon.webp">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body>
<div style="text-align: center;">
    <h1><?php echo "$mail_name für $event"; ?></h1>
    <p>Es sind noch <b><?php echo $free; ?></b> Plätze frei! <br></p>
<?php
if (!array_key_exists("bookingtoken", $_GET) && !array_key_exists("stornotoken", $_GET) && $free > 0 && !$sr) { ?>
    <form method="post">
        <label for="vn">Vorname: </label><input type="text" name="vn" id="vn" maxlength="255" required><br>
        <label for="nn">Nachname: </label><input type="text" name="nn" id="nn" maxlength="255" required><br>
        <label for="email">E-Mail: </label><input type="email" name="email" id="email" maxlength="255" required><br>
        <?php if ($enyear) { ?>
        <label for="year"><?php echo $msg_year; ?></label><br>
        <select name="year" id="year" required>
            <option value="" disabled selected>Bitte auswählen!</option>
            <?php for ($i = $min_year; $i <= $max_year; $i++) {
                echo '<option value="' . $i . '">' . $i . "</option>";
            } ?>
        </select>
        <?php } ?>
        <div class="h-captcha" data-sitekey="caa8d917-b2d3-4c48-b56b-c0dcc26955d7"></div>
        <input type="submit" value="Jetzt kostenfrei Reservieren!" onClick="this.hidden=true;">
    </form>
<?php }
if (!empty($msg)) {
    echo "<p>Hinweis: <b>" . $msg . "</b></p>";
}
if (!array_key_exists("bookingtoken", $_GET) && !array_key_exists("stornotoken", $_GET) && $free > 0 && !$sr) { ?>
    <p>
    Bitte fülle dieses Formular (Vorname, Nachname, E-Mail<?php echo ", " . $type_year; ?>) aus und klicke auf "Jetzt kostenfrei Reservieren". <br>
    Danach erhältst du eine E-Mail zugesendet, öffne den darin enthaltenen Link um deine Reservierung zu bestätigen - tust du dies nicht, wird deine Reservierung nicht im System registriert. <br>
    Nachdem du deine Reservierung bestätigt hast, wird dir eine weitere E-Mail zugesandt darin findest du einem QR-Code und eine PIN, bitte bringe beides zum Einlass mit!<br>
    In dieser E-Mail findest du auch einen Link, mit welchem du deine Reservierung jederzeit stornieren kannst! <br>
    </p>
<?php }
?>
    <details>
        <summary>(Rechtliche) Hinweise:</summary>
        <p>
            Mit "wir" bzw. "uns" ist gemeint: <?php echo $people; ?> <br>
            Die Reservierung ist nicht übertragbar und erfolgt unverbindlich, wir behalten uns vor deine Reservierung jederzeit zu stornieren. <br>
            Bitte verwende deine(n) echten Vor- bzw. Rufnamen und Nachnamen, damit wir dich am Einlass im Notfall auch ohne PIN und QRCode erkennen können! (Die Verwendung von Vor- bzw. Rufnamen des dgti-Ergänzungsausweis ist zulässig) <br>
            Die Reservierung beinhaltet lediglich den unentgeltlichen Zugang zur Veranstaltung (mögliche Getränke und/oder Speisen sind nicht enthalten)! Bitte storniere dennoch deine Reservierung falls du doch nicht kommen willst! <br>
            Deine Daten werden unserseits digital aus der Datenbank gelöscht, sobald du deine Reservierung stornierst (nicht E-Mail Benachrichtigungen)! <br>
            Auch werden all deine unserseits digital gespeicherten Daten (auch E-Mail Benachrichtigungen), sowie mögliche analoge Kopien unserseits schnellstmöglich nach der Veranstaltung oder bei Absage dieser gelöscht bzw. zerstört! <br>
            Falls wir deine Daten an staatliche Stellen weitergeben mussten, haben wir keinen Einfluss was diese mit deinen Daten tun.
            Wir benutzten deine E-Mail-Adresse nur um dir einen Bestätigungslink, sowie Reservierungs- bzw. Stornierungsbestätigungen und mögliche Informationen zu Planänderungen zuzusenden. <br>
            Deine eingegebenen Daten werden verschlüsselt übertragen, aber unverschlüsselt in einer Datenbank auf einem Server in Deutschland gespeichert und verarbeitet <br>
            Bei Reservierungsbestätigung oder Stornierung deinerseits ist es möglich, dass wir uns eine Benachrichtigung zusenden lassen, welche deine Namen und deine Aktion (Bestätigung/Stornierung) beinhaltet! <br>
            Zugriff auf deine Daten haben nur wir, sowie sofern nötig staatliche Stellen (z.B. Ordnungsamt, etc.), eine weitergabe deiner eingegebenen Daten an in diesem Hinweis nicht angegebene Dritte erfolgt nicht! <br>
            Dieser Zugriff erfolgt Passwort geschützt und/oder analog.<br>
            Deine IP-Adresse kann digital an Dienste wie <a href="https://www.crowdsec.net">CrowdSec</a> gesendet werden, um die Sicherheitsgefahr, welche von deiner IP ausgehen könnte zu ermitteln. <br>
            Wir benutzten hCaptcha um zu überprüfen, ob du ein Mensch bist, hier findest du deren <a href="https://www.hcaptcha.com/privacy">Datenschutzbestimmungen</a> und <a href="https://www.hcaptcha.com/terms">AGBs</a>. <br>
            Auftretende Fehler werden für unbestimmte Zeit gespeichert. <br>
            Ein unauthorisierter Datenabfluss wird versucht durch technische Maßnahmen zu verhindern, dennoch gibt es dafür keine Garantie, falls du eine Sicherheitslücke findest, melde diese bitte <a href="https://github.com/ZoeyVid/booking/security/advisories/new">hier</a> oder <a href="mailto:<?php echo $err_support; ?>">hier</a>.<br><br>
        
            Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen. <br>
            Die Inhalte unserer Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen. Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.<br><br>
            Impressum/Verantwortlich im Sinne des § 5 TMG: <?php echo $impressum; ?><br>
        </p>
    </details>
    <p>
        <a href="https://github.com/ZoeyVid/booking">Quellcode</a> - <a href="https://www.mozilla.org/en-US/MPL/2.0">MPL-2.0 Lizenz</a> - integrierte Projekte/Software: <a href="https://github.com/PHPMailer/PHPMailer">PHPMailer</a>, <a href="https://github.com/chillerlan/php-qrcode">php-qrcode</a> und hCaptcha/reCAPTCHA (sowie PHP mit sqlite3, curl, ctype, und openssl)
    </p>
</div>
</body>
</html>

<!--
bestätigung stornierung
s/mime für no-reply fixen
