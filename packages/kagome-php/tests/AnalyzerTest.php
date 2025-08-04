<?php

namespace Kagome\Tests;

use Kagome\Analyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test suite for the Kagome Analyzer class
 *
 * @package Kagome\Tests
 * @covers \Kagome\Analyzer
 */
class AnalyzerTest extends TestCase
{
    /**
     * @var Analyzer The analyzer instance for testing
     */
    private Analyzer $analyzer;

    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    /**
     * Test basic text analysis functionality
     *
     * @return void
     */
    public function testAnalyzeBasicText(): void
    {
        $result = $this->analyzer->analyze('すもももももももものうち');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check first token
        $firstToken = $result[0];
        $this->assertArrayHasKey('surface', $firstToken);
        $this->assertArrayHasKey('features', $firstToken);
        $this->assertEquals('すもも', $firstToken['surface']);
        $this->assertEquals('名詞', $firstToken['features'][0]);
    }

    /**
     * Test the tokenize method returns simplified format
     *
     * @return void
     */
    public function testTokenizeMethod(): void
    {
        $tokens = $this->analyzer->tokenize('東京タワーに行きました');

        $this->assertIsArray($tokens);
        $this->assertNotEmpty($tokens);

        foreach ($tokens as $token) {
            $this->assertArrayHasKey('surface', $token);
            $this->assertArrayHasKey('features', $token);
            $this->assertArrayHasKey('pos', $token);
        }
    }

    /**
     * Test that empty text returns empty array
     *
     * @return void
     */
    public function testEmptyText(): void
    {
        $result = $this->analyzer->analyze('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that invalid binary path throws exception
     *
     * @return void
     */
    public function testInvalidBinaryPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kagome binary not found at');

        new Analyzer('/path/to/nonexistent/binary');
    }

    /**
     * Test analysis with different modes (normal vs search)
     *
     * @return void
     */
    public function testAnalyzeWithDifferentModes(): void
    {
        $text = '関西国際空港';

        $normalResult = $this->analyzer->analyze($text, ['mode' => 'normal']);
        $searchResult = $this->analyzer->analyze($text, ['mode' => 'search']);

        $this->assertIsArray($normalResult);
        $this->assertIsArray($searchResult);

        // Search mode should produce more tokens
        $this->assertGreaterThanOrEqual(count($normalResult), count($searchResult));
    }

    /**
     * Test analysis with user dictionary
     *
     * @return void
     */
    public function testAnalyzeWithUserDictionary(): void
    {
        $userDictPath = __DIR__ . '/fixtures/sample_user_dict.txt';

        // Test with user dictionary
        $text = 'テスト単語を使用します';
        $result = $this->analyzer->analyze($text, ['udict' => $userDictPath]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check if custom word is recognized
        $surfaces = array_column($result, 'surface');
        $this->assertContains('テスト単語', $surfaces);

        // Check if the word is recognized as USER class
        $userToken = null;
        foreach ($result as $token) {
            if ($token['surface'] === 'テスト単語') {
                $userToken = $token;
                break;
            }
        }

        $this->assertNotNull($userToken);
        $this->assertEquals('USER', $userToken['class']);
        $this->assertEquals(['名詞'], $userToken['pos']);
    }

    /**
     * Test that invalid user dictionary path throws exception
     *
     * @return void
     */
    public function testInvalidUserDictionaryPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User dictionary file not found');

        $this->analyzer->analyze('テスト', ['udict' => '/path/to/nonexistent/dict.txt']);
    }

    /**
     * Test that null udict option is handled correctly
     *
     * @return void
     */
    public function testNullUserDictionary(): void
    {
        $result = $this->analyzer->analyze('テスト', ['udict' => null]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
