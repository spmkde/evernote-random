<?php

# TODO
# - Move nr_tags_found to tag list

# $time_start = microtime(true);


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

/**
 * Add a note to an Evernote ENEX file
 */

function addNoteToENEX($enexFile, $title, $content, $created = null, $updated = null) {
    //echo "Called add  with $enexFile, $title, $content, $created, $updated";
    // Default timestamps if not provided
    if ($created === null) {
        $created = gmdate('Ymd\THis\Z');
    }
    if ($updated === null) {
        $updated = gmdate('Ymd\THis\Z');
    }
    
    // Create the note XML structure
    $noteXml = <<<XML
  <note>
    <title><![CDATA[{$title}]]></title>
    <created>{$created}</created>
    <updated>{$updated}</updated>
    <note-attributes>
    </note-attributes>
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

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    
    $enex_path = isset($_POST['file']) ? trim($_POST['file']) : NULL;
    $note_text = isset($_POST['note']) ? trim($_POST['note']) : NULL;
        

    $success = addNoteToENEX(
        $enex_path,
        substr($note_text, 0, 50),
        "<div>$note_text</div>",
        gmdate('Ymd\THis\Z'),
        gmdate('Ymd\THis\Z')
    );
    
    //echo "AFTER ADD";

    // Basic validation
    if (empty($note_text)) {
        echo "Error: Textarea cannot be empty.";
    } else {
        // Sanitize output to prevent XSS
        $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    appendNoteToEnexFile($enex_path, substr($note_text, 0, 50), $note_text);
    //appendNoteToEnexFile($enex_path, "note_text", "note_text");

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Random Evernote Note</title>
    <style>
        body {
            font-family: sans-serif;
            background-color: #fafafa;
            margin: 2rem;
            color: #333;
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: #01A72D;
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
            color: #01A72D;
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
        <h1><a href="random.php">Random Evernote Note</a> - Add a note</h1>
    <hr/>
    
    <form action="add.php" method="POST">
        <textarea id="note" name="note"></textarea></br> 
        <select name="file" id="file">
            <?php foreach ($enex_files as $enex_file) {
                printf("<option value='%s'>%s</option>", $enex_file, $enex_file);
            } ?>
        <input type="submit" value="Add note">
    </select>
    </form>

    <hr/>

    <div style="text-align: center; font-size: 0.7em;">
        <a href="https://github.com/spmkde/evernote-random/">Source code and bug tracker @ github.com</a>
    </div>

    </div>
</body>
</html>
