<?php

/**
 * ZipDownload configuration file
 */

// Zip attachments
// Only show the link when there are more than this many attachments
// -1 to prevent downloading of attachments as zip
$config['mel_archivage_attachments'] = 1;

// Zip selection of messages
$config['mel_archivage_selection'] = true;

// Charset to use for filenames inside the zip
// ASCII//TRANSLIT//IGNORE pour ignorer les accents
$config['mel_archivage_charset'] = 'ASCII//TRANSLIT//IGNORE';

// $config['mel_archivage_folder'] = "Messages archiv&AOk-s";
$config['mel_archivage_folder'] = null;
?>