<?php

return [
    'export_retention_days' => (int) env('ACCOUNTING_EXPORT_RETENTION_DAYS', 90),
    'legal_artifact_retention_years' => (int) env('ACCOUNTING_LEGAL_ARTIFACT_RETENTION_YEARS', 10),
];
