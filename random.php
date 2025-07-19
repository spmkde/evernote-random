<?php

function getRandomNoteFromEnex($filePath) {
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
        } elseif ($inNote) {
            $noteBuffer .= $line;
            if (strpos($line, '</note>') !== false) {
                $inNote = false;
                $noteCount++;

                // Reservoir Sampling: replace with probability 1/n
                if (mt_rand(1, $noteCount) === 1) {
                    $selectedNote = $noteBuffer;
                }
            }
        }
    }

    fclose($handle);

    if (!$selectedNote) {
        return "No notes found.";
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

    // Extract only content inside <en-note>
    if (preg_match('/<en-note[^>]*>(.*?)<\/en-note>/is', $content, $matches)) {
        $cleanContent = $matches[1];
    } else {
        $cleanContent = htmlspecialchars($content);
    }

    return [
        'title' => $title,
        'content' => $cleanContent
    ];
}
?>

<?php

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

$files = scandir(__DIR__);
$enex_files = array();
foreach ($files as $file) {
    if (str_contains($file, "enex")) {
        array_push($enex_files, $file);
    }
}
$r_file = $enex_files[array_rand($enex_files)];
$note = getRandomNoteFromEnex(__DIR__ . "/" . $r_file);
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
    <h1>Random Evernote Note - <?php echo $r_file ?></h1>

    <?php if (is_array($note)): ?>
        <h2><?php echo htmlspecialchars($note['title']); ?></h2>
        <div class="note-content">
            <?php echo $note['content']; ?>
        </div>
    <?php else: ?>
        <p style="color:red;"><?php echo htmlspecialchars($note); ?></p>
    <?php endif; ?>

    <a href="?">Show another note</a>
    </div>
</body>
</html>
