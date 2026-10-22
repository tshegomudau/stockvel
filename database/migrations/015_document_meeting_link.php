<?php

declare(strict_types=1);

/**
 * Optional link from typed documents to a meeting (shown on member meeting view).
 */
return [
    'up' => [
        'ALTER TABLE documents
            ADD COLUMN meeting_id BIGINT UNSIGNED NULL AFTER document_category_id',
        'ALTER TABLE documents
            ADD KEY idx_documents_stokvel_meeting (stokvel_id, meeting_id)',
        'ALTER TABLE documents
            ADD CONSTRAINT fk_documents_meeting
            FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE SET NULL',
    ],
    'down' => [
        'ALTER TABLE documents DROP FOREIGN KEY fk_documents_meeting',
        'ALTER TABLE documents DROP KEY idx_documents_stokvel_meeting',
        'ALTER TABLE documents DROP COLUMN meeting_id',
    ],
];
