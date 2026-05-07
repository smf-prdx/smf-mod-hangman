<?php

/**
 * Class-Hangman.php
 *
 * @package prdx:hangman
 * @link https://cientoseis.es
 */

if (!defined('SMF'))
    die('No direct access...');

final class Hangman
{
    private const DEFAULT_MAX_ATTEMPTS = 6;
    private const MAX_CONFIG_ATTEMPTS = 99;
    private const DEFAULT_STYLE = 'simple';
    private const STYLES = ['simple', 'detailed'];

    public static function hooks(): void
    {
        add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes#', false, __FILE__);
        add_integration_function('integrate_general_mod_settings', __CLASS__ . '::modSettings#', false, __FILE__);
        add_integration_function('integrate_pre_css_output', __CLASS__ . '::css#', false, __FILE__);
    }

    private static function localLoadLanguage(string $template = 'Hangman/Hangman'): void
    {
        $is_package_area = isset($_GET['action'], $_GET['area']) &&
            $_GET['action'] === 'admin' &&
            $_GET['area'] === 'packages';

        loadLanguage($template, '', !$is_package_area);
    }

    public static function modSettings(array &$config_vars): void
    {
        global $modSettings, $txt;

        self::localLoadLanguage();

        $modSettings['hangman_max_attempts'] = $modSettings['hangman_max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS;
        $modSettings['hangman_style'] = $modSettings['hangman_style'] ?? self::DEFAULT_STYLE;

        $config_vars[] = ['title', 'hangman_settings'];
        $config_vars[] = ['int', 'hangman_max_attempts'];
        $config_vars[] = ['select', 'hangman_style', [
            'simple' => $txt['hangman_style_simple'],
            'detailed' => $txt['hangman_style_detailed'],
        ]];
    }

    public static function css(): void
    {
        loadCSSFile('Hangman.css', ['minimize' => true, 'default_theme' => true], 'Hangman');
    }

    public static function bbcCodes(array &$codes): void
    {
        global $txt;

        self::localLoadLanguage();

        $codes[] = [
            'tag' => 'hangman',
            'type' => 'unparsed_equals',
            'block_level' => true,
            'before' => '',
            'after' => '',
            'validate' => function (&$tag, &$data) use ($txt) {
                $state = Hangman::parseSpec($data);
                if ($state === null)
                {
                    $tag['before'] = '<div class="errorbox">' . $txt['hangman_invalid'] . '</div>';
                    return;
                }

                $tag['before'] = Hangman::render($state);
            },
        ];
    }

    public static function parseSpec(string $spec): ?array
    {
        $spec = trim(self::decodeEntities($spec));
        $defaults = self::defaults();
        $tries = null;
        $max_attempts = $defaults['max_attempts'];
        $style = $defaults['style'];

        if ($spec !== '')
        {
            foreach (preg_split('~\s+~', $spec) as $index => $token)
            {
                $token = trim($token);
                if ($token === '')
                    continue;

                if ($index === 0 && preg_match('~^-?\d+$~', $token))
                {
                    $tries = (int) $token;
                    continue;
                }

                if (!preg_match('~^([a-z_]+)\s*=\s*([a-z0-9_-]+)$~i', $token, $match))
                {
                    if (self::normalizeStyle($token) !== null)
                        $style = self::normalizeStyle($token);

                    continue;
                }

                $key = strtolower($match[1]);
                $value = $match[2];

                if (in_array($key, ['tries', 'failures', 'misses', 'fallos', 'intentos'], true))
                    $tries = (int) $value;
                elseif (in_array($key, ['max_attempts', 'attempts', 'max', 'max_intentos'], true))
                    $max_attempts = (int) $value;
                elseif ($key === 'style' || $key === 'estilo')
                {
                    $normalized = self::normalizeStyle($value);
                    if ($normalized !== null)
                        $style = $normalized;
                }
            }
        }

        $max_attempts = max(1, min(self::MAX_CONFIG_ATTEMPTS, $max_attempts));
        $tries = max(0, min($max_attempts, (int) ($tries ?? 0)));

        return [
            'tries' => $tries,
            'max_attempts' => $max_attempts,
            'style' => $style,
        ];
    }

    public static function render(array $state): string
    {
        global $txt;

        self::localLoadLanguage();

        $tries = (int) ($state['tries'] ?? 0);
        $max_attempts = max(1, (int) ($state['max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS));
        $style = self::normalizeStyle((string) ($state['style'] ?? self::DEFAULT_STYLE)) ?? self::DEFAULT_STYLE;
        $stage = self::stage($tries, $max_attempts);
        $drawing = self::drawing($style, $stage);
        $status = $tries >= $max_attempts ? $txt['hangman_status_hanged'] : $txt['hangman_status_playing'];
        $class = 'hangman-box hangman-style-' . $style . ($tries >= $max_attempts ? ' hangman-is-hanged' : '');

        return '<div class="' . $class . '">' .
            '<pre class="hangman-art" aria-label="' . self::escape($txt['hangman_bbc']) . '">' . self::formatDrawing($drawing) . '</pre>' .
            '<div class="hangman-meta">' . sprintf($txt['hangman_meta'], $tries, $max_attempts, $status) . '</div>' .
            '</div>';
    }

    private static function defaults(): array
    {
        global $modSettings;

        $max_attempts = (int) ($modSettings['hangman_max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS);
        $style = self::normalizeStyle((string) ($modSettings['hangman_style'] ?? self::DEFAULT_STYLE)) ?? self::DEFAULT_STYLE;

        return [
            'max_attempts' => max(1, min(self::MAX_CONFIG_ATTEMPTS, $max_attempts)),
            'style' => $style,
        ];
    }

    private static function normalizeStyle(string $style): ?string
    {
        $style = strtolower(trim($style));
        $aliases = [
            'simple' => 'simple',
            'sencillo' => 'simple',
            'basic' => 'simple',
            'detailed' => 'detailed',
            'elaborado' => 'detailed',
            'elaborated' => 'detailed',
            'detail' => 'detailed',
            'detallado' => 'detailed',
        ];

        return $aliases[$style] ?? null;
    }

    private static function stage(int $tries, int $max_attempts): int
    {
        if ($tries <= 0)
            return 0;

        if ($tries >= $max_attempts)
            return 6;

        return max(1, min(6, (int) ceil(($tries / $max_attempts) * 6)));
    }

    private static function drawing(string $style, int $stage): array
    {
        $head = $stage >= 1 ? 'O' : ' ';
        $body = $stage >= 2 ? '|' : ' ';
        $left_arm = $stage >= 3 ? '/' : ' ';
        $right_arm = $stage >= 4 ? '\\' : ' ';
        $left_leg = $stage >= 5 ? '/' : ' ';
        $right_leg = $stage >= 6 ? '\\' : ' ';

        if ($style === 'detailed')
        {
            return [
                '  _________',
                '  |/      |',
                '  |      ' . ($stage >= 1 ? '(_)' : '   '),
                '  |      ' . $left_arm . $body . $right_arm,
                '  |       ' . ($stage >= 2 ? $body : ' '),
                '  |      ' . $left_leg . ' ' . $right_leg,
                '  |',
                '__|__',
            ];
        }

        return [
            ' +---+',
            ' |   |',
            ' ' . $head . '   |',
            $left_arm . $body . $right_arm . '  |',
            $left_leg . ' ' . $right_leg . '  |',
            '     |',
            '=======',
        ];
    }

    private static function formatDrawing(array $lines): string
    {
        return implode('<br>', array_map([self::class, 'escape'], $lines));
    }

    private static function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
