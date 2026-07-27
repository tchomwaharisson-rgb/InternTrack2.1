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
?>