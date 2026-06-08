<?php

declare(strict_types=1);

namespace FlashMind\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout/app'): void
    {
        $rootPath = dirname(__DIR__, 2);
        $templateFile = $rootPath . '/templates/' . $template . '.html';
        $layoutFile = $rootPath . '/templates/' . $layout . '.html';

        if (!is_file($templateFile)) {
            http_response_code(500);
            echo 'Template not found.';
            return;
        }

        $content = self::interpolate((string) file_get_contents($templateFile), $data);
        $output = $content;

        if (is_file($layoutFile)) {
            $layoutData = $data;
            $layoutData['content'] = $content;
            $output = self::interpolate((string) file_get_contents($layoutFile), $layoutData);
        }

        echo $output;
    }

    private static function interpolate(string $template, array $data): string
    {
        $template = self::renderSections($template, $data);
        $replacements = [];

        foreach (self::flatten($data) as $key => $value) {
            if ($key === 'content' || str_starts_with($key, 'raw.') || str_contains($key, '.raw.')) {
                $replacements['{{' . $key . '}}'] = (string) $value;
                continue;
            }

            $replacements['{{' . $key . '}}'] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        $rendered = strtr($template, $replacements);

        return preg_replace('/\{\{[^}]+\}\}/', '', $rendered) ?? $rendered;
    }

    private static function renderSections(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{#([a-zA-Z0-9_.]+)\}\}(.*?)\{\{\/\1\}\}/s', static function (array $matches) use ($data): string {
            $items = self::valueForPath($data, $matches[1]);
            if (!is_array($items)) {
                return '';
            }

            $output = '';
            foreach ($items as $item) {
                $context = is_array($item) ? $item : ['value' => $item];
                $output .= self::interpolate($matches[2], array_replace_recursive($data, $context));
            }

            return $output;
        }, $template) ?? $template;
    }

    private static function valueForPath(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    private static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $compoundKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += self::flatten($value, $compoundKey);
                continue;
            }

            if (is_object($value)) {
                $flat += self::flatten(get_object_vars($value), $compoundKey);
                continue;
            }

            $flat[$compoundKey] = $value;
        }

        return $flat;
    }
}