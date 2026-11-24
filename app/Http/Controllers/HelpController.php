<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

/**
 * The user manual, in the user's language. The text lives in docs/manual/<locale>.md so it
 * reads on GitHub too; screenshots are in public/manual/<locale>/ and are retaken with
 * `npm run manual:screenshots`.
 */
class HelpController extends Controller
{
    public function __invoke()
    {
        $locale = app()->getLocale();
        $file = base_path("docs/manual/{$locale}.md");
        if (! is_file($file)) {
            $file = base_path('docs/manual/en.md');
        }

        $html = Str::markdown(file_get_contents($file), ['html_input' => 'allow', 'allow_unsafe_links' => false]);

        // Image paths are written relative to the Markdown file (for GitHub); serve them from public/.
        $html = str_replace('src="../../public/', 'src="'.rtrim(asset('/'), '/').'/', $html);
        $html = str_replace('<img ', '<img loading="lazy" ', $html);

        // Give each section heading an anchor for the contents list.
        $toc = [];
        $html = preg_replace_callback('#<h2>(.*?)</h2>#', function ($m) use (&$toc) {
            $id = Str::slug(strip_tags(html_entity_decode($m[1]))) ?: 'section-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'title' => strip_tags(html_entity_decode($m[1]))];

            return "<h2 id=\"{$id}\">{$m[1]}</h2>";
        }, $html);

        // The first line of the file is the page title.
        $title = preg_match('#<h1>(.*?)</h1>#', $html, $m) ? strip_tags(html_entity_decode($m[1])) : __('User manual');
        $html = preg_replace('#<h1>.*?</h1>#', '', $html, 1);

        return view('help.index', compact('html', 'toc', 'title'));
    }
}
