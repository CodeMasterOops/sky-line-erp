<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QZ Tray Signing
    |--------------------------------------------------------------------------
    |
    | QZ Tray requires every print request to be cryptographically signed so
    | the browser can talk to local printers without a trust prompt. The
    | private key signs requests server-side; the matching public certificate
    | is handed to the browser. Generate a key pair / certificate following the
    | QZ Tray "Signing" guide and point these at the resulting files.
    |
    | When either path is missing or unreadable the signing endpoints return
    | empty responses and the front-end falls back to QZ Tray's unsigned mode
    | (which shows a one-time "Allow" prompt on the terminal) and, failing
    | that, to standard browser printing.
    |
    */

    'certificate_path' => env('QZ_CERTIFICATE_PATH'),

    'private_key_path' => env('QZ_PRIVATE_KEY_PATH'),

    'private_key_passphrase' => env('QZ_PRIVATE_KEY_PASSPHRASE'),

    /*
    | Signature algorithm. QZ Tray 2.1+ defaults to SHA512. Must match the
    | algorithm configured on the front-end (qz.security.setSignatureAlgorithm).
    */
    'algorithm' => env('QZ_SIGNATURE_ALGORITHM', 'SHA512'),

];
