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
        $replacements = [];

        foreach (self::flatten($data) as $key => $value) {
            if ($key === 'content') {
                $replacements['{{' . $key . '}}'] = (string) $value;
                continue;
            }

            $replacements['{{' . $key . '}}'] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        $rendered = strtr($template, $replacements);

        return preg_replace('/\{\{[^}]+\}\}/', '', $rendered) ?? $rendered;
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