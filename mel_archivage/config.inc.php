<?php

/**
 * ZipDownload configuration file
 */

// Zip attachments
// Only show the link when there are more than this many attachments
// -1 to prevent downloading of attachments as zip
$config['zipdownload_attachments'] = 1;

// Zip selection of messages
$config['zipdownload_selection'] = true;

// Charset to use for filenames inside the zip
// ASCII//TRANSLIT//IGNORE pour ignorer les accents
$config['zipdownload_charset'] = 'ASCII//TRANSLIT//IGNORE';

?>