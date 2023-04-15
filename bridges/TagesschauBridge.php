<?php

class TagesschauBridge extends FeedExpander
{
    const MAINTAINER = 'xcojonny';
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

            // Find article main image
            // $article = convertLazyLoading($article);
            $article_image = $articlePage->find('img.ts-image', 0);
            // get figure with picture
            // $article_image = $articlePage->find('source[media="(max-width: 767px)"]', 0);

            if (is_object($article_image) && !empty($article_image->src)) {
                $article_image = $rootURL . $article_image->src;
            }
            if (!is_null($article)) {
                $item['content'] = $this->cleanContent($article, $article_image, $rootURL);
                $item['content'] = defaultLinkTo($item['content'], $item['uri']);
            }
        }

        return $item;
    }

    private function cleanContent($page, $image, $rootURL)
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
        foreach ($article->find('p.textabsatz, h2, h3, .absatzbild, div.copytext__embed, div.copytext__video') as $element) {
            $item .= $element;
        }

        //  fix double loaded pictures in Freshrss
        $item = str_get_html($item);
        foreach ($item->find('div.absatzbild__media') as $element) {
            $image = $element->find('img', 0);
            $image = $rootURL . $image->src;
            $element->outertext  = "<img src='" . $image . "' alt='Image Description'>";
        }

        // go through external links and process iframes
        foreach ($item->find('div.copytext__embed, div.copytext__video, div.copytext__audio') as $element) {
            $element ->outertext  = '<h3>----Embeded Content----</h3>';
        }
        return $item;
    }
}
