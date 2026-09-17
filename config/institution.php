<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Institution Identity
    |--------------------------------------------------------------------------
    |
    | Printed on the letterhead of every official document. Kept here rather
    | than in a template so the documents can be reused by any institution
    | without editing their markup.
    |
    */

    'name' => env('INSTITUTION_NAME', 'Nama Institusi'),
    'unit' => env('INSTITUTION_UNIT', ''),
    'address' => env('INSTITUTION_ADDRESS', ''),
    'phone' => env('INSTITUTION_PHONE', ''),
    'website' => env('INSTITUTION_WEBSITE', ''),

    /*
    | Path to the letterhead logo, relative to the public disk. Left empty the
    | documents simply print without one.
    */
    'logo_path' => env('INSTITUTION_LOGO_PATH', ''),

    /*
    | Where documents are signed, when a program studi has not set its own.
    */
    'city' => env('INSTITUTION_CITY', 'Semarang'),

];
