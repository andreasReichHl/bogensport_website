<?php

/**
 * Prints everything the paper form needs as JSON: the declaration texts of the online application
 * (private/membership/texts.php) and the membership types with fees (easyVerein groups +
 * admission_fees from the config). Called by build.py - keeps paper and online form in sync.
 */

require_once __DIR__ . '/../../private/membership/groups.php';
require_once __DIR__ . '/../../private/membership/texts.php';

echo json_encode([
    'club' => MEMBERSHIP_CLUB_NAME,
    'address' => MEMBERSHIP_CLUB_ADDRESS,
    'creditorId' => membership_config()['creditor_id'],
    'texts' => membership_texts(),
    'groups' => membership_groups(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
