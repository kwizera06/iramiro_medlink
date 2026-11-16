<?php
// includes/upload_safe.php — the stored file extension comes from the detected content type, never from the client's file name.

// Extension for an uploaded file's real MIME type, or null when the type is not allowed.
function upload_ext_from_mime(string $tmpPath): ?string
{
    static $map = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt', 'text/csv' => 'csv', 'text/rtf' => 'rtf', 'application/rtf' => 'rtf',
        'application/vnd.oasis.opendocument.text' => 'odt', 'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
    ];
    $f = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $f ? finfo_file($f, $tmpPath) : false;
    return $map[$mime] ?? null;
}
