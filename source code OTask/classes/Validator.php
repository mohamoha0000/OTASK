<?php
class Validator {
    public static function isValidUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,}$/', $username);
    }

    public static function isValidPassword($password) {
        return strlen($password) >= 6;
    }
}
?>