<?php

namespace App;

class Validator
{
    public function validate(array $user)
    {

        $errors = [];
        if ($user['name'] == ''){
          $errors['name'] = "Заполните поле";
        }
        if ($user['email'] == '') {
          $errors['email'] = "Заполните поле";
        }
        return $errors;
    }
}