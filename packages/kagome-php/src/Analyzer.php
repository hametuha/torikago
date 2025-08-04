<?php

namespace Kagome;

use RuntimeException;

/**
 * Kagome Japanese morphological analyzer wrapper for PHP
 *
 * This class provides a PHP interface to the Kagome morphological analyzer binary.
 * It handles process communication, error handling, and result parsing.
 *
 * @package Kagome
 */
class Analyzer
{
    /**
     * @var string Path to the kagome binary executable
     */
    private string $binaryPath;

    /**
     * @var array<string, mixed> Default options for kagome analysis
     */
    private array $defaultOptions = [
        'mode' => 'normal',
        'sysdict' => 'ipa',
        'json' => false,
        'simple' => false,
        'split' => false,
        'udict' => null,
    ];

    /**
     * Constructor
     *
     * @param string|null $binaryPath Optional path to kagome binary. If not provided, will auto-detect.
     * @throws RuntimeException If the binary is not found or not executable
     */
    public function __construct(?string $binaryPath = null)
    {
        if ($binaryPath === null) {
            $binaryPath = $this->detectBinaryPath();
        }

        if (!file_exists($binaryPath)) {
            throw new RuntimeException("Kagome binary not found at: $binaryPath");
        }

        if (!is_executable($binaryPath)) {
            throw new RuntimeException("Kagome binary is not executable: $binaryPath");
        }

        $this->binaryPath = $binaryPath;
    }

    /**
     * Detect the appropriate kagome binary path based on the operating system
     *
     * @return string The detected binary path
     * @throws RuntimeException If the operating system is not supported
     */
    private function detectBinaryPath(): string
    {
        // Try Docker mount path first
        $dockerPath = '/usr/local/bin/kagome-binary/kagome-binary/';

        // Try local development path
        $localPath = dirname(__DIR__, 3) . '/bin/kagome-binary/';

        if (PHP_OS_FAMILY === 'Darwin') {
            $binaryName = 'kagome-cli-macos-arm64';
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $binaryName = 'kagome-cli-linux';
        } else {
            throw new RuntimeException('Unsupported operating system: ' . PHP_OS_FAMILY);
        }

        // Check Docker path first
        if (file_exists($dockerPath . $binaryName)) {
            return $dockerPath . $binaryName;
        }

        // Fallback to local path
        if (file_exists($localPath . $binaryName)) {
            return $localPath . $binaryName;
        }

        throw new RuntimeException(
            "Kagome binary not found. Tried: {$dockerPath}{$binaryName}, {$localPath}{$binaryName}"
        );
    }

    /**
     * Analyze Japanese text using kagome
     *
     * @param string $text The text to analyze
     * @param array<string, mixed> $options Analysis options:
     *   - mode: 'normal'|'search'|'extended' (default: 'normal')
     *   - sysdict: 'ipa'|'uni' (default: 'ipa')
     *   - simple: bool Display abbreviated dictionary contents (default: false)
     *   - split: bool Use tiny sentence splitter (default: false)
     *   - udict: string|null Path to user dictionary file (default: null)
     * @return array<int, array{surface: string, features: array<int, string>}> Array of analyzed tokens
     * @throws RuntimeException If the kagome process fails or output cannot be parsed
     */
    public function analyze(string $text, array $options = []): array
    {
        $options = array_merge($this->defaultOptions, $options);
        $options['json'] = true; // Force JSON output for easier parsing

        $command = escapeshellcmd($this->binaryPath);

        foreach ($options as $key => $value) {
            if ($value === null) {
                continue; // Skip null values
            }

            if (is_bool($value)) {
                if ($value) {
                    $command .= ' -' . $key;
                }
            } else {
                // Validate user dictionary file exists
                if ($key === 'udict' && !file_exists($value)) {
                    throw new RuntimeException("User dictionary file not found: $value");
                }
                $command .= ' -' . $key . ' ' . escapeshellarg($value);
            }
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start kagome process');
        }

        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException("Kagome process failed with exit code $exitCode: $error");
        }

        $output = trim($output);

        if ($output === '') {
            return [];
        }

        $result = json_decode($output, true);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse kagome output: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Tokenize Japanese text into a simplified format
     *
     * This is a convenience method that returns a simplified version of the analyze() output.
     *
     * @param string $text The text to tokenize
     * @param array<string, mixed> $options Same options as analyze()
     * @return array<int, array{surface: string, features: array<int, string>, pos: string}>
     *   Array of tokens with surface form, features, and part of speech
     */
    public function tokenize(string $text, array $options = []): array
    {
        $result = $this->analyze($text, $options);

        return array_map(function ($token) {
            return [
                'surface' => $token['surface'] ?? '',
                'features' => $token['features'] ?? [],
                'pos' => $token['features'][0] ?? '',
            ];
        }, $result);
    }
}
