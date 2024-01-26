<?php
include "../config.php";
$tz = $config["tz"];
$host = $config["host"];
$db_name = $config["db_name"];
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
$readpswd = $config["readpswd"];
$err = " Fehler! Wenn dieser Fehler öfter auftritt bitte bei " . $err_support . " melden!";

date_default_timezone_set($tz);
require "../vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;

if ($readpswd !== "") {

    date_default_timezone_set($tz);
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

    $db = new SQLite3("../" . $db_name);
    $db->exec("CREATE TABLE IF NOT EXISTS People (email CHAR(255) UNIQUE NOT NULL, pin CHAR(6) UNIQUE NOT NULL, vn CHAR(255) NOT NULL, nn CHAR(255) NOT NULL, year CHAR(4), bookingtoken CHAR(255) UNIQUE NOT NULL, stornotoken CHAR(255) UNIQUE NOT NULL, cf BOOLEAN NOT NULL, cdate CHAR(255))");
    $db->exec("VACUUM");
    ?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title><?php echo "Datenbankausgabe für $event"; ?></title>
    <meta charset="utf-8">
    <link rel="icon" type="image/webp" href="../favicon.webp">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body>
<div style="text-align: center;">
        <h1><?php echo "Datenbankausgabe für $event"; ?></h1>
        <?php if (!(array_key_exists("pswd", $_POST) && array_key_exists("h-captcha-response", $_POST))) { ?>
        <form method="post">
            <label for="pswd">Passwort: </label><input type="password" name="pswd" id="pswd" maxlength="255" required><br>
            <div class="h-captcha" data-sitekey="caa8d917-b2d3-4c48-b56b-c0dcc26955d7"></div>
            <input type="submit" value="Datenbank auslesen!">
        </form>
        <?php } ?>
<?php if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!(array_key_exists("pswd", $_POST) && array_key_exists("h-captcha-response", $_POST))) {
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
            if ($ennotify) {
                $mail->Subject = "[" . $mail_name . "] ACHTUNG: Erfolgloser Versuch die Datenbank auszulesen für " . $event;
                $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolglos versucht die Datenbank auszulesen!";
                $mail->send();
            }
        } elseif ($pswd !== $readpswd) {
            $msg = "Das Passwort ist ungültig!" . $err;
            if ($ennotify) {
                $mail->Subject = "[" . $mail_name . "] ACHTUNG: Erfolgloser Versuch die Datenbank auszulesen für " . $event;
                $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolglos versucht die Datenbank auszulesen!";
                $mail->send();
            }
        } else {

            $mail->Subject = "[" . $mail_name . "] ACHTUNG: Die Datenbank wurde ausgelesen für " . $event;
            $mail->Body = $_SERVER["REMOTE_ADDR"] . " hat erfolgreich die Datenbank ausgelesen!";
            if (($ennotify && $mail->send()) || !$ennotify) {
                $results = $db->query("SELECT * FROM People"); ?>

<div style="display: flex; justify-content: center;">
    <table style="align-self: center;">
        <tr>
            <th>E-Mail</th>
            <th>PIN</th>
            <th>Vorname</th>
            <th>Nachname</th>
            <?php if ($enyear) { ?><th><?php echo $type_year; ?></th><?php } ?>
            <th>Bookingtoken</th>
            <th>Stronotoken</th>
            <th>Buchung bestätigt?</th>
            <th>bereits kontrolliert?</th>
        </tr>
        <?php while ($row = $results->fetchArray(SQLITE3_ASSOC)) { ?>
        <tr>
            <td><?php echo $row["email"]; ?></td>
            <td><?php echo $row["pin"]; ?></td>
            <td><?php echo $row["vn"]; ?></td>
            <td><?php echo $row["nn"]; ?></td>
            <?php if ($enyear) { ?><td><?php echo $row["year"]; ?></td><?php } ?>
            <td><?php echo '<a href="https://' . $host . "?bookingtoken=" . $row["bookingtoken"] . '">' . $row["bookingtoken"] . "</a>"; ?></td>
            <td><?php echo '<a href="https://' . $host . "?stornotoken=" . $row["stornotoken"] . '">' . $row["stornotoken"] . "</a>"; ?></td>
            <td><?php if ($row["cf"]) {
                echo "Ja";
            } else {
                echo "Nein";
            } ?></td>
            <td><?php if (empty($row["cdate"])) {
                echo "Noch nicht kontrolliert!";
            } else {
                echo "Ja, am " . $row["cdate"];
            } ?></td>
        </tr>
        <?php }
            }
            ?>
    </table>
</div>

<?php
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
</div>
</body>
</html>