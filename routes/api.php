<?php

/*
 * Reference enum lists were previously exposed here without authentication.
 * They now live behind auth:admin in routes/api_admin.php under the
 * /api/admin/enum/* prefix. Keep this file free of unauthenticated data
 * endpoints — public, unauthenticated routes belong in routes/api_public.php.
 */
