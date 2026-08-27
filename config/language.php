<?php

// Load language files
$lang_files = [
    'en' => __DIR__ . '/language_en.php',
    'fr' => __DIR__ . '/language_fr.php',
];

// Default language
$default_lang = 'fr';

// Get current language from session, cookie, or default
$preferred_lang = $_SESSION['language'] ?? ($_COOKIE['language'] ?? $default_lang);
if (!in_array($preferred_lang, ['en', 'fr'], true)) {
    $preferred_lang = $default_lang;
}
$current_lang = $preferred_lang;

if (!isset($_SESSION['language']) || $_SESSION['language'] !== $current_lang) {
    $_SESSION['language'] = $current_lang;
}

// Load translations
$translations = [];
if (isset($lang_files[$current_lang]) && file_exists($lang_files[$current_lang])) {
    $translations = require $lang_files[$current_lang];
} else {
    // Fallback to English
    $translations = require $lang_files['en'];
}

/**
 * Translation function
 * @param string $key The translation key
 * @param array $params Optional parameters for placeholder replacement
 * @return string The translated text
 */
function t($key, $params = []) {
    global $translations;
    
    // Get the translation
    $text = $translations[$key] ?? $key;
    
    // Replace parameters if any
    foreach ($params as $param => $value) {
        $text = str_replace('{' . $param . '}', $value, $text);
    }
    
    return $text;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return t('just_now');
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . 'm ' . t('ago');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h ' . t('ago');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd ' . t('ago');
    } else {
        return date('M d, Y', $time);
    }
}


/**
 * Translate date to current language
 * @param string $date The date string or timestamp
 * @param string $format The date format (strftime format)
 * @return string Formatted date in current language
 */
function translateDate($date, $format = '%B %d, %Y') {
    if (is_string($date)) {
        $timestamp = strtotime($date);
    } else {
        $timestamp = $date;
    }
    
    if ($timestamp === false) {
        return $date;
    }
    
    // Set locale based on language
    $lang = $_SESSION['language'] ?? 'en';
    if ($lang === 'fr') {
        setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr');
    } else {
        setlocale(LC_TIME, 'en_US.utf8', 'en_US', 'en');
    }
    
    return strftime($format, $timestamp);
}

/**
 * Translate date to short format
 */
function translateDateShort($date) {
    return translateDate($date, '%b %d, %Y');
}

/**
 * Translate date to long format
 */
function translateDateLong($date) {
    return translateDate($date, '%A, %B %d, %Y');
}

/**
 * Translate time only
 */
function translateTime($date) {
    return translateDate($date, '%H:%M');
}

/**
 * Translate datetime
 */
function translateDateTime($date) {
    return translateDate($date, '%b %d, %Y at %H:%M');
}

/**
 * Get translated month name
 */
function translateMonth($monthNumber, $short = false) {
    $format = $short ? '%b' : '%B';
    $timestamp = mktime(0, 0, 0, $monthNumber, 1, 2024);
    return translateDate($timestamp, $format);
}

/**
 * Get translated day name
 */
function translateDay($dayNumber, $short = false) {
    $format = $short ? '%a' : '%A';
    // Sunday = 0, Monday = 1, ...
    $timestamp = mktime(0, 0, 0, 1, $dayNumber + 1, 2024);
    return translateDate($timestamp, $format);
}
?>