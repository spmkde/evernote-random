<?php

# test

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

function addNoteToENEX($enexFile, $title, $content, $tags = NULL) {
 
    // Default timestamps if not provided
    $created = gmdate('Ymd\THis\Z');
    $updated = gmdate('Ymd\THis\Z');
    
    // Create the note XML structure
    $noteXml = <<<XML
  <note>
    <title><![CDATA[{$title}]]></title>
    <created>{$created}</created>
    <updated>{$updated}</updated>
    <note-attributes>
    </note-attributes>

XML;

    foreach ($tags as $tag) {
        $noteXml .= "<tag>$tag</tag>\n";
    }

    $noteXml .= <<<XML

    <content><![CDATA[<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
<en-note>{$content}</en-note>
]]></content>
  </note>
XML;
    
    // Read existing ENEX file
    $existingContent = '';
    if (file_exists($enexFile)) {
        $existingContent = file_get_contents($enexFile);
        
        // Remove the closing </en-export> tag temporarily
        $existingContent = preg_replace('/\s*<\/en-export>\s*$/', '', $existingContent);
    } else {
        // Create new ENEX header
        $existingContent = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE en-export SYSTEM "http://xml.evernote.com/pub/enex-1.0.dtd">
<en-export export-date="' . gmdate('Ymd\THis\Z') . '" application="Evernote" version="13.0">';
    }
    
    // Append the new note and close the export tag
    $newContent = $existingContent . "\n" . $noteXml . "\n</en-export>";
    
    // Write back to file
    if (file_put_contents($enexFile, $newContent) !== false) {
        return true;
    }
    
    return false;
}

$post_success = FALSE;
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    
    $enex_path = isset($_POST['file']) ? trim($_POST['file']) : NULL;
    $note_text = isset($_POST['note']) ? trim($_POST['note']) : NULL;
    $note_tags = isset($_POST['tags']) ? trim($_POST['tags']) : NULL;

    $note_tags = explode(",", $note_tags);

    
    $post_success = addNoteToENEX(
        $enex_path,
        substr($note_text, 0, 50),
        "<div>$note_text</div>",
        $note_tags
    );

    // Basic validation
    if (empty($note_text)) {
        echo "Error: Textarea cannot be empty.";
    } else {
        // Sanitize output to prevent XSS
        $safe_text = htmlspecialchars($note_text, ENT_QUOTES, 'UTF-8');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Random Evernote Note</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
         textarea {
            border:1px solid #999999;
            width:90%;
            height:100px;
            margin:3em 0;
            font-family: sans-serif;
        }

        input, select {
            font-size:1em;
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="container" style="text-align: center">
        <h1><a href="random.php">Random Evernote Note</a> - <a href="add.php">Add a note</a></h1>
    <hr/>

    <?php if ($post_success) { 
        echo "Note successfully saved to <b>$enex_path</b>!";   
    ?>

    <br/><br/>
    <button onclick="window.location.href = 'add.php';">Add another note.</button>


    <?php } else { ?>

    <form action="add.php" method="POST" id="newnote">
        <textarea id="note" name="note"></textarea><br/>
        Notebook: 
        <select name="file" id="file">
            <?php foreach ($enex_files as $enex_file) {
                printf("<option value='%s'>%s</option>\n", $enex_file, $enex_file);
            } ?>
        </select>
        <br/>
        Add tags:<input type="text" name="tags" id="tags"><br/> 
        <input type="submit" value="Add note">
    </form>

    <?php } ?>

    <hr/>

    <div style="text-align: center; font-size: 0.7em;">
        <a href="https://github.com/spmkde/evernote-random/">Source code and bug tracker @ github.com</a>
    </div>

    </div>
</body>
</html>
