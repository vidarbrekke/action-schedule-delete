<?php
function wcac_log($message, $level = 'info', $context = 'general') {
    // Toggle: set to true to log to DB, false for error_log
    $log_to_db = false; // Set to true to use DB logging

    if ($log_to_db && class_exists('Wcac_Log')) {
        Wcac_Log::write($level, $context, $message);
    } else {
        error_log("[WCAC $level][$context] $message");
    }
} 