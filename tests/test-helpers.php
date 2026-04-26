<?php
/**
 * Pure-function helper coverage. None of these touch the database or plugin
 * options — they are quick to run and pin the behaviour of small utilities
 * that several other tests / specs rely on.
 */

declare(strict_types=1);

class HelpersTest extends PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideUnwrapCases
     */
    public function test_unwrap_action_arg(mixed $input, string $expected): void
    {
        $this->assertSame($expected, lfp_unwrap_action_arg($input));
    }

    public static function provideUnwrapCases(): array
    {
        return [
            'plain string'       => ['site', 'site'],
            'integer cast'       => [42, '42'],
            'array (YOURLS hook)'=> [['site'], 'site'],
            'empty array'        => [[], ''],
            'object (rejected)'  => [new \stdClass(), ''],
            'null'               => [null, ''],
        ];
    }

    /**
     * @dataProvider provideRootRequestCases
     */
    public function test_is_root_request(string $request, bool $expected): void
    {
        $this->assertSame($expected, lfp_is_root_request($request));
    }

    public static function provideRootRequestCases(): array
    {
        return [
            'empty'         => ['', true],
            'index.php'     => ['index.php', true],
            'shortlink'     => ['abc123', false],
            'admin path'    => ['admin/index.php', false],
        ];
    }

    /**
     * @dataProvider provideColorCases
     */
    public function test_sanitize_color(string $input, string $expected): void
    {
        $this->assertSame($expected, lfp_sanitize_color($input));
    }

    public static function provideColorCases(): array
    {
        return [
            'hex 6 digits'         => ['#1a2b3c', '#1a2b3c'],
            'hex 3 digits'         => ['#abc', '#abc'],
            'hex 8 digits (alpha)' => ['#1a2b3c80', '#1a2b3c80'],
            'rgb()'                => ['rgb(10, 20, 30)', 'rgb(10, 20, 30)'],
            'rgba()'               => ['rgba(10,20,30,0.5)', 'rgba(10,20,30,0.5)'],
            'hsl()'                => ['hsl(180, 50%, 50%)', 'hsl(180, 50%, 50%)'],
            'named'                => ['rebeccapurple', 'rebeccapurple'],
            'whitespace trimmed'   => ['  #fff  ', '#fff'],
            'rejects script'       => ['<script>', ''],
            'rejects bare text'    => ['foo bar', ''],
            'rejects empty'        => ['', ''],
        ];
    }

    /**
     * @dataProvider provideSizeCases
     */
    public function test_sanitize_size(string $input, string $default, string $expected): void
    {
        $this->assertSame($expected, lfp_sanitize_size($input, $default));
    }

    public static function provideSizeCases(): array
    {
        return [
            'bare integer becomes px' => ['12',     '0px',  '12px'],
            'bare decimal becomes px' => ['1.5',    '0px',  '1.5px'],
            'px passes through'       => ['16px',   '0px',  '16px'],
            'percent passes through'  => ['50%',    '0px',  '50%'],
            'em passes through'       => ['1.25em', '0px',  '1.25em'],
            'rem passes through'      => ['1rem',   '0px',  '1rem'],
            'vh passes through'       => ['100vh',  '0px',  '100vh'],
            'clamp() passes through'  => ['clamp(1rem, 2vw, 3rem)', '0px', 'clamp(1rem, 2vw, 3rem)'],
            'calc() passes through'   => ['calc(100% - 16px)',      '0px', 'calc(100% - 16px)'],
            'rejects gibberish'       => ['banana', '0px', '0px'],
            'rejects script'          => ['10px;</style><script>', '0px', '0px'],
            'empty returns default'   => ['',       '0px', '0px'],
        ];
    }

    public function test_favicon_for_url_extracts_hostname(): void
    {
        $url = lfp_favicon_for_url('https://github.com/toineenzo');
        $this->assertStringContainsString('s2/favicons',         $url);
        $this->assertStringContainsString('domain=github.com',   $url);
    }

    public function test_favicon_for_url_handles_schemeless_input(): void
    {
        $url = lfp_favicon_for_url('example.com/path');
        $this->assertStringContainsString('domain=example.com', $url);
    }

    public function test_favicon_for_url_returns_empty_for_garbage(): void
    {
        $this->assertSame('', lfp_favicon_for_url(''));
    }

    public function test_quickadd_sign_is_deterministic_and_keyword_specific(): void
    {
        // YOURLS_COOKIEKEY is set by the test bootstrap.
        $a1 = lfp_quickadd_sign('hello');
        $a2 = lfp_quickadd_sign('hello');
        $b  = lfp_quickadd_sign('world');

        $this->assertSame($a1, $a2, 'Same keyword must produce the same signature.');
        $this->assertNotSame($a1, $b, 'Different keywords must produce different signatures.');
        $this->assertSame(16, strlen($a1), 'Signature is the truncated 16-char form.');
    }

    public function test_sanitize_id_strips_unsafe_chars(): void
    {
        $this->assertSame('safe_id-123', lfp_sanitize_id('safe_id-123'));
        $this->assertSame('clean',       lfp_sanitize_id('cl<script>e</script>an'));
        $this->assertSame('',            lfp_sanitize_id('!!!'));
    }
}
