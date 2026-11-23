<?php
$link = mysqli_connect("localhost", "root", "123456789", "cleannest_db");

if (!$link) {
    die("connection faild: " . mysqli_connect_error());
}
