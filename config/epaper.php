<?php

return [
    'disk' => env('EPAPER_DISK', 'public'),
    'page_upload_max_file_kb' => (int) env('EPAPER_PAGE_UPLOAD_MAX_FILE_KB', 15360),
    'page_upload_max_files' => (int) env('EPAPER_PAGE_UPLOAD_MAX_FILES', 200),
];
