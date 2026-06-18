
<?php

$time_start = microtime(true);

function searchEnexFile($enexPath, $searchTerm) {
    // Load the ENEX file
    $xml = simplexml_load_file($enexPath);
    libxml_use_internal_errors(true);
    
    if ($xml === false) {
        echo "Error loading XML file $enexPath";
        foreach(libxml_get_errors() as $error) {
           echo "\t", $error->message;
        }
        return FALSE;
    }
    
    $results = [];
    
    // Search through each note
    foreach ($xml->note as $note) {
        $noteContent = (string)$note->content;
        
        // Case-insensitive search
        if (stripos($noteContent, $searchTerm) !== false) {
            $results[] = [
                'id' => (string)$note['id'],
                'title' => (string)$note->title,
                'content' => (string)$note->{'content'},
                'created' => (string)$note->{'created'},
                'updated' => (string)$note->{'updated'},
                'match_position' => stripos($noteContent, $searchTerm)
            ];
        }
    }
    
    return $results;
}

?>

<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Evernote Note</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
<?php

if (!isset($_GET['s'])) {
    echo "Please supply a search string";
    exit;
}

// Usage example
try {
    $searchTerm = $_GET['s'];
    $matches = [];
    $enexFiles = glob('*.enex');

    if (empty($enexFiles)) {
        throw new Exception('No ENEX files found');
    }

    foreach ($enexFiles as $enexFile) {
        if ($enexFile == "sandbox.enex") {
            continue; // Skip sandbox file
        }
        $matches = array_merge($matches, searchEnexFile($enexFile, $searchTerm));
    }
    
    echo '<div style="text-align: center">';
    echo '<h1><a href="random.php">Random Evernote Note</a> </h1><br/><hr/>';
    

    echo "<h2>Found " . count($matches) . " matches for '" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "'</h2>";

         echo '<form action="search.php" method="GET">';
         echo '<input type="text" id="search" name="s" required> ';
         echo '<button type="submit">Search again</button>';
         echo '</form>';

         echo '<hr/>';

             echo "</div>";

    
    echo "<div class='accordion'>";
    foreach ($matches as $match) {
        echo "<div class='note-header'><h3>{$match['title']}</h3></div>";
        echo "<div class='note-content'>";
        echo trim($match['content']);
        echo "<a href='note.php?id=" . urlencode($match['id']) . "'>Direct link to this note</a>";        
        echo "</div>";
        echo "<br/>";
    }
    echo "</div><br/><br/>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
    $time_elapsed = microtime(true) - $time_start;
    echo '<div style="text-align: center; font-size: 0.7em;">';
    echo date(DATE_RFC822);
    echo " Execution time: " . round($time_elapsed, 4) . " seconds";
    echo "</div>"
?>

</div>
