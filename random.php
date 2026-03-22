<?php

# TODO
# - Move nr_tags_found to tag list

$time_start = microtime(true);
$nr_notes_scanned = 0;
$nr_tags_found = NULL;


function getRandomNoteFromEnex($filePath, $tag_scope = NULL) {

    global $nr_notes_scanned;
    global $nr_tags_found;
    
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
            $nr_notes_scanned++;
            $noteBuffer = $line;
            $tag_found = ($tag_scope == NULL) ? TRUE : FALSE;
        } elseif ($inNote) {

            if (preg_match('/<tag[^>]*>(.*?)<\/tag>/is', $line, $matches)) {
               if ($matches[1] == "$tag_scope") {
                    $tag_found = TRUE;
                    $nr_tags_found++;
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
    $file_filter = (isset($_GET['f'])) ? $_GET['f'] : "enex";
    $files = scandir(__DIR__);
    $enex_files = array();
    foreach ($files as $file) {
        if (str_contains($file, $file_filter)) {
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
   $scope_link = "?t=" . urlencode($_GET['t']);
} elseif (isset($_GET['q'])) {
   $notes = array();
    foreach ($enex_files as $enex_file) {
        $note = getRandomNoteFromEnex(__DIR__ . "/" . $enex_file, $_GET['t']);
        if ($note !== NULL) { array_push($notes, $note); }
   }

} else {
    $enex_file = isset($_GET['f']) ? $_GET['f'] : $enex_files[array_rand($enex_files)];
    $note = getRandomNoteFromEnex(__DIR__ . "/" . $enex_file);
    $scope = "File: " . $enex_file;
    $scope_link = "?f=" . urlencode($enex_file);
}

$time_elapsed = microtime(true) - $time_start;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Evernote Note</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <div class="container">
    <h1><a href="?">Random Evernote Note</a> - <?php
        printf("<a href='%s'>%s</a>", $scope_link, $scope);
        if ($nr_tags_found !== NULL) { echo " (1 / $nr_tags_found)"; }
    ?></h1>
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
        <li><?php printf("<a href='?t=%s'>%s</a>", urlencode($tag), $tag); ?></li>
    <?php }; ?>
    </ul>


    <hr/>

    <div style="text-align: center">            
        <form action="random.php" method="GET">
            <input type="text" id="search" name="q" required>
            <button type="submit">Search</button>
        </form>
        <br/>
        <button onclick="window.location.href = 'add.php';">Add a note.</button>

    </div>
    
        <hr/>

    <div style="text-align: center; font-size: 0.7em;">
        <?php
            echo date(DATE_RFC822);
            echo " Notes scanned: " . $notes_scanned;
            echo " Execution time: " . round($time_elapsed, 4) . " seconds";
        ?>
    </div>

    <div style="text-align: center; font-size: 0.7em;">
        <a href="https://github.com/spmkde/evernote-random/">Source code and bug tracker @ github.com</a>
    </div>

    </div>
</body>
</html>
