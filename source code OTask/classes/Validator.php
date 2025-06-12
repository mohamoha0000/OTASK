<?php
class Validator {
    public static function isValidUsername($name) {
        return !empty($name);
    }

    public static function isValidPassword($password) {
        return strlen($password) >= 6;
    }
    
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
?>