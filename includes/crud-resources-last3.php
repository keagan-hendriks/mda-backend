<?php

return [

    "adherence_reports" => [
        "table" => "adherence_reports",
        "primaryKey" => "report_id",
        "idParam" => "reportId",
        "fields" => [
            "userId" => ["column" => "user_id", "type" => "int", "required" => true],
            "periodStart" => ["column" => "period_start", "type" => "string", "required" => true],
            "periodEnd" => ["column" => "period_end", "type" => "string", "required" => true],
            "totalScheduled" => ["column" => "total_scheduled", "type" => "int", "required" => true],
            "totalTaken" => ["column" => "total_taken", "type" => "int", "required" => true],
            "totalSkipped" => ["column" => "total_skipped", "type" => "int", "required" => true],
            "totalMissed" => ["column" => "total_missed", "type" => "int", "required" => true],
            "adherencePercentage" => ["column" => "adherence_percentage", "type" => "float", "required" => true]
        ],
        "responseMap" => [
            "report_id" => "reportId",
            "user_id" => "userId",
            "period_start" => "periodStart",
            "period_end" => "periodEnd",
            "total_scheduled" => "totalScheduled",
            "total_taken" => "totalTaken",
            "total_skipped" => "totalSkipped",
            "total_missed" => "totalMissed",
            "adherence_percentage" => "adherencePercentage",
            "generated_at" => "generatedAt"
        ]
    ],

    "auth_tokens" => [
        "table" => "auth_tokens",
        "primaryKey" => "token_id",
        "idParam" => "tokenId",
        "fields" => [
            "userId" => ["column" => "user_id", "type" => "int", "required" => true],
            "tokenHash" => ["column" => "token_hash", "type" => "string", "required" => true],
            "expiresAt" => ["column" => "expires_at", "type" => "string", "required" => true]
        ],
        "responseMap" => [
            "token_id" => "tokenId",
            "user_id" => "userId",
            "token_hash" => "tokenHash",
            "expires_at" => "expiresAt",
            "created_at" => "createdAt"
        ]
    ],

    "trusted_contacts" => [
        "table" => "trusted_contacts",
        "primaryKey" => "contact_id",
        "idParam" => "contactId",
        "fields" => [
            "userId" => ["column" => "user_id", "type" => "int", "required" => true],
            "contactName" => ["column" => "contact_name", "type" => "string", "required" => true],
            "contactEmail" => ["column" => "contact_email", "type" => "string", "required" => true],
            "relationship" => ["column" => "relationship", "type" => "nullable_string", "required" => false],
            "invitationStatus" => ["column" => "invitation_status", "type" => "string", "required" => true]
        ],
        "responseMap" => [
            "contact_id" => "contactId",
            "user_id" => "userId",
            "contact_name" => "contactName",
            "contact_email" => "contactEmail",
            "relationship" => "relationship",
            "invitation_status" => "invitationStatus",
            "created_at" => "createdAt"
        ]
    ]
];
