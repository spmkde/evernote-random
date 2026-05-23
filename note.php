<?php

$time_start = microtime(true);
$nr_notes_scanned = 0;
$nr_tags_found = NULL;

$ENABLE_QC_LINK = TRUE;

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
        <?php
        $content = $note['content'];
        $extracted = '';

        # Extract Bible verses or similar notes that are formatted as "-- Verse" within a div
        if (preg_match('/(<div[^>]*>)\s*--\s*(.*?)<\/div>/is', $content, $matches)) {
            $extracted = $matches[0];
            $content = str_replace($extracted, '', $content);
            $extracted = str_replace("-- ", "", $extracted);
        }
        ?>
        <div class="note-content">
            <?php echo $content; ?>
        </div>
        <div class="note-author" style="text-align:right; margin-top: 1em; margin-right: 1em;">
            <?php if (!empty($note['author'])): ?>
                    <?php echo "<a href='random.php?t=Autor:+" . urlencode($note['author']) . "'>" . htmlspecialchars($note['author']) . "</a>"; ?>
            <?php endif; ?>

            <?php if (!empty($extracted)): ?>
                    <?php echo $extracted; ?>
            <?php elseif (!empty($note['book'])): ?>
                    <?php echo "- " . "<a href='random.php?t=Buch:+" . urlencode($note['book']) . "'>" . htmlspecialchars($note['book']) . "</a>"; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <p style="color:red;"><?php echo htmlspecialchars($note); ?></p>
    <?php endif; ?>

    <hr/>

    <div style="text-align: center; margin: 1rem 0;">
        <button id="copyUrlButton" style="margin-bottom: 10px">Copy current URL</button>

    <?php if ($ENABLE_QC_LINK && is_array($note)): ?>

        <button id="externalLinkButton" style="margin-left: 0.75rem;">Send note to QuotesCover</button>
    <?php endif; ?>
    </div>

    </div>
    <script>
        const copyUrlButton = document.getElementById('copyUrlButton');
        const externalLinkButton = document.getElementById('externalLinkButton');
        const noteContent = <?php echo json_encode(is_array($note) ? $content : ''); ?>;
        const noteAuthor = <?php echo json_encode(is_array($note) ? $note['author'] : ''); ?>;
        const externalBaseUrl = 'https://quotescover.com/pro-version/api/';

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.setAttribute('readonly', '');
            textArea.style.position = 'fixed';
            textArea.style.top = 0;
            textArea.style.left = 0;
            textArea.style.opacity = 0;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            let successful = false;
            try {
                successful = document.execCommand('copy');
            } catch (err) {
                successful = false;
            }
            document.body.removeChild(textArea);
            return successful;
        }

        if (copyUrlButton) {
            copyUrlButton.addEventListener('click', async () => {
                const text = window.location.href;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    try {
                        await navigator.clipboard.writeText(text);
                        alert('URL copied to clipboard');
                        return;
                    } catch (err) {
                        console.warn('navigator.clipboard.writeText failed', err);
                    }
                }

                if (fallbackCopyTextToClipboard(text)) {
                    alert('URL copied to clipboard');
                } else {
                    alert('Clipboard access is not available in this browser. Please copy the URL manually.');
                }
            });
        }

        function stripHtmlTags(html) {
            return html.replace(/<[^>]*>/g, '');
        }

        if (externalLinkButton) {
            externalLinkButton.addEventListener('click', () => {
                const plainContent = stripHtmlTags(noteContent);
                const url = externalBaseUrl + '?theq=' + encodeURIComponent(plainContent) + '&thea=' + encodeURIComponent(noteAuthor);
                window.open(url, '_blank');
            });
        }
    </script>
</body>
</html>
