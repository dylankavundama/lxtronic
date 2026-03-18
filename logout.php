<?php
require_once 'includes/helpers.php';
session_destroy();
header("Location: login.php");
exit();
?>
