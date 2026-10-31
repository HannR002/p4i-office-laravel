<?php

namespace App\Services;

class TemplateEngineService
{
    /**
     * Render template string secara aman dengan interpolasi variabel.
     * Menggunakan htmlspecialchars untuk mencegah Cross-Site Scripting (XSS) dan
     * Server-Side Template Injection (SSTI). Dilarang mengeksekusi PHP/Blade mentah.
     *
     * @param  string  $template  Konten template dengan placeholder {{variabel}}
     * @param  array<string, mixed>  $variables  Data isian formulir
     * @return string Konten HTML yang telah dirender secara aman
     */
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];
            if (! array_key_exists($key, $variables)) {
                return '';
            }

            $value = $variables[$key];
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }, $template);
    }

    /**
     * Ekstrak variabel yang dibutuhkan oleh sebuah template.
     *
     * @return array<int, string>
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $template, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
