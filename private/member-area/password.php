<?php

function member_area_password_requirements_text(): string
{
    return 'Das Passwort muss mindestens 8 Zeichen lang sein und mindestens einen Großbuchstaben, eine Zahl und eines dieser Sonderzeichen enthalten: ! @ # $ % & * + - = ? _ . : , ; ~';
}

function member_area_validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return member_area_password_requirements_text();
    }

    if (!preg_match('/[A-ZÄÖÜ]/u', $password)) {
        return member_area_password_requirements_text();
    }

    if (!preg_match('/\d/', $password)) {
        return member_area_password_requirements_text();
    }

    if (!preg_match('/[!@#$%&*+\-=?_.:,;~]/', $password)) {
        return member_area_password_requirements_text();
    }

    return null;
}
