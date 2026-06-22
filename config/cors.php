<?php
 
return [
    'paths' => ['api/*'],  // ✅ covers all /api routes
 
    'allowed_methods' => ['*'],
 
    'allowed_origins' => ['http://localhost:3000'],  // ✅ your frontend URL
 
    'allowed_origins_patterns' => [],
 
    'allowed_headers' => ['*'],
 
    'exposed_headers' => [],
 
    'max_age' => 0,
 
    'supports_credentials' => true,  // ✅ if using cookies/sessions
];