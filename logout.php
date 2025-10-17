<?php
// Start the session so we can access and modify it.
session_start();

// Unset all of the session variables. This clears all login data.
$_SESSION = array();

// Finally, destroy the session completely.
session_destroy();

// Redirect the user back to the login page.
header('Location: login.php');
exit;
?>