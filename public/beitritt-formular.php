<?php

/**
 * GET: everything the online membership form needs from the server - the membership types
 * (easyVerein groups incl. fees) and the exact wording of all declarations.
 */

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/groups.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/texts.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    echo json_encode(
        [
            'groups' => membership_groups(),
            'texts' => membership_texts(),
            'satzungVersion' => membership_config()['satzung_version'],
            'minimumAge' => membership_config()['minimum_age'],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (EasyvereinTokenException | EasyvereinApiException | MemberAreaDatabaseException | PDOException $error) {
    error_log('beitritt-formular.php failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'unavailable']);
}
