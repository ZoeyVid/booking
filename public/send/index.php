<?php
include "../../config.php";
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
$hcaptcha_secret = $config["hcaptcha_secret"];
$hcaptcha_key = $config["hcaptcha_key"];
$err_support = $config["err_support"];
$event = $config["event"];
$enyear = $config["enyear"];
$type_year = $config["type_year"];
$sendpswd = $config["sendpswd"];
$ensmime = $config["ensmime"];
$smimecert = $config["smimecert"];
$smimekey = $config["smimekey"];
$smimecertchain = $config["smimecertchain"];
$smimepass = $config["smimepass"];
$err = " Fehler! Wenn dieser Fehler öfter auftritt bitte bei " . $err_support . " melden!";

date_default_timezone_set($tz);
require "../../vendor/autoload.php";
use chillerlan\QRCode\QRCode;
use PHPMailer\PHPMailer\PHPMailer;

if ($sendpswd !== "") {

$mail = new PHPMailer();
$mail->isSMTP();
$mail->setLanguage("de", "../../vendor/phpmailer/phpmailer/language");
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
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title><?php echo "E-Mail-Versand für $event"; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html {
            -ms-text-size-adjust: none;
            -webkit-text-size-adjust: none;
            text-size-adjust: none;
        }
    </style>
    <link rel="icon" type="image/webp" href="../favicon.webp">
    <script src="https://js.hcaptcha.com/1/api.js?hl=de&render=onload&recaptchacompat=off" async defer></script>
</head>
<body>
<div style="text-align: center;">
    <h1><?php echo "E-Mail-Versand für $event"; ?></h1>
    <?php if (!(array_key_exists("pswd", $_POST) && array_key_exists("h-captcha-response", $_POST))) { ?>
        <form method="post" id="form">
            <label for="pswd">Passwort: </label><input type="password" name="pswd" id="pswd" maxlength="255" required><br>
            <fieldset id="who">
                <legend>Wen?</legend>
                <input type="radio" id="cf" name="who" value="cf" checked><label for="cf">Bestätigte</label>
                <input type="radio" id="ncf" name="who" value="ncf"><label for="ncf">NICHT Bestätigte</label>
                <input type="radio" id="all" name="who" value="all"><label for="all">Alle</label>
            </fieldset>
            <fieldset id="html-mode">
                <legend>HTML?</legend>
                <input type="radio" id="html" name="html-mode" value="yes" onclick="document.getElementById('html-text-label').hidden = false; document.getElementById('html-text').hidden = false; document.getElementById('html-text').disabled = false; document.getElementById('keep-plain').hidden = false; document.getElementById('keep-plain').disabled = false;"><label for="html">Ja</label>
                <input type="radio" id="plain" name="html-mode" value="no" onclick="document.getElementById('html-text-label').hidden = true; document.getElementById('html-text').hidden = true; document.getElementById('html-text').disabled = true; document.getElementById('keep-plain').hidden = true; document.getElementById('keep-plain').disabled = true;" checked><label for="plain">Nein</label>
            </fieldset>
            <fieldset id="keep-plain" disabled hidden="hidden">
                <legend>Dennoch Plain senden?</legend>
                <input type="radio" id="no-plain" name="keep-plain" value="yes" onclick="document.getElementById('plain-text-label').hidden = false; document.getElementById('plain-text').hidden = false; document.getElementById('plain-text').disabled = false" checked><label for="no-plain">Ja</label>
                <input type="radio" id="only-html" name="keep-plain" value="no" onclick="document.getElementById('plain-text-label').hidden = true; document.getElementById('plain-text').hidden = true; document.getElementById('plain-text').disabled = true"><label for="only-html">Nein</label>
            </fieldset>
            <br><label for="subject">Betreff: </label><input type="text" name="subject" id="subject" maxlength="255" required><br>
            <label for="html-text" id="html-text-label" hidden="hidden"><br>HTML Text:<br></label><textarea id="html-text" name="html-text" form="form" style="width: calc(200%/3);" disabled hidden="hidden"></textarea>
            <label for="plain-text" id="plain-text-label"><br>Text:<br></label><textarea id="plain-text" name="plain-text" form="form" style="width: calc(200%/3);" required></textarea><br>
            <div class="h-captcha" data-sitekey="<?php echo $hcaptcha_key; ?>"></div>
            <input type="submit" value="E-Mails versenden!" onClick="this.hidden=true;">
        </form>
    <?php } ?>
    <?php if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!array_key_exists("pswd", $_POST) || !array_key_exists("h-captcha-response", $_POST) || !array_key_exists("who", $_POST) || !array_key_exists("subject", $_POST) || !array_key_exists("html", $_POST) || ($_POST["html"] === "no" && !array_key_exists("plain-text", $_POST)) || ($_POST["html"] === "yes" && !array_key_exists("html-text", $_POST)) || ($_POST["keep-plain"] === "yes" && !array_key_exists("plain-text", $_POST))) {
            $msg = "Formular fehlerhaft!" . $err;
        } else {
            $pswd = $_POST["pswd"];
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
            if (!$responseData->success) {
                $msg = "hCaptcha ungültig!" . $err;
            } elseif ($pswd !== $sendpswd) {
                $msg = "Das Passwort ist ungültig!" . $err;
                if ($ennotify) {
                    $mail->Subject = "[" . $mail_name . "] ACHTUNG: Erfolgloser Versuch eine Rund-E-Mail zu versenden für " . $event;
                    $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolglos versucht eine Rund-E-Mail zu versenden! Verwendetes Passwort: " . $pswd;
                    $mail->send();
                }
            } else {
                $mail->Subject = "[" . $mail_name . "] ACHTUNG: Rund-E-Mail versandt für " . $event;
                $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolgreich eine Rund-E-Mail versandt!";
                if (($ennotify && $mail->send()) || !$ennotify) {
                    if ($_POST["who"] === "cf") {
                        $results = $db->query("SELECT * FROM People WHERE cf = true");
                    } elseif ($_POST["who"] === "ncf") {
                        $results = $db->query("SELECT * FROM People WHERE cf = false");
                    } elseif ($_POST["who"] === "all") {
                        $results = $db->query("SELECT * FROM People");
                    }
                    if ($_POST["who"] === "cf" || $_POST["who"] === "ncf" || $_POST["who"] === "all") {
                        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                            $plain = $_POST["plain-text"]->replace("?email?", $row["email"])->replace("?pin?", $row["pin"])->replace("?vn?", $row["vn"])->replace("?nn?", $row["nn"])->replace("?bookinglink?", "https://" . $host . "?bookingtoken=" . $row["bookingtoken"])->replace("?stornolink?", "https://" . $host . "?stornotoken=" . $row["stornotoken"]);
                            if ($enyear) {
                                $plain = $plain->replace("?year?", $row["year"]);
                            }
                            $html = $_POST["html-text"]->replace("?email?", $row["email"])->replace("?pin?", $row["pin"])->replace("?vn?", $row["vn"])->replace("?nn?", $row["nn"])->replace("?bookinglink?", '<a href="https://' . $host . "?bookingtoken=" . $row["bookingtoken"] . '">https://' . $host . "?bookingtoken=" . $row["bookingtoken"] . "</a>")->replace("?stornolink?", '<a href="https://' . $host . "?stornotoken=" . $row["stornotoken"] . '">https://' . $host . "?stornotoken=" . $row["stornotoken"] . "</a>")->replace("?qrcode?", '<img src="' . (new QRCode())->render("https://" . $host . "/check?pin=" . $row["pin"]) . '" style="width: 25%" alt="QRCode"/>');
                            if ($enyear) {
                                $html = $html->replace("?year?", $row["year"]);
                            }

                            $subject = $_POST["subject"]->replace("?email?", $row["email"])->replace("?pin?", $row["pin"])->replace("?vn?", $row["vn"])->replace("?nn?", $row["nn"])->replace("?bookinglink?", "https://" . $host . "?bookingtoken=" . $row["bookingtoken"])->replace("?stornolink?", "https://" . $host . "?stornotoken=" . $row["stornotoken"]);
                            if ($enyear) {
                                $subject = $subject->replace("?year?", $row["year"]);
                            }

                            $mail->Subject = "[" . $mail_name . "] " . $subject;
                            $mail->clearAddresses();
                            $mail->addAddress($row["email"], $row["vn"] . " " . $row["nn"]);

                            if ($_POST["html"] === "yes") {
                                $mail->isHTML();
                                $mail->Body = $html;
                                if ($_POST["keep-plain"] === "yes") {
                                    $mail->AltBody = $plain;
                                }
                            } else {
                                $mail->isHTML(false);
                                $mail->Body = $plain;
                            }
                            $mail->send();
                        }
                    } else {
                        $msg = "Formular fehlerhaft!" . $err;
                    }
                } else {
                    $msg = "Fehler!" . $err;
                }
            }
        }
    }
    } else {
        $msg = "Der Rund-Email-Versand ist deaktiviert!";
    }
    if (!empty($msg)) {
        echo "<p>Hinweis: <b>" . $msg . "</b></p>";
    }
    ?>

    <br><a href="https://github.com/ZoeyVid/booking">Quellcode</a> - <a href="https://www.mozilla.org/en-US/MPL/2.0">MPL-2.0 Lizenz</a> - integrierte Projekte/Software: <a href="https://github.com/PHPMailer/PHPMailer">PHPMailer</a>, <a href="https://github.com/chillerlan/php-qrcode">php-qrcode</a> und hCaptcha/reCAPTCHA (sowie PHP mit sqlite3, curl, ctype, openssl und session)
</div>
</body>
</html>
