<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "kikomatourdb";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8mb4');

$conn->query("
    CREATE TABLE IF NOT EXISTS inquiries (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name       VARCHAR(120) NOT NULL,
        email      VARCHAR(180) NOT NULL,
        phone      VARCHAR(30)  NOT NULL DEFAULT '',
        message    TEXT         NOT NULL,
        status     ENUM('new','pending','replied') NOT NULL DEFAULT 'new',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
?>
