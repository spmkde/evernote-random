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
    <title>{$title}</title>
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

    if ($note_tags) {
        $note_tags = array_map("trim", explode(",", $note_tags));
        $note_tags_clean = array_map("htmlspecialchars", $note_tags);
    } else {
        $note_tags_clean = NULL;
    }
    
    $post_success = addNoteToENEX(
        $enex_path,
        substr($note_text, 0, 100),
        "<div>$note_text</div>",
        $note_tags_clean
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

        .autocomplete { position: relative; display: inline-block; width: auto; max-width: 400px; }
        .autocomplete input { width: 100%; min-width: 250px; }
        .suggestion-list {
          position: absolute;
          top: calc(100% + 0.25rem);
          left: 0;
          right: 0;
          border: 1px solid #ccc;
          background: #fff;
          max-height: 220px;
          overflow-y: auto;
          box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
          z-index: 10;
        }
        .suggestion-item {
          padding: 0.5rem;
          cursor: pointer;
        }
        .suggestion-item:hover {
          background: #f0f0f0;
        }
        .hidden { display: none; }
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
        <label for="tags" style="display:inline-block; margin-right:0.5rem; vertical-align:middle;">Add tags:</label>
        <div class="autocomplete" style="display:inline-block; vertical-align:middle;">
            <input type="text" name="tags" id="tags" placeholder="tag1, tag2, ...">
            <div id="tagSuggestions" class="suggestion-list hidden"></div>
        </div>
        <br/>
        <input type="submit" value="Add note">
    </form>

    <?php } ?>

    <script>
      const tagInput = document.getElementById('tags');
      if (tagInput) {
        const suggestions = document.getElementById('tagSuggestions');
        let tags = [];

        fetch('taglist.txt')
          .then(response => response.text())
          .then(text => {
            tags = text
              .split(/\r?\n/)
              .map(line => line.trim())
              .filter(line => line.length > 0);
          })
          .catch(err => {
            console.error('Failed to load taglist.txt', err);
          });

        function renderSuggestions(list) {
          if (!list.length) {
            suggestions.classList.add('hidden');
            suggestions.innerHTML = '';
            return;
          }

          suggestions.innerHTML = list
            .map(tag => `<div class="suggestion-item" data-tag="${tag}">${tag}</div>`)
            .join('');
          suggestions.classList.remove('hidden');
        }

        function getCurrentTerm(value) {
          const lastComma = value.lastIndexOf(',');
          if (lastComma === -1) {
            return { prefix: '', term: value.trim() };
          }
          return {
            prefix: value.slice(0, lastComma + 1),
            term: value.slice(lastComma + 1).trim()
          };
        }

        function updateSuggestions() {
          const { term } = getCurrentTerm(tagInput.value);
          if (!term) {
            renderSuggestions(tags.slice(0, 50));
            return;
          }

          const filtered = tags
            .filter(tag => tag.toLowerCase().includes(term.toLowerCase()))
            .slice(0, 50);

          renderSuggestions(filtered);
        }

        function applyTag(tag) {
          const { prefix } = getCurrentTerm(tagInput.value);
          tagInput.value = `${prefix} ${tag}`.trimStart();
          suggestions.classList.add('hidden');
          tagInput.focus();
          tagInput.setSelectionRange(tagInput.value.length, tagInput.value.length);
        }

        tagInput.addEventListener('input', updateSuggestions);
        tagInput.addEventListener('focus', updateSuggestions);
        tagInput.addEventListener('blur', () => {
          setTimeout(() => suggestions.classList.add('hidden'), 150);
        });

        suggestions.addEventListener('mousedown', event => {
          const item = event.target.closest('.suggestion-item');
          if (!item) return;
          event.preventDefault();
          applyTag(item.dataset.tag);
        });
      }
    </script>

    <hr/>

    <div style="text-align: center; font-size: 0.7em;">
        <a href="https://github.com/spmkde/evernote-random/">Source code and bug tracker @ github.com</a>
    </div>

    </div>
</body>
</html>
