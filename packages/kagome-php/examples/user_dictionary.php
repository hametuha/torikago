<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kagome\Analyzer;

try {
    $analyzer = new Analyzer();

    // Create a sample user dictionary file
    $userDictPath = __DIR__ . '/custom_dict.txt';
    $userDictContent = <<<EOF
# Custom user dictionary for example
# Format: <surface>,<tokens>,<readings>,<part-of-speech>
固有名詞例,固有名詞例,コユウメイシレイ,名詞
専門用語,専門用語,センモンヨウゴ,名詞
カスタム単語,カスタム 単語,カスタム タンゴ,名詞
EOF;

    file_put_contents($userDictPath, $userDictContent);
    echo "Created user dictionary: $userDictPath\n\n";

    // Example text containing custom words
    $text = '固有名詞例と専門用語とカスタム単語を含むテキストです。';

    echo "=== Analysis without user dictionary ===\n";
    $resultWithoutDict = $analyzer->analyze($text);
    foreach ($resultWithoutDict as $token) {
        echo sprintf("  %s (%s)\n", $token['surface'], $token['features'][0] ?? '');
    }

    echo "\n=== Analysis with user dictionary ===\n";
    $resultWithDict = $analyzer->analyze($text, ['udict' => $userDictPath]);
    foreach ($resultWithDict as $token) {
        echo sprintf("  %s (%s)\n", $token['surface'], $token['features'][0] ?? '');
    }

    echo "\n=== Tokenize with user dictionary ===\n";
    $tokens = $analyzer->tokenize($text, ['udict' => $userDictPath]);
    foreach ($tokens as $token) {
        echo sprintf("  Surface: %s, POS: %s\n", $token['surface'], $token['pos']);
    }

    // Clean up
    unlink($userDictPath);
    echo "\nCleaned up user dictionary file.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";

    // Clean up on error
    if (isset($userDictPath) && file_exists($userDictPath)) {
        unlink($userDictPath);
    }
}
