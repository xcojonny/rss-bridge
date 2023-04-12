<?php

class TagesschauBridge extends FeedExpander
{
    const MAINTAINER = 'Jonas M';
    const NAME = 'Tagesschau Bridge';
    const URI = 'https://www.tagesschau.de/';
    const CACHE_TIMEOUT = 1800; // 30min
    const DESCRIPTION = 'Returns the full articles instead of only the intro';
    const PARAMETERS = [[
        'category' => [
            'name' => 'Category',
            'type' => 'list',
            'values' => [
                'Alle News'
                => 'https://www.tagesschau.de/xml/rss2/',
                'Alle GolemNews'
                => 'https://rss.golem.de/rss.php?feed=ATOM1.0',
            ]
        ],
        'limit' => [
            'name' => 'Limit',
            'type' => 'number',
            'required' => false,
            'title' => 'Specify number of full articles to return',
            'defaultValue' => 5
        ]
    ]];
    const LIMIT = 5;
    // const HEADERS = ['Cookie: golem_consent20=simple|220101;'];

    public function collectData()
    {
        $this->collectExpandableDatas(
            $this->getInput('category'),
            $this->getInput('limit') ?: static::LIMIT
        );
    }

    protected function parseItem($item)
    {
        $item = parent::parseItem($item);
        $item['content'] ??= '';
        $uri = $item['uri'];

        // $urls = [];

        // while ($uri) {
        //     if (isset($urls[$uri])) {
        //         // Prevent forever a loop
        //         break;
        //     // }
        // $urls[$uri] = true;

        // $articlePage = getSimpleHTMLDOMCached($uri, static::CACHE_TIMEOUT, static::HEADERS);
        $articlePage = getSimpleHTMLDOMCached($uri, static::CACHE_TIMEOUT);

        // URI without RSS feed reference
        // $item['uri'] = $articlePage->find('head meta[name="twitter:url"]', 0)->content;

        $author = $articlePage->find('article .authorline__author', 0);
        if ($author) {
            $item['author'] = $author->plaintext;
        }

        $item['content'] .= $this->extractContent($articlePage);

            // next page
            // $nextUri = $articlePage->find('link[rel="next"]', 0);
            // $uri = $nextUri ? static::URI . $nextUri->href : null;
        // }

        return $item;
    }

    private function extractContent($page)
    {
        $item = '';

        $article = $page->find('article', 0);
        // $item = $page;
        // delete known bad elements
        foreach (
            $article->find('div[id*="adtile"], .teaser-absatz, .copytext-element-wrapper, #seminars,
            div.gbox_affiliate, div.toc, .embedcontent') as $bad
        ) {
            $bad->remove();
        }
        // reload html, as remove() is buggy
        $article = str_get_html($article->outertext);

        if ($pageHeader = $article->find('.seitenkopf__headline h1', 0)) {
            $item .= $pageHeader;
        }

        $header = $article->find('header', 0);
        foreach ($header->find('p, figure') as $element) {
            $item .= $element;
        }

        $content = $article->find('div.formatted', 0);
        foreach ($article->find('p, h1, h3, img[src*="."]') as $element) {
            $content .= $element;
        }

        // full image quality
        foreach ($content->find('img[data-src-full][src*="."]') as $img) {
            $img->src = $img->getAttribute('data-src-full');
        }

        foreach ($content->find('p, h1, h3, img[src*="."]') as $element) {
            $item .= $element;
        }

        return $item;
    }
}
