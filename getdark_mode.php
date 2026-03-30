<?php
/**
 * getdark_mode.php
 * ─────────────────────────────────────────────────────
 * 1. Sets  $dark_mode  (int 0 or 1) for use in inline PHP checks
 * 2. Echoes the darkmode <link> tag when dark mode is ON
 *
 * Include ONCE inside <head> – it is safe to call multiple times
 * because the variable is only fetched once per request.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Only run the DB query once per request */
if (!isset($dark_mode)) {

    $dark_mode = 0; // safe default

    if (isset($_SESSION['user_id'])) {
        /*
         * $con must already exist (require'd by the parent page).
         * We do NOT require connect.php here to avoid double-connection.
         */
        if (isset($con)) {
            $dm_stmt = $con->prepare("SELECT dark_mode FROM users WHERE user_id = ?");
            $dm_stmt->bind_param("i", $_SESSION['user_id']);
            $dm_stmt->execute();
            $dm_row = $dm_stmt->get_result()->fetch_assoc();
            $dm_stmt->close();

            if ($dm_row) {
                $dark_mode = (int)$dm_row['dark_mode'];
            }
        }
    }
}

/* Output the stylesheet link when dark mode is active */
if ($dark_mode == 1) {
    echo '<link rel="stylesheet" href="darkmode.css">' . PHP_EOL;
}