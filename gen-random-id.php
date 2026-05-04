<?php

/**
 * PHP script to add a unique UUID v4 ID to each <note> element in an Evernote ENEX file.
 * 
 * Usage: php add_unique_id_to_notes.php input.enex output.enex
 * 
 * This script parses the ENEX XML file, generates a unique ID for each note,
 * adds it as an 'id' attribute to the <note> element, and saves the modified file.
 */


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

function add_id_to_notes($enex_file) {
    // Load the ENEX file as XML
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    // Suppress warnings for DTD validation issues
    libxml_use_internal_errors(true);
    if (!$dom->load($enex_file)) {
        die("Error: Failed to load XML from '$enex_file'. Check if it's a valid ENEX file.\n");
    }
    libxml_clear_errors();

    // Find all <note> elements
    $notes = $dom->getElementsByTagName('note');

    // Add unique ID to each note
    foreach ($notes as $note) {
        $content = $note->getElementsByTagName('content')->item(0)->textContent;
        $uuid = sha1($content);
        $note->setAttribute('id', $uuid);
    }

    // Save the modified XML back to the same file
    if ($dom->save($enex_file)) {
        echo "Successfully added unique IDs to notes in '$enex_file'.\n";
    } else {
        echo "Error: Failed to save modified ENEX to '$enex_file'.\n";
    }
}

foreach ($enex_files as $enex_file) {
    echo "Adding IDs to $enex_file\n";
    add_id_to_notes($enex_file);
}

?>