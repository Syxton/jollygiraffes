<?php

/***************************************************************************
* files.php - Authenticated file gateway.
* -------------------------------------------------------------------------
* Every attachment/avatar/document in the app is served through this single
* script instead of a direct URL. The actual files live in $CFG->userfilespath,
* which is OUTSIDE the web root, so there is no direct URL to them at all -
* this script is the only way to read them, and it requires a logged-in
* session plus an authorization check (status_can_access_document) before it
* will stream a single byte.
*
* Usage: files.php?did=123            (inline, e.g. <img src="...">)
*        files.php?did=123&download=1 (forces a "Save As" download)
***************************************************************************/

if (!isset($CFG)) {
    include_once('config.php');
}
include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/lib/status_lib.php');

status_migrate();
status_start_session();

// Allowed extensions -> mime type. Keep this in sync with what upload.php accepts;
// anything not on this list is refused rather than guessed at.
$allowed_ext = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'heic' => 'image/heic',
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt'  => 'text/plain',
];

function files_deny($code) {
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $code === 403 ? 'Forbidden' : ($code === 404 ? 'Not found' : 'Error');
    exit;
}

// Must be logged in as either role before anything else happens.
if (!status_current_role()) {
    files_deny(403);
}

$did = isset($_GET['did']) ? intval($_GET['did']) : 0;
if (!$did) {
    files_deny(404);
}

$document = get_db_row("SELECT * FROM documents WHERE did='" . $did . "'");
if (!$document) {
    files_deny(404);
}

// The single choke point: is this logged-in session allowed to see this specific document?
if (!status_can_access_document($document)) {
    files_deny(403);
}

// Work out which subfolder this document lives in, same mapping the rest of the app uses.
if (!empty($document["chid"])) {
    $folder = "children/" . $document["chid"];
} elseif (!empty($document["cid"])) {
    $folder = "contacts/" . $document["cid"];
} elseif (!empty($document["actid"])) {
    $folder = "activities/" . $document["actid"];
} elseif (!empty($document["aid"])) {
    $folder = "accounts/" . $document["aid"];
} else {
    files_deny(404);
}

$filename = $document["filename"];

// Belt-and-suspenders path safety: filenames are generated server-side by upload.php
// (tag_timestamp.ext) and never come from user input at read time, but we still refuse
// anything containing a path separator or "..", and confirm the resolved path is really
// inside userfilespath before touching the filesystem.
if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
    files_deny(404);
}

$base = realpath($CFG->userfilespath);
$full = realpath($CFG->userfilespath . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename);

if ($base === false || $full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) {
    files_deny(404);
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
if (!array_key_exists($ext, $allowed_ext)) {
    files_deny(404);
}

$mtype = $allowed_ext[$ext];
$download = !empty($_GET['download']);

header('Content-Type: ' . $mtype);
header('Content-Length: ' . filesize($full));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($filename) . '"');
// These files can contain personal info about kids/families - never let intermediate
// caches or the browser disk-cache keep a copy around after the session ends.
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

ob_clean();
flush();
readfile($full);
exit;
