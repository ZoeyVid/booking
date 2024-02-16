<?php
include "../config.php";
$tz = $config["tz"];
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
$recaptcha_secret = $config["recaptcha_secret"];
$recaptcha_key = $config["recaptcha_key"];
$recaptcha_score = $config["recaptcha_score"];
$err_support = $config["err_support"];
$event = $config["event"];
$enyear = $config["enyear"];
$type_year = $config["type_year"];
$ensmime = $config["ensmime"];
$smimecert = $config["smimecert"];
$smimekey = $config["smimekey"];
$smimecertchain = $config["smimecertchain"];
$smimepass = $config["smimepass"];
$checkpswd = $config["checkpswd"];
$err = " Fehler! Wenn dieser Fehler öfter auftritt bitte bei " . $err_support . " melden!";

use ReCaptcha\ReCaptcha;
date_default_timezone_set($tz);
require "../vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;

if ($checkpswd !== "") {

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
    $mail->addAddress($mail_notify, $mail_name);
    if ($ensmime) {
        $mail->sign($smimecert, $smimekey, $smimepass, $smimecertchain);
    }

    $db = new SQLite3($db_path);
    $db->exec("CREATE TABLE IF NOT EXISTS People (email CHAR(255) UNIQUE NOT NULL, pin CHAR(6) UNIQUE NOT NULL, vn CHAR(255) NOT NULL, nn CHAR(255) NOT NULL, year CHAR(4), bookingtoken CHAR(255) UNIQUE NOT NULL, stornotoken CHAR(255) UNIQUE NOT NULL, cf BOOLEAN NOT NULL, cdate CHAR(255))");
    $db->exec("VACUUM");

    session_start([
        "use_strict_mode" => true,
        "cookie_path" => "/check",
        "cookie_secure" => true,
        "cookie_httponly" => true,
        "cookie_samesite" => "Strict",
        "cookie_domain" => $host,
    ]);

    if ($_SERVER["REQUEST_METHOD"] === "GET" && array_key_exists("pin", $_GET)) {
        $vp = $_GET["pin"];
    } else {
        $vp = "";
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && array_key_exists("pswd", $_POST)) {
        $_SESSION["pswd"] = $_POST["pswd"];
    } elseif (empty($_SESSION["pswd"])) {
        $_SESSION["pswd"] = "";
    }
    ?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title><?php echo "Reservierungskontrolle für $event"; ?></title>
    <meta charset="utf-8">
    <link rel="icon" type="image/webp" href="../favicon.webp">
    <script src="https://www.google.com/recaptcha/api.js?trustedtypes=true"></script>
    <script>
        function onSubmit(token) {
            document.getElementById("checker").submit();
        }
    </script>
</head>
<body>
<div style="text-align: center;">
        <h1><?php echo "Reservierungskontrolle für $event"; ?></h1>
        <form method="post" id="checker">
            <label for="pin">PIN: </label><input value="<?php echo $vp; ?>" type="text" name="pin" id="pin" maxlength="6" required><br>
            <label for="pswd">Passwort: </label><input value="<?php echo $_SESSION["pswd"]; ?>" type="password" name="pswd" id="pswd" maxlength="255" required><br>
            <input class="g-recaptcha" data-sitekey="<?php echo $recaptcha_key; ?>" data-callback="onSubmit" data-action="check" type="submit" value="PIN überprüfen!">
        </form>
<?php if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!(array_key_exists("pin", $_POST) && array_key_exists("pswd", $_POST) && array_key_exists("g-recaptcha-response", $_POST))) {
        $msg = "Formular fehlerhaft!" . $err;
    } else {
        $pswd = $_POST["pswd"];
        $pin = $_POST["pin"];
        $recaptcha = new ReCaptcha($recaptcha_secret);
        $responseData = $recaptcha
            ->setExpectedHostname($host)
            ->setExpectedAction("check")
            ->setScoreThreshold($recaptcha_score)
            ->verify($_POST["g-recaptcha-response"], $_SERVER["REMOTE_ADDR"]);
        $query = $db->prepare("SELECT * FROM People WHERE pin=:pin AND cf = true");
        $query->bindValue(":pin", $pin);
        if (!$responseData->isSuccess()) {
            $msg = "reCAPTCHA ungültig!" . $err;
        } elseif (!is_array($query->execute()->fetchArray())) {
            $msg = "Die eingegebene PIN ist ungültig!";
            if ($ennotify) {
                $mail->Subject = "[" . $mail_name . "] ACHTUNG: Erfolgloser Versuch eine PIN zu überprüfen für " . $event;
                $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolglos versucht eine PIN zu überprüfen (PIN ungültig)!\nVerwendetes Passwort: " . $pswd . "\nVerwendete PIN: " . $pin;
                $mail->send();
            }
        } elseif ($pswd !== $checkpswd) {
            $msg = "Das Passwort ist ungültig!" . $err;
            if ($ennotify) {
                $mail->Subject = "[" . $mail_name . "] ACHTUNG: Erfolgloser Versuch eine PIN zu überprüfen für " . $event;
                $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolglos versucht eine PIN zu überprüfen (Passwort ungültig)!\nVerwendetes Passwort: " . $pswd . "\nVerwendete PIN: " . $pin;
                $mail->send();
            }
        } else {
             ?>

<div style="display: flex; justify-content: center;">
    <table style="align-self: center;">
        <tr>
            <th>Vorname</th>
            <th>Nachname</th>
            <?php if ($enyear) { ?><th><?php echo $type_year; ?></th><?php } ?>
            <th>bereits kontrolliert?</th>
        </tr>
        <tr>
            <td><?php echo htmlspecialchars($query->execute()->fetchArray()["vn"]); ?></td>
            <td><?php echo htmlspecialchars($query->execute()->fetchArray()["nn"]); ?></td>
            <?php if ($enyear) { ?><td><?php echo htmlspecialchars($query->execute()->fetchArray()["year"]); ?></td><?php } ?>
            <td><?php if (empty($query->execute()->fetchArray()["cdate"])) {
                echo "Noch nicht kontrolliert!";
            } else {
                echo "Ja, am " . $query->execute()->fetchArray()["cdate"];
            } ?></td>
        </tr>
    </table>
</div>

<?php
$getdate = getdate();
$cdate = $getdate["mday"] . "." . $getdate["mon"] . "." . $getdate["year"] . " um " . $getdate["hours"] . ":" . $getdate["minutes"] . "." . $getdate["seconds"];
$query = $db->prepare("UPDATE People SET cdate=:cdate WHERE pin=:pin AND cf = true");
$query->bindValue(":pin", $pin);
$query->bindValue(":cdate", $cdate);
if (!$query->execute()) {
    $msg = "Fehler beim ändern des Datums der letzten Reservierungskontrolle";
}

        }
    }
}
} else {
    $msg = "Die Reservierungskontrolle ist deaktiviert!";
}
if (!empty($msg)) {
    echo "<p>Hinweis: <b>" . $msg . "</b></p>";
}
?>
<br><a href="https://github.com/ZoeyVid/booking">Quellcode</a> - <a href="https://www.mozilla.org/en-US/MPL/2.0">MPL-2.0 Lizenz</a> - integrierte Projekte/Software: <a href="https://github.com/PHPMailer/PHPMailer">PHPMailer</a>, <a href="https://github.com/chillerlan/php-qrcode">php-qrcode</a> und hCaptcha/reCAPTCHA (sowie PHP mit sqlite3, curl, ctype, openssl und session)
</div>
</body>
</html>
