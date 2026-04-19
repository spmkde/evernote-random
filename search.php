
<?php

$time_start = microtime(true);

function searchEnexFile($enexPath, $searchTerm) {
    // Load the ENEX file
    $xml = simplexml_load_file($enexPath);
    
    if ($xml === false) {
        throw new Exception("Failed to load ENEX file");
    }   
    
    $results = [];
    
    // Search through each note
    foreach ($xml->note as $note) {
        $noteContent = (string)$note->content;
        
        // Case-insensitive search
        if (stripos($noteContent, $searchTerm) !== false) {
            $results[] = [
                'title' => (string)$note->title,
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
    $matches = searchEnexFile('Quotes.enex', $_GET['s']);
    
    echo '<div style="text-align: center">';
    echo '<h1><a href="random.php">Random Evernote Note</a> </h1>';
    

    echo "<h2>Found " . count($matches) . " matches</h2>";
    echo "</div>";
    
    foreach ($matches as $match) {
        echo "<div class='note-content'>";
        echo "{$match['title']}<br/><br/>\n";
        echo "</div>";

    }
    echo "<br/><br/>";
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