<?php

function get_enex_files() {
    // Get all files from current directory ending in .enex
    $enex_files = glob('*.enex');
    
    // Return empty array if no files found
    return $enex_files !== false ? $enex_files : [];
}

$enex_files = get_enex_files();

function fix_title_cdata($enex_file) {
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

    // Fix title CDATA for each note
    foreach ($notes as $note) {
        $title = $note->getElementsByTagName('title')->item(0);
        if ($title) {
            // Remove CDATA by setting textContent to itself
            $title->textContent = $title->textContent;
        }
    }

    // Save the modified XML back to the same file
    if ($dom->save($enex_file)) {
        echo "Successfully fixed title CDATA in '$enex_file'.\n";
    } else {
        echo "Error: Failed to save modified ENEX to '$enex_file'.\n";
    }
}

foreach ($enex_files as $enex_file) {
    echo "Fixing title CDATA in $enex_file\n";
    fix_title_cdata($enex_file);
}

?>