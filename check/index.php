<?php
include "../config.php";
$tz = $config["tz"];
$host = $config["host"];
$db_name = $config["db_name"];
$recaptcha_secret = $config["recaptcha_secret"];
$recaptcha_key = $config["recaptcha_key"];
$recaptcha_score = $config["recaptcha_score"];
$err_support = $config["err_support"];
$event = $config["event"];
$enyear = $config["enyear"];
$type_year = $config["type_year"];
$checkpswd = $config["checkpswd"];
$err = " Fehler! Wenn dieser Fehler öfter auftritt bitte bei " . $err_support . " melden!";

date_default_timezone_set($tz);
require "../vendor/autoload.php";
use ReCaptcha\ReCaptcha;

if ($checkpswd !== "") {

    $db = new SQLite3("../" . $db_name);
    $db->exec("CREATE TABLE IF NOT EXISTS People (email CHAR(255) UNIQUE NOT NULL, pin CHAR(6) UNIQUE NOT NULL, vn CHAR(255) NOT NULL, nn CHAR(255) NOT NULL, year CHAR(4), bookingtoken CHAR(255) UNIQUE NOT NULL, stornotoken CHAR(255) UNIQUE NOT NULL, cf BOOLEAN NOT NULL, cdate CHAR(255))");
    $db->exec("VACUUM");

    if (array_key_exists("pin", $_POST)) {
        $vp = $_POST["pin"];
    } elseif (array_key_exists("pin", $_GET)) {
        $vp = $_GET["pin"];
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
    <?php if (true) { ?>
        <h1><?php echo "Reservierungskontrolle für $event"; ?></h1>
        <form method="post" id="checker">
            <label for="pin">PIN: </label><input value="<?php echo $vp; ?>" type="text" name="pin" id="pin" maxlength="6" required><br>
            <label for="pswd">Passwort: </label><input value="<?php echo $_POST["pswd"]; ?>" type="password" name="pswd" id="pswd" maxlength="255" required><br>
            <input class="g-recaptcha" data-sitekey="<?php echo $recaptcha_key; ?>" data-callback="onSubmit" data-action="check" type="submit" value="PIN überprüfen!">
        </form>
    <?php } ?>
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
        } elseif ($pswd !== $checkpswd) {
            $msg = "Das Passwort ist ungültig!" . $err;
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
            <td><?php echo $query->execute()->fetchArray()["vn"]; ?></td>
            <td><?php echo $query->execute()->fetchArray()["nn"]; ?></td>
            <?php if ($enyear) { ?><td><?php echo $query->execute()->fetchArray()["year"]; ?></td><?php } ?>
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
</div>
</body>
</html>