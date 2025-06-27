<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ExtractLocalizationStrings
{
    public function handle(Request $request, Closure $next)
    {
        if (!App::environment('local')) {
            return $next($request);
        }

        $viewPath = resource_path('views');
        $langPath = lang_path();

        // Extract all __('Text Here') keys from views using regex
        $fileList = collect(File::allFiles($viewPath));
        $pattern = '/__\(\s*[\'"](.+?)[\'"]\s*\)/';
        $translations = [];

        foreach ($fileList as $file) {
            if ($file->getExtension() !== 'php') continue;

            $contents = file_get_contents($file->getRealPath());
            if (preg_match_all($pattern, $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $key = stripslashes($match);
                    $translations[$key] = $translations[$key] ?? $key;
                }
            }
        }

        ksort($translations);

        // Write to en.json and generate hash
        $enPath = $langPath . '/en.json';
        $enJson = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($enPath, $enJson);

        $enHash = md5($enJson);
        $enModified = filemtime($enPath);

        // Translate into supported locales from config (excluding en)
        $locales = config('app.supported_locales', []);
        foreach ($locales as $locale) {
            if ($locale === 'en') continue;

            $localePath = "{$langPath}/{$locale}.json";
            $localeHashPath = "{$langPath}/.{$locale}.hash";

            $previousHash = file_exists($localeHashPath)
                ? trim(file_get_contents($localeHashPath))
                : null;

            $shouldTranslate =
                !file_exists($localePath) ||
                $previousHash !== $enHash;

            if (!$shouldTranslate) {
                continue;
            }

            $translated = $this->translateFullJson($translations, 'en', $locale);

            if (is_array($translated)) {
                file_put_contents($localePath, json_encode(
                    $translated,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));

                file_put_contents($localeHashPath, $enHash);
                touch($localePath, $enModified);
                logger()->info("Translated en.json to {$locale}.json");
            } else {
                logger()->warning("Translation failed for {$locale}.json — skipping write.");
            }
        }

        return $next($request);
    }

    private function translateFullJson(array $enJson, string $from, string $to): ?array
    {
        $apiKey = config('openai.secret_key');

        $jsonText = json_encode($enJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Translate the following JSON object from {$from} to {$to}.  These translations are in the context of a website. Return only the translated JSON — no explanations, no formatting, no code blocks.

$jsonText
PROMPT;

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a translation engine. Only return raw JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $content = $response->json('choices.0.message.content');

        $clean = trim(preg_replace('/^```json|```$/m', '', $content));

        try {
            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            logger()->error("Failed to decode translated JSON for '{$to}': " . $e->getMessage());
            return null;
        }
    }
}
