<?php

$time_start = microtime(true);
$nr_notes_scanned = 0;
$nr_tags_found = NULL;

$DAILY_QUOTA = FALSE; // Set to a positive integer to enable daily request quota, or FALSE to disable.
$DISPLAY_TITLE = FALSE; // Display note header

if ($DAILY_QUOTA) {
    $quota_cookie_name = 'daily_request_quota';
    $today = gmdate('Y-m-d');
    $quota_data = ['date' => $today, 'count' => 0];

    if (isset($_COOKIE[$quota_cookie_name])) {
        $cookie_value = $_COOKIE[$quota_cookie_name];
        $decoded = json_decode($cookie_value, true);
        if (is_array($decoded) && isset($decoded['date'], $decoded['count'])) {
            $quota_data = [
                'date' => is_string($decoded['date']) ? $decoded['date'] : $today,
                'count' => is_int($decoded['count']) ? $decoded['count'] : intval($decoded['count'])
            ];
        }
    }


    if ($quota_data['date'] !== $today) {
        $quota_data['date'] = $today;
        $quota_data['count'] = 0;
    }

    $isQuotaExceeded = ($quota_data['count'] >= $DAILY_QUOTA);
    if (!$isQuotaExceeded) {
        $quota_data['count']++;
    }

    $cookie_expires = strtotime('tomorrow 00:00');
    if ($cookie_expires === false) {
        $cookie_expires = time() + 86400;
    }
    setcookie($quota_cookie_name, json_encode($quota_data), $cookie_expires, '/');

}
//
// $tag_scope doesn't matter right now
//
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

// Example XML document to show what is being parsed
//     <note>
//    <title>Gott, ich sehe nur bis zu meiner Nasenspitze, aber ich vertraue dir, denn du siehst alles</title>
//    <created>20251109T212539Z</created>
//    <updated>20251109T212609Z</updated>
//    <tag>Autor: Joyce Meyer</tag>
//    <tag>challenges</tag>
//    <note-attributes>
//      <author>Sebastian Kayser</author>
//    </note-attributes>
//    <content>

    
    while (($line = fgets($handle)) !== false) {

        // Entering <note>
        if (strpos($line, '<note id=') !== false) {
            $inNote = true;
            $nr_notes_scanned++;
            $noteBuffer = $line;
            $tag_found = ($tag_scope == NULL) ? TRUE : FALSE;

          // Inside <note>
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

    // Parse the selected note using regex
    if (!preg_match('/<note id="([^"]+)"/', $selectedNote, $idMatch)) {
        return "Failed to parse selected note.";
    }
    $id = $idMatch[1];

    if (!preg_match('/<title>(.*?)<\/title>/s', $selectedNote, $titleMatch)) {
        $title = '';
    } else {
        $title = trim($titleMatch[1]);
    }

    if (!preg_match('/<content>(.*?)<\/content>/s', $selectedNote, $contentMatch)) {
        $content = '';
    } else {
        $content = $contentMatch[1];
    }

    $tags = [];
    preg_match_all('/<tag>(.*?)<\/tag>/s', $selectedNote, $tagMatches);
    if (isset($tagMatches[1])) {
        $tags = array_map('trim', $tagMatches[1]);
    }

    // Extract only content inside <en-note>
    if (preg_match('/<en-note[^>]*>(.*?)<\/en-note>/is', $content, $matches)) {
        $cleanContent = $matches[1];
    } else {
        $cleanContent = htmlspecialchars($content);
    }

    return [
        'id' => $id,
        'title' => $title,
        'content' => $cleanContent,
        'tags' => $tags
    ];
}

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
                    'tags' => $tags
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

if ($DAILY_QUOTA && $isQuotaExceeded) {
    $note = "Daily request quota exceeded. Please try again tomorrow.";
    $scope = "Quota exceeded";
    $scope_link = "?";
} elseif (isset($_GET['id'])) {
    $note = getNoteById($_GET['id']);
    $scope = "Note by ID ";
    $scope_link = "?id=" . urlencode($_GET['id']);
} elseif (isset($_GET['t'])) {
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
        <?php if ($DISPLAY_TITLE) { echo "<h2>" . htmlspecialchars($note['title']) . "</h2>"; } ?>
        <div class="note-content">
            <?php echo $note['content']; ?>
        </div>
        <p><a href="note.php?id=<?php echo urlencode($note['id']); ?>">Direct link to this note</a></p>
    <?php else: ?>
        <p style="color:red;"><?php echo htmlspecialchars($note); ?></p>
    <?php endif; ?>

    <hr/>
    <?php if (is_array($note)): ?>
        <p>Tags:</p>
        <ul>
        <?php foreach ($note['tags'] as $tag) { ?>
            <li><?php printf("<a href='?t=%s'>%s</a>", urlencode($tag), $tag); ?></li>
        <?php }; ?>
        </ul>
    <?php endif; ?>

    <hr/>

    <div style="text-align: center">            
        <form action="search.php" method="GET">
            <input type="text" id="search" name="s" required>
            <button type="submit">Search</button>
        </form>
        <br/>
        <button onclick="window.location.href = 'add.php';">Add a note.</button>

    </div>
    
        <hr/>

    <div style="text-align: center; font-size: 0.7em;">
        <?php
            echo date(DATE_RFC822);
            echo " Notes scanned: " . $nr_notes_scanned;
            echo " Execution time: " . round($time_elapsed, 4) . " seconds";
            echo "<br/><br/>";
            if ($DAILY_QUOTA) {
                if ($isQuotaExceeded) {
                    echo "<span style='color:red;'>Daily request quota exceeded. Please try again tomorrow.</span><br/>";
                } else {
                    echo "<span style='color:green;'>You have " . ($DAILY_QUOTA - $quota_data['count']) . " requests left for today.</span><br/>";
                }
            }
        ?>
    </div>

    <div style="text-align: center; font-size: 0.7em;">
        <a href="https://github.com/spmkde/evernote-random/">Source code and bug tracker @ github.com</a>
    </div>

    </div>
</body>
</html>
