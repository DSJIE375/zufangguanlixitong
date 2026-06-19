<?php
require_once '../includes/database.php';

session_destroy();

header("Location: login.php");
exit;
