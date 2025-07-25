<?php

function getRandomNoteFromEnex($filePath, $tag_scope = NULL) {
    
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return "Could not open file.";
    }

    $inNote = false;
    $noteBuffer = '';
    $noteCount = 0;
    $selectedNote = '';

    while (($line = fgets($handle)) !== false) {
        if (strpos($line, '<note>') !== false) {
            $inNote = true;
            $noteBuffer = $line;
            $tag_found = ($tag_scope == NULL) ? TRUE : FALSE;
        } elseif ($inNote) {

            if (preg_match('/<tag[^>]*>(.*?)<\/tag>/is', $line, $matches)) {
               if ($matches[1] == "$tag_scope") {
                    $tag_found = TRUE;
               }
            }

            $noteBuffer .= $line;
            if (strpos($line, '</note>') !== false) {
                $inNote = false;
                if ($tag_found) { 
                    $noteCount++;
                
                    // Reservoir Sampling: replace with probability 1/n
                    if (mt_rand(1, $noteCount) === 1) {
                        $selectedNote = $noteBuffer;
                    }
                }
            }
        }
    }

    fclose($handle);

    if (!$selectedNote) {
        return NULL;
    }

    // Wrap the selected note in a root element to parse
    $wrappedXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><root>$selectedNote</root>";
    libxml_use_internal_errors(true);
    $parsed = simplexml_load_string($wrappedXml);

    if (!$parsed || !isset($parsed->note)) {
        return "Failed to parse selected note.";
    }

    $note = $parsed->note;
    $title = (string)$note->title;
    $content = (string)$note->content;
    $tags = $note->tag;

    // Extract only content inside <en-note>
    if (preg_match('/<en-note[^>]*>(.*?)<\/en-note>/is', $content, $matches)) {
        $cleanContent = $matches[1];
    } else {
        $cleanContent = htmlspecialchars($content);
    }

    return [
        'title' => $title,
        'content' => $cleanContent,
        'tags' => $tags
    ];
}
?>

<?php

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

function get_enex_files() {
    $files = scandir(__DIR__);
    $enex_files = array();
    foreach ($files as $file) {
        if (str_contains($file, "enex")) {
            array_push($enex_files, $file);
        }
    }
    return $enex_files;
}

$enex_files = get_enex_files();

if (isset($_GET['t'])) {
    $notes = array();
    foreach ($enex_files as $enex_file) {
        $note = getRandomNoteFromEnex(__DIR__ . "/" . $enex_file, $_GET['t']);
        if ($note !== NULL) { array_push($notes, $note); }
   } 
   $note = $notes[array_rand($notes)];
   $scope = "Tag: " . $_GET['t'];
} else {
    $enex_file = $enex_files[array_rand($enex_files)];
    $note = getRandomNoteFromEnex(__DIR__ . "/" . $enex_file);
    $scope = "File: " . $enex_file;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Evernote Note</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #fafafa;
            margin: 2rem;
            color: #333;
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: #0056b3;
        }
        h2 {
            margin-top: 0;
            color: #222;
        }
        hr {
            color: #222;
            margin-top: 2em;
            margin-bottom: 2em;
            border: 0.5px solid;
        }
        .note-content {
            background: white;
            border: 1px solid #ddd;
            padding: 1rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        a {
            display: inline-block;
            margin-top: 1rem;
            text-decoration: none;
            color: #0056b3;
        }
        a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            body: font-size: 4rem;
        }

        .container { 
            max-width: 700px;
            margin: 0 auto;
            padding: 1rem;
         }
    </style>
</head>
<body>
    <div class="container">
    <h1><a href="?">Random Evernote Note</a> - <?php echo $scope ?></h1>
    <hr/>
    <?php if (is_array($note)): ?>
        <h2><?php echo htmlspecialchars($note['title']); ?></h2>
        <div class="note-content">
            <?php echo $note['content']; ?>
        </div>
    <?php else: ?>
        <p style="color:red;"><?php echo htmlspecialchars($note); ?></p>
    <?php endif; ?>

    <hr/>
    <p>Tags:

    <ul>
    <?php foreach ($note['tags'] as $tag) { ?>
        <div class="note-tags">
        <li><?php printf("<a href='?t=%s'>%s</a>", urlencode($tag), $tag); ?></li>
        </div>
    <?php }; ?>
    </ul>
    </p>

    <a href="?">Show another note</a>

    </div>
</body>
</html>
