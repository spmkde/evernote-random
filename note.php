<?php

$time_start = microtime(true);
$nr_notes_scanned = 0;
$nr_tags_found = NULL;

function getNoteById($noteId) {
    global $enex_files;
    foreach ($enex_files as $enex_file) {
        $note = getNoteFromEnexById(__DIR__ . "/" . $enex_file, $noteId);
        if ($note !== NULL) return $note;
    }
    return "Note not found.";
}

function getNoteFromEnexById($filePath, $noteId) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return "Could not open file.";
    }

    $inNote = false;
    $noteBuffer = '';
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, '<note') !== false) {
            if (preg_match('/<note id="([^"]+)"/', $line, $matches)) {
                $currentId = $matches[1];
                if ($currentId == $noteId) {
                    $inNote = true;
                    $noteBuffer = $line;
                }
            }
        } elseif ($inNote) {
            $noteBuffer .= $line;
            if (strpos($line, '</note>') !== false) {
                $inNote = false;
                // parse it using regex
                if (!preg_match('/<note id="([^"]+)"/', $noteBuffer, $idMatch)) {
                    return "Failed to parse selected note.";
                }
                $id = $idMatch[1];

                if (!preg_match('/<title>(.*?)<\/title>/s', $noteBuffer, $titleMatch)) {
                    $title = '';
                } else {
                    $title = trim($titleMatch[1]);
                }

                if (!preg_match('/<content>(.*?)<\/content>/s', $noteBuffer, $contentMatch)) {
                    $content = '';
                } else {
                    $content = $contentMatch[1];
                }

                $tags = [];
                preg_match_all('/<tag>(.*?)<\/tag>/s', $noteBuffer, $tagMatches);
                if (isset($tagMatches[1])) {
                    $tags = array_map('trim', $tagMatches[1]);
                }

                $author = null;
                $book = null;

                foreach ($tags as $tag) {
                    if (strpos($tag, 'Autor:') === 0) {
                        $author = trim(substr($tag, strlen('Autor:')));
                        if ($author === '') {
                            $author = null;
                        }
                    }
                    if (strpos($tag, 'Buch:') === 0) {
                        $book = trim(substr($tag, strlen('Buch:')));
                        if ($book === '') {
                            $book = null;
                        }
                    }
                }

                if (preg_match('/<en-note[^>]*>(.*?)<\/en-note>/is', $content, $matches)) {
                    $cleanContent = $matches[1];
                } else {
                    $cleanContent = htmlspecialchars($content);
                }
                fclose($handle);
                return [
                    'id' => $id,
                    'title' => $title,
                    'content' => $cleanContent,
                    'tags' => $tags,
                    'author' => $author,
                    'book' => $book
                ];
            }
        }
    }
    fclose($handle);
    return NULL;
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
        if ($file == "sandbox.enex") { continue; }
        if (str_contains($file, $file_filter)) {
            array_push($enex_files, $file);
        }
    }
    return $enex_files;
}

$enex_files = get_enex_files();

if (!isset($_GET['id'])) {
    $note = "Please provide a note ID.";
}

foreach ($enex_files as $enex_file) {
    $note = getNoteFromEnexById(__DIR__ . "/" . $enex_file, $_GET['id']);
    if (is_array($note)) {
        break;
    }
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
    <h1><a href="random.php?">Random Evernote Note</a></h1>
    <hr/>
    <?php if (is_array($note)): ?>
        <div class="note-content">
            <?php echo $note['content']; ?>
        </div>
        <?php if (!empty($note['author'])): ?>
            <div class="note-author" style="text-align:right; margin-top: 1em; margin-right: 1em;">
                <?php echo htmlspecialchars($note['author']); ?>
        <?php endif; ?>

        <?php if (!empty($note['book'])): ?>
                <?php echo "- " . htmlspecialchars($note['book']); ?>

            </div>
        <?php endif; ?>


    <?php else: ?>
        <p style="color:red;"><?php echo htmlspecialchars($note); ?></p>
    <?php endif; ?>

    <hr/>
    </div>
</body>
</html>
