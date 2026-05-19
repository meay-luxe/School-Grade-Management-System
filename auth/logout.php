<?php
require_once '../helpers/Auth.php';

Auth::logout();
header('Location: login.php');
exit();