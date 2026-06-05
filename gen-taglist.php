<?php

$tags = [];

foreach (glob('*.enex') as $enexFile) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->load($enexFile)) {
        echo "Error loading XML file $enexFile";
        foreach(libxml_get_errors() as $error) {
           echo "\t", $error->message;
        }
        libxml_clear_errors();
        continue;
    }
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('tag') as $tagNode) {
        $tagText = trim($tagNode->textContent);
        if ($tagText !== '') {
            $tags[$tagText] = true;
        }
    }
}

$uniqueTags = array_keys($tags);
sort($uniqueTags, SORT_NATURAL | SORT_FLAG_CASE);

file_put_contents('taglist.txt', implode("\n", $uniqueTags) . "\n");

echo "Saved " . count($uniqueTags) . " unique tags to taglist.txt\n";
