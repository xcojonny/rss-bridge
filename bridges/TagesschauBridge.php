<?php

class TagesschauBridge extends FeedExpander
{
    const MAINTAINER = 'Jonas M';
    const NAME = 'Tagesschau Bridge';
    const URI = 'https://www.tagesschau.de/';
    // const CACHE_TIMEOUT = 1800; // 30min
    const CACHE_TIMEOUT = 10; // 30min
    const DESCRIPTION = 'Returns the full articles';
    const PARAMETERS = [[
        'category' => [
            'name' => 'Category',
            'type' => 'list',
            'values' => [
                'Alle News'
                => 'https://www.tagesschau.de/xml/rss2/'
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
        $uri = $item['uri'];
        $rootURL = parse_url($uri);
        $rootURL = $rootURL['scheme'] . '://' . $rootURL['host'];
        if (strpos($rootURL, 'www.tagesschau.de') !== false) {
            $item['content'] ??= '';
            // $articlePage = getSimpleHTMLDOMCached($item['uri']);
            $articlePage = getSimpleHTMLDOMCached($uri, static::CACHE_TIMEOUT);

            $author = $articlePage->find('article .authorline__author', 0);
            if ($author) {
                $item['author'] = $author->plaintext;
            }
            // Find article body
            $article = null;
            switch (true) {
                case !is_null($articlePage->find('article', 0)):
                    // most common content div
                    $article = $articlePage->find('article', 0);
                    break;
            }
            // ts-image

            // Find article main image
            $article = convertLazyLoading($article);
            $article_image = $articlePage->find('img.ts-image', 0);
            // get figure with picture
            $article_image = $articlePage->find('source[media="(max-width: 767px)"]', 0);
            // $article_image = $articlePage->find('source[media="(max-width: 1023px)"]', 0);


            if (is_object($article_image) && !empty($article_image->src)) {
                $article_image = $rootURL . $article_image->src;
                // $mime_type = parse_mime_type($article_image);
                // if (strpos($mime_type, 'image') === false) {
                //     $article_image .= '#.image'; // force image
                // }
                // if (empty($item['enclosures'])) {
                //     $item['enclosures'] = [$article_image];
                // } else {
                //     $item['enclosures'] = array_merge($item['enclosures'], (array) $article_image);
                // }
            }
            if (!is_null($article)) {
                $item['content'] = $this->cleanContent($article, $article_image);
                $item['content'] = defaultLinkTo($item['content'], $item['uri']);
            }
        }

        return $item;
    }

    private function cleanContent($page, $image)
    {
        $item = '';
        $article = $page;
        // $item = $page;
        // delete known bad elements
        // foreach (
        //     $article->find('div[id*="adtile"], .teaser-absatz, .copytext-element-wrapper, #seminars,
        //     div.gbox_affiliate, div.toc, .embedcontent') as $bad
        // ) {
        //     $bad->remove();
        // }
        // // reload html, as remove() is buggy
        // $article = str_get_html($article->outertext);

        // add main image to page
        $image = "<img src='" . $image . "' alt='Image Description'>";
        $item .= $image;
        // $content = $article;
        foreach ($article->find('p.textabsatz, h2, h3') as $element) {
            $item .= $element;
        }
        return $item;
    }
}
