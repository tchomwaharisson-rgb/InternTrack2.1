<?php
// config/language.php - Main Language Loader

// Load language files
$lang_files = [
    'en' => __DIR__ . '/language_en.php',
    'fr' => __DIR__ . '/language_fr.php',
];

// Default language
$default_lang = 'en';

// Get current language from session or default
$current_lang = $_SESSION['language'] ?? $default_lang;

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

/**
 * Get available languages
 * @return array Array of language codes and names
 */
// function getAvailableLanguages() {
//     return ['en' => 'English', 'fr' => 'Français'];
// }

/**
 * Get current language
 * @return string Current language code
 */
// function getCurrentLanguage() {
//     return $_SESSION['language'] ?? 'en';
// }

/**
 * Time ago function
 * @param string $datetime The datetime string
 * @return string Human readable time difference
 */
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