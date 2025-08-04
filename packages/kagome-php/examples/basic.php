<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kagome\Analyzer;

try {
    $analyzer = new Analyzer();

    // Basic analysis
    echo "=== Basic Analysis ===\n";
    $text = 'すもももももももものうち';
    $result = $analyzer->analyze($text);
    echo "Input: $text\n";
    echo "Result:\n";
    print_r($result);

    // Tokenize method
    echo "\n=== Tokenize Method ===\n";
    $text = '東京タワーに行きました';
    $tokens = $analyzer->tokenize($text);
    echo "Input: $text\n";
    echo "Tokens:\n";
    foreach ($tokens as $token) {
        echo sprintf("  %s (%s)\n", $token['surface'], $token['pos']);
    }

    // Different modes
    echo "\n=== Different Modes ===\n";
    $text = '関西国際空港';

    echo "Normal mode:\n";
    $normal = $analyzer->tokenize($text, ['mode' => 'normal']);
    foreach ($normal as $token) {
        echo sprintf("  %s\n", $token['surface']);
    }

    echo "\nSearch mode:\n";
    $search = $analyzer->tokenize($text, ['mode' => 'search']);
    foreach ($search as $token) {
        echo sprintf("  %s\n", $token['surface']);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
