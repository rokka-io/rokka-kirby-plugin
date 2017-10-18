<?php

$logged_in_user = site()->user();

if ($logged_in_user && $logged_in_user->hasRole('admin')) {
    return array(
        'title' => 'Create Rokka Stacks',
        'options' => array(
            array(
                'text' => 'Create now',
                'icon' => 'plus',
                'link' => '/rokka-create-stacks',
                'target' => '_blank'
            )
        ),
        'html' => function() {
            return 'Create needed rokka stacks. Will overwrite existing ones with the same name! Use only when you sure they changed/are missing';
        }
    );
}
else {
    return false;
}
